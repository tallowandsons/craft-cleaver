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
        foreach ($sections as $section) {
            $this->chopEntriesFromSection($section, $percent, $minimumEntries, $targetStatuses);
        }
    }

    /**
     * Chop entries from a specific section while preserving status distribution
     */
    private function chopEntriesFromSection(Section $section, int $percent, ?int $minimumEntries = null, ?array $targetStatuses = null): void
    {
        // Get all unique statuses in this section
        $statuses = $this->getUniqueStatusesInSection($section);

        // Filter statuses if target statuses are specified
        if ($targetStatuses !== null) {
            $statuses = array_intersect($statuses, $targetStatuses);
        }

        foreach ($statuses as $status) {
            $entriesToDelete = $this->selectEntriesToDelete($section, $status, $percent, $minimumEntries);

            if (!empty($entriesToDelete)) {
                $this->queueDeletionJob($entriesToDelete, $section->handle, $status);
            }
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
    }
}
