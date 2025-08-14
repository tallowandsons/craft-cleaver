<?php

namespace tallowandsons\cleaver;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\log\Dispatcher;
use craft\log\MonologTarget;
use craft\services\Utilities;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;
use tallowandsons\cleaver\models\Settings;
use tallowandsons\cleaver\services\ChopService;
use tallowandsons\cleaver\utilities\CleaverUtility;
use yii\base\Event;

/**
 * cleaver plugin
 *
 * @method static Cleaver getInstance()
 * @method Settings getSettings()
 * @author tallowandsons <support@tallowandsons.com>
 * @copyright tallowandsons
 * @license https://craftcms.github.io/license/ Craft License
 * @property-read ChopService $chopService
 */
class Cleaver extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => ['chopService' => ChopService::class],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->attachEventHandlers();
        $this->registerLogTarget();

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function () {});
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('cleaver/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function attachEventHandlers(): void
    {
        // Register event handlers here ...
        // (see https://craftcms.com/docs/5.x/extend/events.html to get started)
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITIES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = CleaverUtility::class;
        });
    }


    // ===================
    // ===== LOGGING =====
    // ===================

    /**
     * Registers a custom log target for Cleaver.
     */
    private function registerLogTarget(): void
    {
        if (Craft::getLogger()->dispatcher instanceof Dispatcher) {
            Craft::getLogger()->dispatcher->targets['cleaver'] = new MonologTarget([
                'name' => 'cleaver',
                'categories' => ['cleaver', 'cleaver.*'],
                'level' => LogLevel::DEBUG, // Changed to DEBUG to allow all messages through
                'logContext' => false,
                'allowLineBreaks' => false,
                'formatter' => new LineFormatter(
                    format: "[%datetime%] [%level_name%] [%extra.yii_category%] %message%\n",
                    dateFormat: 'Y-m-d H:i:s',
                ),
            ]);
        }
    }

    public static function log(string $message, ?string $category = null, string $logLevel = LogLevel::INFO): void
    {
        $settings = self::getInstance()->getSettings();

        // Don't log if log level is set to none
        if ($settings->logLevel === Settings::LOG_LEVEL_NONE) {
            return;
        }

        // Only log debug messages if log level is set to verbose
        if ($logLevel === LogLevel::DEBUG && $settings->logLevel !== Settings::LOG_LEVEL_VERBOSE) {
            return;
        }

        // Prefix category with plugin handle for namespacing if not already namespaced
        if (!str_starts_with($category, 'cleaver')) {
            $category = 'cleaver.' . ltrim($category, '.');
        }

        Craft::getLogger()->log($message, $logLevel, $category);
    }

    public static function debug(string $message, ?string $category = null): void
    {
        self::log($message, $category, LogLevel::DEBUG);
    }
}
