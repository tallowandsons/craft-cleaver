<?php

namespace tallowandsons\cleaver\services;

use Craft;
use craft\elements\Entry;
use craft\models\Section;
use tallowandsons\cleaver\Cleaver;
use tallowandsons\cleaver\jobs\ChopEntriesJob;
use yii\base\Component;

/**
 * Chop Service service
 */
class ChopService extends Component
{
    /**
     * Chop entries from the specified sections
     */
    public function chopEntries(array $sections, int $percent, ?int $minimumEntries = null, ?array $targetStatuses = null): void
    {
        $sectionNames = array_map(fn($s) => $s->name, $sections);
        Cleaver::log("Starting chop operation for " . count($sections) . " sections: " . implode(', ', $sectionNames), 'service');
        Cleaver::debug("Chop parameters - percent: {$percent}%, minimumEntries: " . ($minimumEntries ?? 'default') . ", targetStatuses: " . ($targetStatuses ? implode(', ', $targetStatuses) : 'all'), 'service');

        foreach ($sections as $section) {
            $this->chopEntriesFromSection($section, $percent, $minimumEntries, $targetStatuses);
        }

        Cleaver::log("Completed chop operation for all sections", 'service');
    }

    /**
     * Chop entries from a specific section while preserving status distribution
     */
    private function chopEntriesFromSection(Section $section, int $percent, ?int $minimumEntries = null, ?array $targetStatuses = null): void
    {
        Cleaver::debug("Processing section: {$section->name} (handle: {$section->handle})", 'service');

        // Get all unique statuses in this section
        $statuses = $this->getUniqueStatusesInSection($section);
        Cleaver::debug("Found statuses in section {$section->handle}: " . implode(', ', $statuses), 'service');

        // Filter statuses if target statuses are specified
        if ($targetStatuses !== null) {
            $statuses = array_intersect($statuses, $targetStatuses);
            Cleaver::debug("Filtered to target statuses: " . implode(', ', $statuses), 'service');
        }

        $totalJobsQueued = 0;
        foreach ($statuses as $status) {
            $entriesToDelete = $this->selectEntriesToDelete($section, $status, $percent, $minimumEntries);

            if (!empty($entriesToDelete)) {
                $this->queueDeletionJob($entriesToDelete, $section->handle, $status);
                $totalJobsQueued++;
                Cleaver::debug("Queued deletion job for {$section->handle} (status: {$status}) - " . count($entriesToDelete) . " entries", 'service');
            } else {
                Cleaver::debug("No entries to delete for {$section->handle} (status: {$status})", 'service');
            }
        }

        if ($totalJobsQueued > 0) {
            Cleaver::log("Queued {$totalJobsQueued} deletion jobs for section: {$section->name}", 'service');
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
    private function selectEntriesToDelete(Section $section, string $status, int $percent, ?int $minimumEntries = null): array
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

        // Get the minimum entries setting (use override if provided)
        if ($minimumEntries === null) {
            $settings = Cleaver::getInstance()->getSettings();
            $minimumEntries = $settings->minimumEntries;
        }

        // Calculate how many to delete based on percentage
        $entriesToDeleteCount = (int) ceil($totalEntries * ($percent / 100));

        // Ensure we don't delete more than we should to maintain minimum
        $maxDeletions = max(0, $totalEntries - $minimumEntries);
        $entriesToDeleteCount = min($entriesToDeleteCount, $maxDeletions);

        Cleaver::debug("Section {$section->handle} (status: {$status}) - Total: {$totalEntries}, Will delete: {$entriesToDeleteCount}, Min entries: {$minimumEntries}", 'service');

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
    private function queueDeletionJob(array $entryIds, string $sectionHandle, string $status): void
    {
        $job = new ChopEntriesJob([
            'entryIds' => $entryIds,
            'sectionHandle' => $sectionHandle,
            'status' => $status,
        ]);

        Craft::$app->getQueue()->push($job);
        Cleaver::debug("Queued ChopEntriesJob with " . count($entryIds) . " entry IDs for section: {$sectionHandle} (status: {$status})", 'service');
    }
}
