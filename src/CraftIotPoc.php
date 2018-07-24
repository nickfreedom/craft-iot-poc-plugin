<?php
/**
 * Craft IoT PoC plugin for Craft CMS 3.x
 *
 * Companion Plugin for Craft IoT PoC Presentation.
 *
 * @copyright Copyright (c) 2018 Nick Le Guillou
 */

namespace nickleguillou\craftiotpoc;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\services\Elements;
use craft\events\ElementEvent;
use craft\web\UrlManager;
use craft\events\RegisterUrlRulesEvent;
use craft\elements\Entry;
use craft\elements\User;

use yii\base\Event;

use nickleguillou\craftiotpoc\models\Settings;

/**
 * Class CraftIotPoc
 *
 * @author    Nick Le Guillou
 * @package   CraftIotPoc
 * @since     1.0.0
 *
 */
class CraftIotPoc extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var CraftIotPoc
     */
    public static $plugin;

    /**
     * @var mixed
     */
    public static $settings;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    // Protected Methods
    // =========================================================================
    protected function createSettingsModel()
    {
        return new Settings();
    }

    // Public Methods
    // =========================================================================



    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_SAVE_ELEMENT,
            function (ElementEvent $event) {
                if (!$event->element instanceof Entry && !$event->element instanceof User) {
                    return;
                }

                $key = uniqid();

                if ($event->element instanceof User) {
                    if (!$event->element->getFieldValue('key')) {
                        $event->element->setFieldValues(['key' => $key]);
                    }

                    return;
                }

                $section = Craft::$app->sections->getSectionByHandle('devices');

                if ($section->id != $event->element->sectionId) {
                    return;
                }

                if (!$event->element->getFieldValue('key')) {
                    $event->element->setFieldValues(['key' => $key]);
                }
            }
        );

        Craft::info(
            Craft::t(
                'craft-iot-poc',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

}
