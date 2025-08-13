<?php

namespace tallowandsons\cleaver\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use tallowandsons\cleaver\Cleaver;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Chop controller
 */
class ChopController extends Controller
{
    public $defaultAction = 'index';

    /**
     * Comma-separated list of section handles to target
     */
    public ?string $sections = null;

    /**
     * Comma-separated list of entry statuses to target (live, disabled, pending, etc)
     */
    public ?string $statuses = null;

    /**
     * The percentage of entries to delete
     */
    public ?int $percent = null;

    /**
     * Minimum number of entries to keep per section
     */
    public ?int $minEntries = null;

    /**
     * Skip confirmation prompt
     */
    public bool $skipConfirm = false;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        switch ($actionID) {
            case 'index':
                $options[] = 'sections';
                $options[] = 'statuses';
                $options[] = 'percent';
                $options[] = 'minEntries';
                $options[] = 'skipConfirm';
                break;
        }
        return $options;
    }

    public function optionAliases(): array
    {
        return [
            's' => 'sections',
            'st' => 'statuses',
            'p' => 'percent',
            'm' => 'minEntries',
            'y' => 'skipConfirm',
        ];
    }

    /**
     * Randomly delete entries from sections while preserving entry status distribution
     *
     * The command respects a minimum entries constraint to prevent complete deletion
     * of all entries from a section. This can be configured in plugin settings or
     * overridden using the --min-entries option.
     *
     * The command is protected by an environment lock and will only run in
     * specifically allowed environments configured in plugin settings.
     */
    public function actionIndex(): int
    {
        $plugin = Cleaver::getInstance();
        $settings = $plugin->getSettings();

        // Check environment lock
        if (!$this->checkEnvironmentLock($settings)) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Use provided percent or default from settings
        $deletePercent = $this->percent ?? $settings->defaultPercent;

        // Validate percentage
        if ($deletePercent < 1 || $deletePercent > 99) {
            $this->stderr("Error: Percentage must be between 1 and 99.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Get target sections
        $targetSections = $this->getTargetSections();
        if (empty($targetSections)) {
            $this->stderr("Error: No valid sections found.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Get target statuses
        $targetStatuses = $this->getTargetStatuses();
        if ($targetStatuses === []) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Validate minimum entries if provided
        if ($this->minEntries !== null && $this->minEntries < 0) {
            $this->stderr("Error: Minimum entries must be 0 or greater.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Display summary and get confirmation
        if (!$this->confirmDeletion($targetSections, $deletePercent, $targetStatuses)) {
            $this->stdout("Operation cancelled.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        // Execute the chop operation
        try {
            $plugin->chopService->chopEntries($targetSections, $deletePercent, $this->minEntries, $targetStatuses);
            $this->stdout("Chop operation has been queued successfully.\n", Console::FG_GREEN);
            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Error: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Get the target sections based on the provided section handles
     */
    private function getTargetSections(): array
    {
        if ($this->sections) {
            $sectionHandles = array_map('trim', explode(',', $this->sections));
            $sections = [];

            foreach ($sectionHandles as $handle) {
                $section = Craft::$app->entries->getSectionByHandle($handle);
                if ($section) {
                    $sections[] = $section;
                } else {
                    $this->stderr("Warning: Section '{$handle}' not found.\n", Console::FG_YELLOW);
                }
            }

            return $sections;
        }

        // Return all sections if none specified
        return Craft::$app->entries->getAllSections();
    }

    /**
     * Get the target statuses based on the provided status names
     */
    private function getTargetStatuses(): ?array
    {
        if (!$this->statuses) {
            return null; // Return null to indicate all statuses should be included
        }

        $statusNames = array_map('trim', explode(',', $this->statuses));
        $validStatuses = [];
        $availableStatuses = ['live', 'pending', 'disabled', 'expired'];

        foreach ($statusNames as $status) {
            $status = strtolower($status);
            if (in_array($status, $availableStatuses)) {
                $validStatuses[] = $status;
            } else {
                $this->stderr("Warning: Status '{$status}' is not valid. Valid statuses are: " . implode(', ', $availableStatuses) . "\n", Console::FG_YELLOW);
            }
        }

        if (empty($validStatuses)) {
            $this->stderr("Error: No valid statuses provided.\n", Console::FG_RED);
            return [];
        }

        return $validStatuses;
    }

    /**
     * Display deletion summary and get user confirmation
     */
    private function confirmDeletion(array $sections, int $percent, ?array $targetStatuses = null): bool
    {
        if ($this->skipConfirm) {
            return true;
        }

        $plugin = Cleaver::getInstance();
        $settings = $plugin->getSettings();
        $minimumEntries = $this->minEntries ?? $settings->minimumEntries;

        $this->stdout("\nCleaver Deletion Summary:\n", Console::FG_CYAN);
        $this->stdout(str_repeat("=", 60) . "\n");

        foreach ($sections as $section) {
            $totalEntries = Entry::find()->sectionId($section->id)->count();
            $requestedDeletions = (int) ceil($totalEntries * ($percent / 100));
            $maxPossibleDeletions = max(0, $totalEntries - $minimumEntries);
            $actualDeletions = min($requestedDeletions, $maxPossibleDeletions);

            $this->stdout("Section '{$section->handle}': ");
            $this->stdout("{$actualDeletions} of {$totalEntries} entries");

            if ($actualDeletions < $requestedDeletions) {
                $this->stdout(" (limited by minimum: {$minimumEntries})", Console::FG_YELLOW);
            }

            $this->stdout("\n");
        }

        $this->stdout(str_repeat("=", 60) . "\n");
        $this->stdout("Cleaver is about to delete {$percent}% of entries from the selected sections.\n", Console::FG_YELLOW);
        $this->stdout("Minimum entries per section: {$minimumEntries}", Console::FG_CYAN);
        if ($this->minEntries !== null) {
            $this->stdout(" (overridden)", Console::FG_YELLOW);
        }
        $this->stdout("\n");

        if ($targetStatuses !== null) {
            $this->stdout("Target statuses: " . implode(', ', $targetStatuses), Console::FG_CYAN);
            $this->stdout(" (specified)", Console::FG_YELLOW);
        } else {
            $this->stdout("Target statuses: all statuses", Console::FG_CYAN);
        }
        $this->stdout("\n");

        return $this->confirm("Do you want to proceed?");
    }

    /**
     * Check if the current environment is allowed for Cleaver execution
     */
    private function checkEnvironmentLock($settings): bool
    {
        $currentEnv = Craft::$app->getConfig()->env ?: 'unknown';
        $allowedEnvironments = $settings->getAllowedEnvironmentsArray();

        // Add extra protection for production-like environments
        $productionLikeEnvs = ['production', 'prod', 'live'];

        // If current environment is not in allowed list
        if (!in_array($currentEnv, $allowedEnvironments, true)) {
            $this->stderr("\n" . str_repeat("!", 60) . "\n", Console::FG_RED);
            $this->stderr("ENVIRONMENT LOCK: Cleaver is not allowed to run in this environment!\n", Console::FG_RED);
            $this->stderr("Current environment: '{$currentEnv}'\n", Console::FG_RED);
            $this->stderr("Allowed environments: " . implode(', ', $allowedEnvironments) . "\n", Console::FG_RED);

            // Extra warning for production-like environments
            if (in_array($currentEnv, $productionLikeEnvs, true)) {
                $this->stderr("\nDANGER: This appears to be a PRODUCTION environment!\n", Console::FG_RED);
                $this->stderr("Running Cleaver in production could cause irreversible data loss!\n", Console::FG_RED);
            }

            $this->stderr(str_repeat("!", 60) . "\n\n", Console::FG_RED);
            $this->stderr("Configure allowed environments in plugin settings to enable Cleaver.\n", Console::FG_CYAN);

            return false;
        }

        return true;
    }
}
