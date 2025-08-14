<?php

namespace tallowandsons\cleaver\utilities;

use Craft;
use craft\base\Utility;

/**
 * Cleaver Utility utility
 */
class CleaverUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('cleaver', 'Cleaver Utility');
    }

    static function id(): string
    {
        return 'cleaver-utility';
    }

    public static function icon(): ?string
    {
        return 'wrench';
    }

    static function contentHtml(): string
    {
        // todo: replace with custom content HTML
        return '';
    }
}
