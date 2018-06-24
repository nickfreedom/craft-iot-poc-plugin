<?php
/**
 * Craft IoT PoC plugin for Craft CMS 3.x
 *
 * Companion Plugin for Craft IoT PoC Presentation.
 *
 * @link      https://github.com/nickfreedom
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

use yii\base\Event;

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

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

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
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['siteActionTrigger1'] = 'craft-iot-poc/api';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['cpActionTrigger1'] = 'craft-iot-poc/api/do-something';
            }
        );

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
        );

        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_SAVE_ELEMENT,
            function (ElementEvent $event) {
                $section = Craft::$app->sections->getSectionByHandle('devices');

                if ($section->id != $event->element->sectionId) {
                    return;
                }

                if (!$event->element->getFieldValue('key')) {
                    $event->element->setFieldValues(['key' => uniqid()]);
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
