<?php

namespace tallowandsons\cleaver\jobs;

use Craft;
use craft\base\Batchable;
use craft\elements\Entry;
use craft\queue\BaseBatchedElementJob;
use tallowandsons\cleaver\Cleaver;
use tallowandsons\cleaver\models\Settings;
use yii\queue\RetryableJobInterface;

/**
 * Entry IDs Batchable class
 */
class EntryIdsBatcher implements Batchable
{
    public function __construct(
        private array $entryIds,
    ) {}

    public function count(): int
    {
        return count($this->entryIds);
    }

    public function getSlice(int $offset, int $limit): iterable
    {
        return array_slice($this->entryIds, $offset, $limit);
    }
}

/**
 * Chop Entries Job queue job
 */
class ChopEntriesJob extends BaseBatchedElementJob implements RetryableJobInterface
{
    /**
     * Array of entry IDs to delete
     */
    public array $entryIds = [];

    /**
     * Section handle for logging purposes
     */
    public string $sectionHandle = '';

    /**
     * Entry status for logging purposes
     */
    public string $status = '';

    /**
     * The batch size for processing entries
     */
    public int $batchSize = 50;

    protected function loadData(): Batchable
    {
        return new EntryIdsBatcher($this->entryIds);
    }

    /**
     * Initialize the job configuration
     */
    public function init(): void
    {
        // Always use batch size from settings
        $settings = Cleaver::getInstance()->getSettings();
        $this->batchSize = $settings->batchSize;

        parent::init();
    }
    protected function processItem(mixed $item): void
    {
        $entryId = $item;

        // Get the entry
        $entry = Entry::find()
            ->id($entryId)
            ->one();

        if (!$entry) {
            // Entry might have already been deleted
            return;
        }

        // Delete the entry
        $settings = Cleaver::getInstance()->getSettings();
        $hardDelete = ($settings->deleteMode === Settings::DELETE_MODE_HARD);

        if (!Craft::$app->getElements()->deleteElement($entry, $hardDelete)) {
            throw new \Exception("Failed to delete entry ID: {$entryId}");
        }
    }

    protected function defaultDescription(): ?string
    {
        $count = count($this->entryIds);
        return "Chopping {$count} entries from section '{$this->sectionHandle}' (status: {$this->status})";
    }

    public function canRetry($attempt, $error): bool
    {
        // Allow up to 3 retry attempts
        return $attempt < 3;
    }

    public function getTtr(): int
    {
        // 5 minutes TTR
        return 300;
    }
}
