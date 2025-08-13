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
     * The percentage of entries to delete
     */
    public ?int $percent = null;

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
                $options[] = 'percent';
                $options[] = 'skipConfirm';
                break;
        }
        return $options;
    }

    public function optionAliases(): array
    {
        return [
            's' => 'sections',
            'p' => 'percent',
            'y' => 'skipConfirm',
        ];
    }

    /**
     * Randomly delete entries from sections while preserving entry status distribution
     */
    public function actionIndex(): int
    {
        $plugin = Cleaver::getInstance();
        $settings = $plugin->getSettings();

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

        // Display summary and get confirmation
        if (!$this->confirmDeletion($targetSections, $deletePercent)) {
            $this->stdout("Operation cancelled.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        // Execute the chop operation
        try {
            $plugin->chopService->chopEntries($targetSections, $deletePercent);
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
     * Display deletion summary and get user confirmation
     */
    private function confirmDeletion(array $sections, int $percent): bool
    {
        if ($this->skipConfirm) {
            return true;
        }

        $this->stdout("\nCleaver Deletion Summary:\n", Console::FG_CYAN);
        $this->stdout(str_repeat("=", 50) . "\n");

        foreach ($sections as $section) {
            $totalEntries = Entry::find()->sectionId($section->id)->count();
            $entriesToDelete = (int) ceil($totalEntries * ($percent / 100));
            $this->stdout("Section '{$section->handle}': {$entriesToDelete} of {$totalEntries} entries ({$percent}%)\n");
        }

        $this->stdout(str_repeat("=", 50) . "\n");
        $this->stdout("Cleaver is about to delete {$percent}% of entries from the selected sections.\n", Console::FG_YELLOW);

        return $this->confirm("Do you want to proceed?");
    }
}
