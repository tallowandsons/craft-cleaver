<?php

/**
 * Cleaver config.php
 *
 * This file exists only as a template for the Cleaver settings.
 * It does nothing on its own.
 *
 * Don't edit this file directly. Instead, copy it to `craft/config` as `cleaver.php`
 * and make your changes there to override the default settings.
 *
 * Once copied to `craft/config`, this file will be multi-environment aware,
 * so you can have different settings groups per environment, just like `general.php`.
 *
 * @see tallowandsons\cleaver\models\Settings for all available options and documentation.
 */

use tallowandsons\cleaver\models\Settings;

return [
    // =========================================================================
    // DEFAULTS
    // =========================================================================

    // The default percentage of entries to delete when none is specified
    // Range: 1-100 (minimum entries per section always preserved)
    // 'defaultPercent' => 90,

    // The minimum number of entries to keep per section (regardless of percentage)
    // Set to 0 to allow complete deletion
    // 'minimumEntries' => 1,

    // Default entry statuses to include when none are specified
    // Valid: live, pending, expired, disabled
    // 'defaultStatuses' => ['live'],

    // Default section handles to include when none are specified
    // e.g. ['blog', 'news']
    // 'defaultSections' => [],

    // Default delete mode for entries (can be overridden per run)
    // Options: Settings::DELETE_MODE_SOFT, Settings::DELETE_MODE_HARD
    // 'defaultDeleteMode' => Settings::DELETE_MODE_SOFT,

    // =========================================================================
    // SAFETY
    // =========================================================================

    // Environments where Cleaver is allowed to run (case-insensitive match)
    // 'allowedEnvironments' => ['dev', 'staging', 'local'],

    // =========================================================================
    // PERFORMANCE
    // =========================================================================

    // The number of entries to process in each batch
    // 'batchSize' => 50,

    // =========================================================================
    // INTERFACE
    // =========================================================================

    // Show the Cleaver Utility UI in the Control Panel utilities menu
    // 'enableUtility' => true,

    // =========================================================================
    // LOGGING
    // =========================================================================

    // Log level for Cleaver logs: none, info, verbose
    // Options: Settings::LOG_LEVEL_NONE, Settings::LOG_LEVEL_INFO, Settings::LOG_LEVEL_VERBOSE
    // 'logLevel' => Settings::LOG_LEVEL_INFO,
];
