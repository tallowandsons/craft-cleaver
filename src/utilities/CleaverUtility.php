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
        return 'cut';
    }

    static function contentHtml(): string
    {
        $plugin = Cleaver::getInstance();
        $settings = $plugin->getSettings();

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

        // Current environment
        $environment = Cleaver::getCurrentEnvironment();

        return Craft::$app->view->renderTemplate('cleaver/_utility.twig', [
            'sections' => $sections,
            'statusOptions' => $statusOptions,
            'settings' => $settings,
            'environment' => $environment,
        ]);
    }
}
