<?php

namespace tallowandsons\cleaver\services;

use Craft;
use craft\elements\Entry;
use craft\models\Section;
use tallowandsons\cleaver\Cleaver;
use tallowandsons\cleaver\jobs\ChopEntriesJob;
use tallowandsons\cleaver\models\ChopConfig;
use yii\base\Component;

/**
 * Chop Service service
 */
class ChopService extends Component
{
    /**
     * Plan and execute a chop operation using the provided configuration
     */
    public function planChop(ChopConfig $config): void
    {
        $sections = $this->getSectionsFromConfig($config);

        if (empty($sections)) {
            Cleaver::log("No valid sections found for chop operation", 'service');
            return;
        }

        $sectionNames = array_map(fn($s) => $s->name, $sections);
        $mode = $config->dryRun ? 'DRY RUN' : 'LIVE';
        Cleaver::log("Starting chop operation ({$mode}) for " . count($sections) . " sections: " . implode(', ', $sectionNames), 'service');
        Cleaver::debug("Configuration: " . $config->getSummary(), 'service');

        foreach ($sections as $section) {
            $this->chopEntriesFromSection($section, $config);
        }

        Cleaver::log("Completed chop operation ({$mode}) for all sections", 'service');
    }

    /**
     * Get Section objects from ChopConfig section handles
     */
    private function getSectionsFromConfig(ChopConfig $config): array
    {
        if (empty($config->sectionHandles)) {
            // Return all sections if none specified
            Cleaver::debug("No section handles specified, returning all sections", 'service');
            return Craft::$app->entries->getAllSections();
        }

        $sections = [];
        foreach ($config->sectionHandles as $handle) {
            $section = Craft::$app->entries->getSectionByHandle($handle);
            if ($section) {
                $sections[] = $section;
                Cleaver::debug("Found section: {$section->name} (handle: {$handle})", 'service');
            } else {
                Cleaver::log("Warning: Section '{$handle}' not found", 'service');
            }
        }

        return $sections;
    }

    /**
     * Chop entries from a specific section while preserving status distribution
     */
    private function chopEntriesFromSection(Section $section, ChopConfig $config): void
    {
        Cleaver::debug("Processing section: {$section->name} (handle: {$section->handle})" . ($config->dryRun ? ' - DRY RUN' : ''), 'service');

        // Get all unique statuses in this section
        $statuses = $this->getUniqueStatusesInSection($section);
        Cleaver::debug("Found statuses in section {$section->handle}: " . implode(', ', $statuses), 'service');

        // Filter statuses if target statuses are specified
        if (!empty($config->statuses)) {
            $statuses = array_intersect($statuses, $config->statuses);
            Cleaver::debug("Filtered to target statuses: " . implode(', ', $statuses), 'service');
        }

        $totalJobsQueued = 0;
        foreach ($statuses as $status) {
            $entriesToDelete = $this->selectEntriesToDelete($section, $status, $config);

            if (!empty($entriesToDelete)) {
                $this->queueDeletionJob($entriesToDelete, $section->handle, $status, $config);
                $totalJobsQueued++;
                $mode = $config->dryRun ? 'DRY RUN' : 'deletion';
                Cleaver::debug("Queued {$mode} job for {$section->handle} (status: {$status}) - " . count($entriesToDelete) . " entries", 'service');
            } else {
                Cleaver::debug("No entries to delete for {$section->handle} (status: {$status})", 'service');
            }
        }

        if ($totalJobsQueued > 0) {
            $mode = $config->dryRun ? 'DRY RUN' : 'deletion';
            Cleaver::log("Queued {$totalJobsQueued} {$mode} jobs for section: {$section->name}", 'service');
        }
    }

    /**
     * Get all unique entry statuses in a section
     */
    private function getUniqueStatusesInSection(Section $section): array
    {
        $query = Entry::find()
            ->sectionId($section->id)
            ->select(['status'])
            ->distinct()
            ->orderBy(null) // Remove any default ordering
            ->asArray();

        $results = $query->all();

        return array_column($results, 'status');
    }

    /**
     * Select entries to delete from a section with a specific status
     */
    private function selectEntriesToDelete(Section $section, string $status, ChopConfig $config): array
    {
        // Get all entries with this status
        $totalEntries = Entry::find()
            ->sectionId($section->id)
            ->status($status)
            ->count();

        if ($totalEntries === 0) {
            Cleaver::debug("No entries found for {$section->handle} with status: {$status}", 'service');
            return [];
        }

        // Calculate how many to delete based on percentage
        $entriesToDeleteCount = (int) ceil($totalEntries * ($config->percent / 100));

        // Ensure we don't delete more than we should to maintain minimum
        $maxDeletions = max(0, $totalEntries - $config->minimumEntries);
        $entriesToDeleteCount = min($entriesToDeleteCount, $maxDeletions);

        Cleaver::debug("Section {$section->handle} (status: {$status}) - Total: {$totalEntries}, Will delete: {$entriesToDeleteCount}, Min entries: {$config->minimumEntries}", 'service');

        if ($entriesToDeleteCount === 0) {
            return [];
        }

        // Get all entry IDs with this status
        $allEntryIds = Entry::find()
            ->sectionId($section->id)
            ->status($status)
            ->select(['id'])
            ->column();

        // Shuffle and take the required number
        shuffle($allEntryIds);

        return array_slice($allEntryIds, 0, $entriesToDeleteCount);
    }

    /**
     * Queue a deletion job for the selected entries
     */
    private function queueDeletionJob(array $entryIds, string $sectionHandle, string $status, ChopConfig $config): void
    {
        $job = new ChopEntriesJob([
            'entryIds' => $entryIds,
            'sectionHandle' => $sectionHandle,
            'status' => $status,
            'config' => $config->toJobArray(),
        ]);

        Craft::$app->getQueue()->push($job);
        $mode = $config->dryRun ? 'DRY RUN' : 'deletion';
        Cleaver::debug("Queued ChopEntriesJob ({$mode}) with " . count($entryIds) . " entry IDs for section: {$sectionHandle} (status: {$status})", 'service');
    }
}
