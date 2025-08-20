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
        foreach (Craft::$app->entries->getAllSections() as $section) {
            $sections[] = [
                'label' => $section->name,
                'value' => $section->handle,
            ];
        }

        // Entry status options
        $statusOptions = [
            ['label' => 'Live', 'value' => 'live'],
            ['label' => 'Disabled', 'value' => 'disabled'],
            ['label' => 'Draft', 'value' => 'draft'],
            ['label' => 'Pending', 'value' => 'pending'],
        ];

        // Current environment and allowance
        $environment = Cleaver::getCurrentEnvironment();
        $envLower = strtolower($environment);
        $allowed = $settings->getAllowedEnvironmentsArray();
        $allowedLower = array_map('strtolower', $allowed);
        $isAllowedEnv = in_array($envLower, $allowedLower, true);
        $allowedListLower = implode(',', $allowedLower);

        return Craft::$app->view->renderTemplate('cleaver/_utility.twig', [
            'sections' => $sections,
            'statusOptions' => $statusOptions,
            'settings' => $settings,
            'environment' => $environment,
            'envLower' => $envLower,
            'isAllowedEnv' => $isAllowedEnv,
            'allowedListLower' => $allowedListLower,
        ]);
    }
}
