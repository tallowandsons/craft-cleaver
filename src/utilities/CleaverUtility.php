<?php

namespace tallowandsons\cleaver\utilities;

use Craft;
use craft\base\Utility;
use craft\helpers\App;
use tallowandsons\cleaver\Cleaver;

/**
 * Cleaver Utility utility
 */
class CleaverUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('cleaver', 'Cleaver');
    }

    static function id(): string
    {
        return 'cleaver-utility';
    }

    public static function icon(): ?string
    {
        $iconPath = Craft::getAlias('@tallowandsons/cleaver/icon-mask.svg');

        if (!is_string($iconPath)) {
            return null;
        }

        return $iconPath;
    }

    static function contentHtml(): string
    {
        $plugin = Cleaver::getInstance();
        $settings = $plugin->getSettings();

        // If utility is disabled, return empty string
        if (!$settings->enableUtility) {
            return '';
        }

        // Get all sections for the dropdown
        $sections = [];
        $allSectionHandles = [];
        foreach (Craft::$app->entries->getAllSections() as $section) {
            $sections[] = [
                'label' => $section->name,
                'value' => $section->handle,
            ];
            $allSectionHandles[] = $section->handle;
        }

        // Entry status options (valid Craft entry statuses)
        $statusOptions = [
            ['label' => 'Live', 'value' => 'live'],
            ['label' => 'Pending', 'value' => 'pending'],
            ['label' => 'Expired', 'value' => 'expired'],
            ['label' => 'Disabled', 'value' => 'disabled'],
        ];

        $allStatusValues = array_map(fn($o) => $o['value'], $statusOptions);

        // Normalize defaults and preselect "all" when empty
        $defaultsSections = method_exists($settings, 'getDefaultSectionsArray') ? $settings->getDefaultSectionsArray() : (array) $settings->defaultSections;
        $defaultsStatuses = method_exists($settings, 'getDefaultStatusesArray') ? $settings->getDefaultStatusesArray() : (array) $settings->defaultStatuses;

        $selectedSections = !empty($defaultsSections) ? $defaultsSections : $allSectionHandles;
        $selectedStatuses = !empty($defaultsStatuses) ? $defaultsStatuses : $allStatusValues;

        // Current environment and allowance
        $environment = Cleaver::getCurrentEnvironment();
        $envLower = strtolower($environment);
        $allowedLower = array_map('strtolower', $settings->getAllowedEnvironmentsArray());
        $isAllowedEnv = Cleaver::isEnvironmentAllowed($environment);
        $allowedListLower = implode(',', $allowedLower);

        return Craft::$app->view->renderTemplate('cleaver/_utility.twig', [
            'sections' => $sections,
            'statusOptions' => $statusOptions,
            'settings' => $settings,
            'selectedSections' => $selectedSections,
            'selectedStatuses' => $selectedStatuses,
            'environment' => $environment,
            'envLower' => $envLower,
            'isAllowedEnv' => $isAllowedEnv,
            'allowedListLower' => $allowedListLower,
        ]);
    }
}
