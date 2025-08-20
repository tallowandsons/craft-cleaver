<?php

namespace tallowandsons\cleaver\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use craft\web\Response;
use tallowandsons\cleaver\Cleaver;
use tallowandsons\cleaver\models\ChopConfig;
use yii\web\BadRequestHttpException;

/**
 * Utility controller for Cleaver web interface
 */
class UtilityController extends Controller
{
    /**
     * Execute the chop operation via web interface
     */
    public function actionChop(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $request = Craft::$app->getRequest();

        // Validate environment confirmation (case-insensitive compare)
        $environment = Cleaver::getCurrentEnvironment();
        $confirmEnvironment = $request->getBodyParam('confirmEnvironment');

        if (strcasecmp($confirmEnvironment ?? '', $environment) !== 0) {
            return $this->asJson([
                'success' => false,
                'message' => 'Environment confirmation does not match. Please type the exact environment name.',
            ]);
        }

        // Build chop configuration from form data
        $config = $this->buildChopConfigFromRequest($request);

        // Enforce allowed environments from settings (server-side safety)
        $allowed = array_map('strtolower', Cleaver::getInstance()->getSettings()->getAllowedEnvironmentsArray());
        if (!in_array(strtolower($environment), $allowed, true)) {
            return $this->asJson([
                'success' => false,
                'message' => 'Cleaver is disabled in this environment (' . $environment . '). Allowed: ' . implode(', ', $allowed),
            ]);
        }

        // Validate configuration
        if (!$config->validate()) {
            return $this->asJson([
                'success' => false,
                'message' => 'Invalid configuration: ' . implode(', ', $config->getErrorSummary(true)),
            ]);
        }

        try {
            // Execute the chop operation
            Cleaver::getInstance()->chopService->planChop($config->setSource('web'));

            $message = $config->dryRun
                ? 'Dry run completed successfully. Check logs for details.'
                : 'Chop operation completed successfully. Check logs for details.';

            return $this->asJson([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            Cleaver::log('Error during web chop operation: ' . $e->getMessage(), 'error');
            return $this->asJson([
                'success' => false,
                'message' => 'Error during chop operation: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Build ChopConfig from web request
     */
    private function buildChopConfigFromRequest($request): ChopConfig
    {
        $config = ChopConfig::fromDefaults();

        $config->sectionHandles = $request->getBodyParam('sections', []);
        $config->percent = (int) $request->getBodyParam('percent', $config->percent);
        $config->statuses = $request->getBodyParam('statuses', []);
        $config->dryRun = (bool) $request->getBodyParam('dryRun', false);
        $config->softDelete = (bool) $request->getBodyParam('softDelete', true);
        $config->verbose = (bool) $request->getBodyParam('verbose', false);

        return $config;
    }
}
