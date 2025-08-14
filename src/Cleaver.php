<?php

namespace tallowandsons\cleaver;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Utilities;
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

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function() {
            // ...
        });
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
}
