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
     * Whether this is a dry run (no actual deletion)
     */
    public bool $dryRun = false;

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

        $mode = $this->dryRun ? 'DRY RUN' : 'LIVE';
        Cleaver::log("Starting ChopEntriesJob ({$mode}) for section '{$this->sectionHandle}' (status: {$this->status}) - " . count($this->entryIds) . " entries to process", 'job');
        Cleaver::debug("Job configuration - batchSize: {$this->batchSize}, deleteMode: {$settings->deleteMode}, dryRun: " . ($this->dryRun ? 'true' : 'false'), 'job');

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
            Cleaver::debug("Entry ID {$entryId} not found (may have been already deleted)", 'job');
            return;
        }

        if ($this->dryRun) {
            // In dry run mode, just log what would be deleted
            Cleaver::log("DRY RUN: Would delete entry ID {$entryId}: '{$entry->title}' from section '{$this->sectionHandle}'", 'job');
            Cleaver::debug("DRY RUN: Entry details - ID: {$entryId}, Title: '{$entry->title}', Status: {$entry->status}, Section: {$this->sectionHandle}", 'job');
            return;
        }

        Cleaver::debug("Processing entry ID {$entryId}: '{$entry->title}' from section '{$this->sectionHandle}'", 'job');

        // Delete the entry
        $settings = Cleaver::getInstance()->getSettings();
        $hardDelete = ($settings->deleteMode === Settings::DELETE_MODE_HARD);

        if (!Craft::$app->getElements()->deleteElement($entry, $hardDelete)) {
            $deleteType = $hardDelete ? 'hard' : 'soft';
            Cleaver::log("Failed to {$deleteType} delete entry ID {$entryId}: '{$entry->title}'", 'job');
            throw new \Exception("Failed to delete entry ID: {$entryId}");
        }

        $deleteType = $hardDelete ? 'hard' : 'soft';
        Cleaver::debug("Successfully {$deleteType} deleted entry ID {$entryId}: '{$entry->title}'", 'job');
    }

    protected function defaultDescription(): ?string
    {
        $count = count($this->entryIds);
        $mode = $this->dryRun ? ' (DRY RUN)' : '';
        return "Chopping {$count} entries from section '{$this->sectionHandle}' (status: {$this->status}){$mode}";
    }

    public function afterExecute(): void
    {
        parent::afterExecute();
        $mode = $this->dryRun ? 'DRY RUN' : 'LIVE';
        Cleaver::log("Completed ChopEntriesJob ({$mode}) for section '{$this->sectionHandle}' (status: {$this->status}) - processed " . count($this->entryIds) . " entries", 'job');
    }

    public function canRetry($attempt, $error): bool
    {
        $canRetry = $attempt < 3;
        if ($canRetry) {
            Cleaver::log("ChopEntriesJob retry attempt {$attempt} for section '{$this->sectionHandle}' due to error: " . $error->getMessage(), 'job');
        } else {
            Cleaver::log("ChopEntriesJob failed after 3 attempts for section '{$this->sectionHandle}': " . $error->getMessage(), 'job');
        }
        return $canRetry;
    }

    public function getTtr(): int
    {
        // 5 minutes TTR
        return 300;
    }
}
