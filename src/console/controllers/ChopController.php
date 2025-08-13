<?php

namespace tallowandsons\cleaver\console\controllers;

use Craft;
use craft\console\Controller;
use yii\console\ExitCode;

/**
 * Chop controller
 */
class ChopController extends Controller
{
    public $defaultAction = 'index';

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        switch ($actionID) {
            case 'index':
                // $options[] = '...';
                break;
        }
        return $options;
    }

    /**
     * cleaver/chop command
     */
    public function actionIndex(): int
    {
        // ...
        return ExitCode::OK;
    }
}
