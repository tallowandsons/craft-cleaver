<?php

namespace tallowandsons\cleaver\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use tallowandsons\cleaver\Cleaver;
use tallowandsons\cleaver\models\ChopConfig;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Chop controller
 */
class ChopController extends Controller
{
    public $defaultAction = 'entries';

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

    /**
     * Dry run mode - log what would be deleted without actually deleting
     */
    public bool $dryRun = false;

    /**
     * Verbose output - show detailed information
     */
    public bool $verbose = false;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        switch ($actionID) {
            case 'entries':
                $options[] = 'sections';
                $options[] = 'statuses';
                $options[] = 'percent';
                $options[] = 'minEntries';
                $options[] = 'skipConfirm';
                $options[] = 'dryRun';
                $options[] = 'verbose';
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
            'd' => 'dryRun',
            'v' => 'verbose',
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
    public function actionEntries(): int
    {
        $plugin = Cleaver::getInstance();
        $settings = $plugin->getSettings();

        // Check environment lock
        if (!$this->checkEnvironmentLock($settings)) {
            Cleaver::log("Environment lock check failed", 'cli');
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Create ChopConfig from defaults and CLI options
        $config = ChopConfig::fromDefaults()->setSource('cli');
        $this->populateConfigFromCliOptions($config);

        Cleaver::log("Starting chop operation via CLI", 'cli');
        Cleaver::debug("CLI configuration: " . $config->getSummary(), 'cli');

        if ($config->dryRun) {
            $this->stdout("DRY RUN MODE: No entries will actually be deleted.\n", Console::FG_CYAN);
            Cleaver::log("Operating in DRY RUN mode", 'cli');
        }

        // Validate configuration
        if (!$config->validate()) {
            foreach ($config->getErrors() as $attribute => $errors) {
                foreach ($errors as $error) {
                    $this->stderr("Error in {$attribute}: {$error}\n", Console::FG_RED);
                }
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Display summary and get confirmation
        if (!$this->confirmDeletion($config)) {
            $this->stdout("Operation cancelled.\n", Console::FG_YELLOW);
            Cleaver::log("CLI operation cancelled by user", 'cli');
            return ExitCode::OK;
        }

        // Execute the chop operation
        try {
            $plugin->chopService->planChop($config);
            $operationType = $config->dryRun ? 'dry-run' : 'chop';
            $this->stdout("Chop operation has been queued successfully" . ($config->dryRun ? ' (DRY RUN)' : '') . ".\n", Console::FG_GREEN);
            Cleaver::log("CLI {$operationType} operation queued successfully", 'cli');
            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Error: " . $e->getMessage() . "\n", Console::FG_RED);
            Cleaver::log("CLI chop operation failed: " . $e->getMessage(), 'cli');
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Populate ChopConfig from CLI options
     */
    private function populateConfigFromCliOptions(ChopConfig $config): void
    {
        if ($this->sections !== null) {
            $config->sectionHandles = array_map('trim', explode(',', $this->sections));
        }

        if ($this->percent !== null) {
            $config->percent = $this->percent;
        }

        if ($this->statuses !== null) {
            $config->statuses = array_map('trim', explode(',', $this->statuses));
        }

        if ($this->minEntries !== null) {
            $config->minimumEntries = $this->minEntries;
        }

        $config->dryRun = $this->dryRun;
        $config->verbose = $this->verbose;
    }

    /**
     * Display deletion summary and get user confirmation
     */
    private function confirmDeletion(ChopConfig $config): bool
    {
        if ($this->skipConfirm) {
            return true;
        }

        // Get sections from config (same logic as service)
        $sections = $this->getSectionsFromConfig($config);

        if (empty($sections)) {
            $this->stderr("Error: No valid sections found.\n", Console::FG_RED);
            Cleaver::log("No valid sections found for CLI operation", 'cli');
            return false;
        }

        $this->stdout("\nCleaver Deletion Summary:\n", Console::FG_CYAN);
        $this->stdout(str_repeat("=", 60) . "\n");

        foreach ($sections as $section) {
            $totalEntries = Entry::find()->sectionId($section->id)->site('*')->anyStatus()->count();
            $requestedDeletions = (int) ceil($totalEntries * ($config->percent / 100));
            $maxPossibleDeletions = max(0, $totalEntries - $config->minimumEntries);
            $actualDeletions = min($requestedDeletions, $maxPossibleDeletions);

            $this->stdout("Section '{$section->handle}': ");
            $this->stdout("{$actualDeletions} of {$totalEntries} entries");

            if ($actualDeletions < $requestedDeletions) {
                $this->stdout(" (limited by minimum: {$config->minimumEntries})", Console::FG_YELLOW);
            }

            $this->stdout("\n");
        }

        $this->stdout(str_repeat("=", 60) . "\n");
        $this->stdout("Cleaver is about to delete {$config->percent}% of entries from the selected sections.\n", Console::FG_YELLOW);
        $this->stdout("Minimum entries per section: {$config->minimumEntries}", Console::FG_CYAN);
        $this->stdout("\n");

        if (!empty($config->statuses)) {
            $this->stdout("Target statuses: " . implode(', ', $config->statuses), Console::FG_CYAN);
            $this->stdout("\n");
        }

        $deleteMode = $config->softDelete ? 'SOFT DELETE' : 'HARD DELETE';
        $this->stdout("Delete mode: {$deleteMode}", Console::FG_CYAN);
        $this->stdout("\n");

        if ($config->dryRun) {
            $this->stdout("DRY RUN: No entries will actually be deleted", Console::FG_CYAN);
            $this->stdout("\n");
        }

        $this->stdout("\nConfiguration: " . $config->getSummary(), Console::FG_CYAN);
        $this->stdout("\n\n");

        return $this->confirm('Are you sure you want to proceed?');
    }

    /**
     * Get Section objects from ChopConfig section handles
     */
    private function getSectionsFromConfig(ChopConfig $config): array
    {
        if (empty($config->sectionHandles)) {
            // Return all sections if none specified
            return Craft::$app->entries->getAllSections();
        }

        $sections = [];
        foreach ($config->sectionHandles as $handle) {
            $section = Craft::$app->entries->getSectionByHandle($handle);
            if ($section) {
                $sections[] = $section;
            } else {
                $this->stderr("Warning: Section '{$handle}' not found.\n", Console::FG_YELLOW);
            }
        }

        return $sections;
    }

    /**
     * Check if the current environment is allowed for Cleaver execution
     */
    private function checkEnvironmentLock($settings): bool
    {
        $currentEnv = Cleaver::getCurrentEnvironment();
        $allowedEnvironments = $settings->getAllowedEnvironmentsArray();
        $envLower = strtolower($currentEnv);
        $allowedLower = array_map('strtolower', $allowedEnvironments);

        Cleaver::debug("Environment check - current: '{$currentEnv}', allowed: " . implode(', ', $allowedEnvironments), 'cli');

        // Add extra protection for production-like environments
        $productionLikeEnvs = ['production', 'prod', 'live'];

        // If current environment is not in allowed list (case-insensitive)
        if (!in_array($envLower, $allowedLower, true)) {
            $this->stderr("\n" . str_repeat("!", 60) . "\n", Console::FG_RED);
            $this->stderr("ENVIRONMENT LOCK: Cleaver is not allowed to run in this environment!\n", Console::FG_RED);
            $this->stderr("Current environment: '{$currentEnv}'\n", Console::FG_RED);
            $this->stderr("Allowed environments: " . implode(', ', $allowedEnvironments) . "\n", Console::FG_RED);

            // Extra warning for production-like environments
            if (in_array($envLower, $productionLikeEnvs, true)) {
                $this->stderr("\nDANGER: This appears to be a PRODUCTION environment!\n", Console::FG_RED);
                $this->stderr("Running Cleaver in production could cause irreversible data loss!\n", Console::FG_RED);
                Cleaver::log("CRITICAL: Attempt to run Cleaver in production-like environment '{$currentEnv}'", 'cli');
            }

            $this->stderr(str_repeat("!", 60) . "\n\n", Console::FG_RED);
            $this->stderr("Configure allowed environments in plugin settings to enable Cleaver.\n", Console::FG_CYAN);

            return false;
        }

        Cleaver::debug("Environment check passed for '{$currentEnv}'", 'cli');
        return true;
    }
}
