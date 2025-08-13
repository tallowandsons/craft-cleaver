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
     * The default percentage of entries to delete when no percentage is specified
     */
    public int $defaultPercent = 90;

    /**
     * The minimum number of entries to keep per section (regardless of percentage)
     */
    public int $minimumEntries = 1;

    /**
     * Environments where Cleaver is allowed to run
     */
    public array $allowedEnvironments = ['dev', 'staging', 'local'];

    public function defineRules(): array
    {
        return [
            ['defaultPercent', 'integer', 'min' => 1, 'max' => 99],
            ['defaultPercent', 'default', 'value' => 90],
            ['minimumEntries', 'integer', 'min' => 0],
            ['minimumEntries', 'default', 'value' => 1],
            ['allowedEnvironments', 'each', 'rule' => ['string']],
            ['allowedEnvironments', 'default', 'value' => ['dev', 'staging', 'local']],
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
}
