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
    // Constants
    // =========================================================================

    // Plugin Dependencies
    const PLUGIN_MANY_TO_MANY = 'manytomany';
    const PLUGIN_INCOGNITO_FIELD = 'incognito-field';

    // User Field Layout Tab
    const USER_LAYOUT_TAB_NAME_API = 'IoT API';

    // Sections
    const SECTION_NAME_DEVICES = 'IoT Devices';
    const SECTION_HANDLE_DEVICES = 'iotDevices';

    const SECTION_NAME_PROVISION_PROFILES = 'IoT Provision Profiles';
    const SECTION_HANDLE_PROVISION_PROFILES = 'iotProvisionProfiles';

    const SECTION_NAME_TIME_SERIES = 'IoT Time Series';
    const SECTION_HANDLE_TIME_SERIES = 'iotTimeSeries';

    // Field Groups
    const FIELD_GROUP_NAME = 'IoT';
    
    // Fields
    const FIELD_NAME_ALLOW_REMOTE_CONTROL = 'Allow Remote Control';
    const FIELD_HANDLE_ALLOW_REMOTE_CONTROL = 'iotAllowRemoteControl';

    const FIELD_NAME_DEVICE = 'Device';
    const FIELD_HANDLE_DEVICE = 'iotDevice';

    const FIELD_NAME_KEY = 'Key';
    const FIELD_HANDLE_KEY = 'iotKey';

    const FIELD_NAME_LAST_RECORDING = 'Last Recording';
    const FIELD_HANDLE_LAST_RECORDING = 'iotLastRecording';

    const FIELD_NAME_LAST_REMOTE_CONTROL = 'Last Remote Control';
    const FIELD_HANDLE_LAST_REMOTE_CONTROL = 'iotLastRemoteControl';

    const FIELD_NAME_PROVISION_PROFILE = 'Provision Profile';
    const FIELD_HANDLE_PROVISION_PROFILE = 'iotProvisionProfile';

    const FIELD_NAME_PROVISIONED_DEVICES = 'Provisioned Devices';
    const FIELD_HANDLE_PROVISONED_DEVICES = 'iotProvisionedDevices';

    const FIELD_NAME_SERIAL_NUMBER = 'Serial Number';
    const FIELD_HANDLE_SERIAL_NUMBER = 'iotSerialNumber';

    const FIELD_NAME_SIGNAL_NAME = 'Signal Name';
    const FIELD_HANDLE_SIGNAL_NAME = 'iotSignalName';

    const FIELD_NAME_SIGNAL_TRANSFORMS = 'Signal Transforms';
    const FIELD_HANDLE_SIGNAL_TRANSFORMS = 'iotSignalTransforms';

    const FIELD_NAME_SIGNAL_TYPES_AND_UNITS = 'Signal Types and Units';
    const FIELD_HANDLE_SIGNAL_TYPES_AND_UNITS = 'iotSignalTypesAndUnits';

    const FIELD_NAME_SIGNAL_VALUE = 'Signal Value';
    const FIELD_HANDLE_SIGNAL_VALUE = 'iotSignalValue';

    const FIELD_NAME_WHITELIST_RULES = 'Whitelist Rules';
    const FIELD_HANDLE_WHITELIST_RULES = 'iotWhitelistRules';

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
