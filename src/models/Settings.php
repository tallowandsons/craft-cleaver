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

    public function defineRules(): array
    {
        return [
            ['defaultPercent', 'integer', 'min' => 1, 'max' => 99],
            ['defaultPercent', 'default', 'value' => 90],
        ];
    }
}
