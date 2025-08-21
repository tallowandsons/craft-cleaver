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
     * Default section handles to include when none are specified
     * e.g. ['blog', 'news']
     */
    public array $defaultSections = [];

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
            ['defaultPercent', 'integer', 'min' => 1, 'max' => 100],
            ['defaultPercent', 'default', 'value' => 90],
            ['minimumEntries', 'integer', 'min' => 0],
            ['minimumEntries', 'default', 'value' => 1],
            ['defaultStatuses', 'each', 'rule' => ['in', 'range' => ['live', 'pending', 'expired', 'disabled']]],
            ['defaultStatuses', 'default', 'value' => ['live']],
            ['allowedEnvironments', 'each', 'rule' => ['string']],
            ['allowedEnvironments', 'default', 'value' => ['dev', 'staging', 'local']],
            ['defaultSections', 'each', 'rule' => ['string']],
            ['defaultSections', 'default', 'value' => []],
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
     * Normalized default sections as an array.
     * - 'all' (string, any case) => [] (meaning no filter / all)
     * - CSV string => array of trimmed handles
     * - array => filtered array
     */
    public function getDefaultSectionsArray(): array
    {
        $value = $this->defaultSections;
        if (is_string($value)) {
            if (strcasecmp(trim($value), 'all') === 0) {
                return [];
            }
            return array_map('trim', array_filter(explode(',', $value)));
        }
        if (is_array($value)) {
            $lower = array_map(fn($v) => is_string($v) ? strtolower(trim($v)) : $v, $value);
            if (in_array('all', $lower, true)) {
                return [];
            }
            return array_values(array_filter($value));
        }
        return [];
    }

    /**
     * Normalized default statuses as an array.
     * - 'all' (string, any case) => [] (meaning no filter / all)
     * - CSV string => array of trimmed statuses
     * - array => filtered array
     */
    public function getDefaultStatusesArray(): array
    {
        $value = $this->defaultStatuses;
        if (is_string($value)) {
            if (strcasecmp(trim($value), 'all') === 0) {
                return [];
            }
            return array_map('trim', array_filter(explode(',', $value)));
        }
        if (is_array($value)) {
            $lower = array_map(fn($v) => is_string($v) ? strtolower(trim($v)) : $v, $value);
            if (in_array('all', $lower, true)) {
                return [];
            }
            return array_values(array_filter($value));
        }
        return [];
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
            if (strcasecmp(trim($value), 'all') === 0) {
                // Empty array means all statuses (no filter)
                $this->defaultStatuses = [];
                return;
            }
            $this->defaultStatuses = array_map('trim', array_filter(explode(',', $value)));
        } elseif (is_array($value)) {
            $lower = array_map(fn($v) => is_string($v) ? strtolower(trim($v)) : $v, $value);
            if (in_array('all', $lower, true)) {
                $this->defaultStatuses = [];
                return;
            }
            $this->defaultStatuses = array_values(array_filter($value));
        }
    }

    /**
     * Set default sections from a comma-separated string or array
     */
    public function setDefaultSections($value): void
    {
        if (is_string($value)) {
            if (strcasecmp(trim($value), 'all') === 0) {
                // Empty array means all sections (no filter)
                $this->defaultSections = [];
                return;
            }
            $this->defaultSections = array_map('trim', array_filter(explode(',', $value)));
        } elseif (is_array($value)) {
            $lower = array_map(fn($v) => is_string($v) ? strtolower(trim($v)) : $v, $value);
            if (in_array('all', $lower, true)) {
                $this->defaultSections = [];
                return;
            }
            $this->defaultSections = array_values(array_filter($value));
        }
    }
}
