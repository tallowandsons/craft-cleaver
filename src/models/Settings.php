<?php

namespace tallowandsons\cleaver\models;

use Craft;
use craft\base\Model;

/**
 * cleaver settings
 */
class Settings extends Model
{
    /**
     * Delete mode constants
     */
    public const DELETE_MODE_SOFT = 'soft';
    public const DELETE_MODE_HARD = 'hard';

    /**
     * Log level constants
     */
    public const LOG_LEVEL_NONE = 'none';
    public const LOG_LEVEL_INFO = 'info';
    public const LOG_LEVEL_VERBOSE = 'verbose';

    /**
     * The default percentage of entries to delete when no percentage is specified
     */
    public int $defaultPercent = 90;

    /**
     * The minimum number of entries to keep per section (regardless of percentage)
     */
    public int $minimumEntries = 1;

    /**
     * Default entry statuses to include when none are specified
     * e.g. ['live', 'disabled']
     */
    public array $defaultStatuses = ['live'];

    /**
     * Environments where Cleaver is allowed to run
     */
    public array $allowedEnvironments = ['dev', 'staging', 'local'];

    /**
     * The batch size for processing entries in queue jobs
     */
    public int $batchSize = 50;

    /**
     * The delete mode for entries (soft or hard delete)
     */
    public string $defaultDeleteMode = self::DELETE_MODE_SOFT;

    /**
     * The log level for Cleaver logging (none, info, or verbose)
     */
    public string $logLevel = self::LOG_LEVEL_INFO;

    /**
     * Whether to show the Cleaver Utility UI in the Control Panel
     */
    public bool $enableUtility = true;

    public function defineRules(): array
    {
        return [
            ['defaultPercent', 'integer', 'min' => 1, 'max' => 99],
            ['defaultPercent', 'default', 'value' => 90],
            ['minimumEntries', 'integer', 'min' => 0],
            ['minimumEntries', 'default', 'value' => 1],
            ['defaultStatuses', 'each', 'rule' => ['string']],
            ['defaultStatuses', 'default', 'value' => ['live']],
            ['allowedEnvironments', 'each', 'rule' => ['string']],
            ['allowedEnvironments', 'default', 'value' => ['dev', 'staging', 'local']],
            ['batchSize', 'integer', 'min' => 1, 'max' => 1000],
            ['batchSize', 'default', 'value' => 50],
            ['defaultDeleteMode', 'in', 'range' => [self::DELETE_MODE_SOFT, self::DELETE_MODE_HARD]],
            ['defaultDeleteMode', 'default', 'value' => self::DELETE_MODE_SOFT],
            ['logLevel', 'in', 'range' => [self::LOG_LEVEL_NONE, self::LOG_LEVEL_INFO, self::LOG_LEVEL_VERBOSE]],
            ['logLevel', 'default', 'value' => self::LOG_LEVEL_INFO],
            ['enableUtility', 'boolean'],
            ['enableUtility', 'default', 'value' => true],
        ];
    }

    /**
     * Get the allowed environments as an array
     */
    public function getAllowedEnvironmentsArray(): array
    {
        return $this->allowedEnvironments;
    }

    /**
     * Set allowed environments from a comma-separated string
     */
    public function setAllowedEnvironments($value): void
    {
        if (is_string($value)) {
            $this->allowedEnvironments = array_map('trim', array_filter(explode(',', $value)));
        } elseif (is_array($value)) {
            $this->allowedEnvironments = array_filter($value);
        }
    }

    /**
     * Set default statuses from a comma-separated string or array
     */
    public function setDefaultStatuses($value): void
    {
        if (is_string($value)) {
            $this->defaultStatuses = array_map('trim', array_filter(explode(',', $value)));
        } elseif (is_array($value)) {
            $this->defaultStatuses = array_filter($value);
        }
    }
}
