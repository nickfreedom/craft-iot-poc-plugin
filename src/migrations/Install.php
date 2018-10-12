<?php

namespace nickleguillou\craftiotpoc\migrations;

use Craft;
use craft\db\Migration;
use nickleguillou\craftiotpoc\CraftIoTPoc;

/**
 * Install migration.
 */
class Install extends Migration
{
    public $fieldGroup = null;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->fieldGroup = $this->getGroupByName(CraftIoTPoC::FIELD_GROUP_NAME);
    }

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Plugin Dependencies
        // =========================================================================
        Craft::$app->plugins->init();

        // @TODO Is Composer Install required first?
        Craft::$app->plugins->installPlugin(CrafTIotPoC::PLUGIN_MANY_TO_MANY);
        Craft::$app->plugins->installPlugin(CrafTIotPoC::PLUGIN_INCOGNITO_FIELD);

        // Sections
        // =========================================================================
        $this->createSection([
            'name' => CraftIotPoc::SECTION_NAME_DEVICES,
            'handle' => CraftIotPoc::SECTION_HANDLE_DEVICES,
            'type' => \craft\models\Section::TYPE_CHANNEL
        ]);

        $this->createSection([
            'name' => CraftIotPoc::SECTION_NAME_PROVISION_PROFILES,
            'handle' => CraftIotPoc::SECTION_HANDLE_PROVISION_PROFILES,
            'type' => \craft\models\Section::TYPE_CHANNEL
        ]);

        $this->createSection([
            'name' => CraftIotPoc::SECTION_NAME_TIME_SERIES,
            'handle' => CraftIotPoc::SECTION_HANDLE_TIME_SERIES,
            'type' => \craft\models\Section::TYPE_CHANNEL
        ]);

        // Add Field Group
        // =========================================================================
        if (is_null($this->fieldGroup)) {
            $this->fieldGroup = new \craft\models\FieldGroup();
            $this->fieldGroup->name = CraftIoTPoC::FIELD_GROUP_NAME;
            Craft::$app->getFields()->saveGroup($this->fieldGroup);
        }

        // Fields
        // =========================================================================
        
        // Serial Number
        $this->createPlainTextField([
            'handle' => CraftIotPoc::FIELD_HANDLE_SERIAL_NUMBER,
            'name' => CraftIotPoc::FIELD_NAME_SERIAL_NUMBER
        ]);
        
        // Signal Name
        $this->createPlainTextField([
            'handle' => CraftIotPoc::FIELD_HANDLE_SIGNAL_NAME,
            'name' => CraftIotPoc::FIELD_NAME_SIGNAL_NAME
        ]);
        
        // Signal Value
        $this->createPlainTextField([
            'handle' => CraftIotPoc::FIELD_HANDLE_SIGNAL_VALUE,
            'name' => CraftIotPoc::FIELD_NAME_SIGNAL_VALUE,
            'useMonospacedFont' => true,
            'multiline' => true,
            'initialRows' => 4
        ]);
        
        // Key
        $this->createIncognitoField([
            'handle' => CraftIotPoc::FIELD_HANDLE_KEY,
            'name' => CraftIotPoc::FIELD_NAME_KEY,
            'mode' => 'readonly',
            'placeholder' => 'This will be auto-populated upon saving.',
            'useMonospacedFont' => true,
        ]);
        
        // Last Recording
        $this->createIncognitoField([
            'handle' => CraftIotPoc::FIELD_HANDLE_LAST_RECORDING,
            'name' => CraftIotPoc::FIELD_NAME_LAST_RECORDING,
            'mode' => 'readonly',
            'placeholder' => 'This will be auto-populated when this device reports data.',
            'useMonospacedFont' => true,
            'multiline' => true,
            'initialRows' => 4
        ]);
        
        // Last Remote Control
        $this->createIncognitoField([
            'handle' => CraftIotPoc::FIELD_HANDLE_LAST_REMOTE_CONTROL,
            'name' => CraftIotPoc::FIELD_NAME_LAST_REMOTE_CONTROL,
            'mode' => 'readonly',
            'placeholder' => 'This will get populated whenever a remote control request is made.',
            'useMonospacedFont' => true,
            'multiline' => true,
            'initialRows' => 4
        ]);
        
        // Allow Remote Control
        $this->createLightswitchField([
            'handle' => CraftIotPoc::FIELD_HANDLE_ALLOW_REMOTE_CONTROL,
            'name' => CraftIotPoc::FIELD_NAME_ALLOW_REMOTE_CONTROL
        ]);

        // Signal Transforms
        $this->createMatrixField([
            'handle' => CraftIotPoc::FIELD_HANDLE_SIGNAL_TRANSFORMS,
            'name' => CraftIotPoc::FIELD_NAME_SIGNAL_TRANSFORMS,
            'blockTypes' => [
                [

                    'blockName' => 'Round',
                    'blockHandle' => 'iotRound',
                    'fields' => [
                        new \craft\fields\PlainText([
                            'name' => 'Target Signal',
                            'handle' => 'iotTargetSignal',
                            'required' => true,
                        ]),
                        new \craft\fields\Number([
                            'name' => 'Precision',
                            'handle' => 'iotPrecision',
                            'required' => true,
                            'defaultValue' => 0,
                            'min' => 0,
                            'decimals' => 0
                        ]),
                    ]
                ]
            ]
        ]);

        // Signal Types and Units
        $this->createMatrixField([
            'handle' => CraftIotPoc::FIELD_HANDLE_SIGNAL_TYPES_AND_UNITS,
            'name' => CraftIotPoc::FIELD_NAME_SIGNAL_TYPES_AND_UNITS,
            'blockTypes' => [
                [
                    'blockName' => 'Temperature',
                    'blockHandle' => 'iotTemperature',
                    'fields' => [
                        new \craft\fields\PlainText([
                            'name' => 'Target Signal',
                            'handle' => 'iotTargetSignal',
                            'required' => true,
                        ]),
                        new \craft\fields\Dropdown([
                            'name' => 'Base Unit',
                            'handle' => 'iotBaseUnit',
                            'required' => true,
                            'options' => [
                                [
                                    'label' => '°C',
                                    'value' => 'celcius',
                                    'default' => true
                                ],
                                [
                                    'label' => '°F',
                                    'value' => 'fahrenheit',
                                ]
                            ]
                        ]),
                        new \craft\fields\Dropdown([
                            'name' => 'Convert To',
                            'handle' => 'iotConvertTo',
                            'required' => true,
                            'options' => [
                                [
                                    'label' => '(none)',
                                    'value' => '',
                                ],
                                [
                                    'label' => '°C',
                                    'value' => 'celcius'
                                ],
                                [
                                    'label' => '°F',
                                    'value' => 'fahrenheit',
                                ]
                            ]
                        ]),
                    ]
                ],
                [
                    'blockName' => 'Pressure',
                    'blockHandle' => 'iotPressure',
                    'fields' => [
                        new \craft\fields\PlainText([
                            'name' => 'Target Signal',
                            'handle' => 'iotTargetSignal',
                            'required' => true,
                        ]),
                        new \craft\fields\Dropdown([
                            'name' => 'Base Unit',
                            'handle' => 'iotBaseUnit',
                            'required' => true,
                            'options' => [
                                [
                                    'label' => 'mb',
                                    'value' => 'millibars',
                                    'default' => true
                                ],
                                [
                                    'label' => 'inHg',
                                    'value' => 'inchesOfMercury',
                                ],
                            ]
                        ]),
                        new \craft\fields\Dropdown([
                            'name' => 'Convert To',
                            'handle' => 'iotConvertTo',
                            'options' => [
                                [
                                    'label' => '(none)',
                                    'value' => '',
                                    'default' => true
                                ],
                                [
                                    'label' => 'mb',
                                    'value' => 'millibars',
                                ],
                                [
                                    'label' => 'inHg',
                                    'value' => 'inchesOfMercury',
                                ],
                            ]
                        ]),
                    ]
                ],
                [
                    'blockName' => 'Humidity',
                    'blockHandle' => 'iotHumidity',
                    'fields' => [
                        new \craft\fields\PlainText([
                            'name' => 'Target Signal',
                            'handle' => 'iotTargetSignal',
                            'required' => true,
                        ]),
                        new \craft\fields\Dropdown([
                            'name' => 'Base Unit',
                            'handle' => 'iotBaseUnit',
                            'required' => true,
                            'options' => [
                                [
                                    'label' => '%',
                                    'value' => 'percent',
                                    'default' => true
                                ],
                            ]
                        ]),
                        new \craft\fields\Dropdown([
                            'name' => 'Convert To',
                            'handle' => 'iotConvertTo',
                            'options' => [
                                [
                                    'label' => '(none)',
                                    'value' => '',
                                    'default' => true
                                ]
                            ]
                        ]),
                    ]
                ]
            ]
        ]);

        // Whitelist Rules
        $this->createMatrixField([
            'handle' => CraftIotPoc::FIELD_HANDLE_WHITELIST_RULES,
            'name' => CraftIotPoc::FIELD_NAME_WHITELIST_RULES,
            'blockTypes' => [
                [
                    'blockName' => 'Prefix',
                    'blockHandle' => 'iotPrefix',
                    'fields' => [
                        new \craft\fields\PlainText([
                            'name' => 'Rule',
                            'handle' => 'iotRule',
                            'required' => true,
                        ]),
                    ]
                ],
                [
                    'blockName' => 'Exact Match',
                    'blockHandle' => 'iotExactMatch',
                    'fields' => [
                        new \craft\fields\PlainText([
                            'name' => 'Rule',
                            'handle' => 'iotRule',
                            'required' => true,
                        ]),
                    ]
                ],
            ]
        ]);

        // Device field
        $section = Craft::$app->getSections()->getSectionByHandle(CraftIotPoc::SECTION_HANDLE_DEVICES);

        $this->createEntriesField([
            'handle' => CraftIotPoc::FIELD_HANDLE_DEVICE,
            'name' => CraftIotPoc::FIELD_NAME_DEVICE,
            'sources' => [ "section:$section->id" ],
            'limit' => 1
        ]);

        // Provision Profile field
        $section = Craft::$app->getSections()->getSectionByHandle(CraftIotPoc::SECTION_HANDLE_PROVISION_PROFILES);

        $this->createEntriesField([
            'handle' => CraftIotPoc::FIELD_HANDLE_PROVISION_PROFILE,
            'name' => CraftIotPoc::FIELD_NAME_PROVISION_PROFILE,
            'sources' => [ "section:$section->id" ],
            'limit' => 1
        ]);

        // Provisioned Devices field
        $section = Craft::$app->getSections()->getSectionByHandle(CraftIotPoc::SECTION_HANDLE_DEVICES);
        $field = Craft::$app->getFields()->getFieldByHandle(CraftIotPoc::FIELD_HANDLE_PROVISION_PROFILE);

        $this->createManyToManyField([
            'handle' => CraftIotPoc::FIELD_HANDLE_PROVISONED_DEVICES,
            'name' => CraftIotPoc::FIELD_NAME_PROVISIONED_DEVICES,
            'instructions' => 'These devices have been provisioned using one of the defined rules in this entry.',
            'source' => $section->id,
            'singleField' => $field->id
        ]);

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->deleteSection(CraftIoTPoc::SECTION_HANDLE_DEVICES);
        $this->deleteSection(CraftIoTPoc::SECTION_HANDLE_PROVISION_PROFILES);
        $this->deleteSection(CraftIoTPoc::SECTION_HANDLE_TIME_SERIES);
        
        if (!is_null($this->fieldGroup)) {
            Craft::$app->getFields()->deleteGroup($this->fieldGroup);
        }
    }

    /**
     * Fetches a group by its name
     * 
     * @param string $groupName
     * @returns FieldGroup|null
     */
    public function getGroupByName($groupName)
    {
        $groups = Craft::$app->getFields()->getAllGroups();
        foreach ($groups as $group) {
            if ($group->name == $groupName) {
                return $group;
            }
        }

        return null;
    }

    /**
     * Create a PlainText field
     * @param array $settings The field settings (see field settings in Control Panel for details)
     *      - handle
     *      - name
     *      - useMonospacedFont
     *      - multiline
     *      - initialRows
     */
    public function createPlainTextField($settings)
    {
        $handle = $settings['handle'];
        $name = $settings['name'];
        $useMonospacedFont = isset($settings['useMonospacedFont']) ? $settings['useMonospacedFont'] : '';
        $multiline = isset($settings['multiline']) ? $settings['multiline'] : '';
        $initialRows = isset($settings['initialRows']) ? $settings['initialRows'] : '';

        if (!is_null(Craft::$app->getFields()->getFieldByHandle($handle))) {
             // TODO: Add log to indicate the field was skipped.
            return;
        }

        $new = Craft::$app->getFields()->createField([
            'type' => 'craft\\fields\\PlainText',
            'groupId' => $this->fieldGroup->id,
            'name' => $name,
            'handle' => $handle,
            'settings' => [
                'code' => $useMonospacedFont,
                'multiline' => $multiline,
                'initialRows' => $initialRows,
            ]
        ]);

        Craft::$app->getFields()->saveField($new);
    }

    /**
     * Create an Incognito field
     * @param array $settings The field settings (see field settings in Control Panel for details)
     *      - handle
     *      - name
     *      - mode
     *      - placeholder
     *      - useMonospacedFont
     *      - multiline
     *      - initialRows
     */
    public function createIncognitoField($settings)
    {
        $handle = $settings['handle'];
        $name = $settings['name'];
        $mode = isset($settings['mode']) ? $settings['mode'] : '';
        $placeholder = isset($settings['placeholder']) ? $settings['placeholder'] : '';
        $useMonospacedFont = isset($settings['useMonospacedFont']) ? $settings['useMonospacedFont'] : '';
        $multiline = isset($settings['multiline']) ? $settings['multiline'] : '';
        $initialRows = isset($settings['initialRows']) ? $settings['initialRows'] : '';

        if (!is_null(Craft::$app->getFields()->getFieldByHandle($handle))) {
            // TODO: Add log to indicate the field was skipped.
            return;
        }

        $new = Craft::$app->getFields()->createField([
            'type' => 'mmikkel\\incognitofield\\fields\\IncognitoFieldType',
            'groupId' => $this->fieldGroup->id,
            'name' => $name,
            'handle' => $handle,
            'settings' => [
                'mode' => $mode,
                'placeholder' => $placeholder,
                'code' => $useMonospacedFont,
                'multiline' => $multiline,
                'initialRows' => $initialRows,
            ]
        ]);

        Craft::$app->getFields()->saveField($new);
    }

    /**
     * Create a Lightswitch field
     * @param array $settings The field settings (see field settings in Control Panel for details)
     *      - handle
     *      - name
     */
    public function createLightswitchField($settings)
    {
        $handle = $settings['handle'];
        $name = $settings['name'];

        if (!is_null(Craft::$app->getFields()->getFieldByHandle($handle))) {
            // TODO: Add log to indicate the field was skipped.
            return;
        }

        $new = Craft::$app->getFields()->createField([
            'type' => 'craft\\fields\\Lightswitch',
            'groupId' => $this->fieldGroup->id,
            'name' => $name,
            'handle' => $handle,
            'settings' => []
        ]);

        Craft::$app->getFields()->saveField($new);
    }

    /**
     * Create a Matrix field
     * @param array $settings The field settings (see field settings in Control Panel for details)
     *      - handle
     *      - name
     *      - fields
     */
    public function createMatrixField($settings)
    {
        $handle = $settings['handle'];
        $name = $settings['name'];

        if (!is_null(Craft::$app->getFields()->getFieldByHandle($handle))) {
            // TODO: Add log to indicate the field was skipped.
            return;
        }

        $blockTypes = [];

        if (isset($settings['blockTypes']) && count($settings['blockTypes']) > 0) {
            foreach ($settings['blockTypes'] as $blockType) {
                $blockTypes[] = new \craft\models\MatrixBlockType([
                    'name' => $blockType['blockName'],
                    'handle' => $blockType['blockHandle'],
                    'fields' => $blockType['fields']
                ]);
            }
        }

        $new = new \craft\fields\Matrix([
            'groupId' => $this->fieldGroup->id,
            'name' => $name,
            'handle' => $handle,
            'blockTypes' => $blockTypes
        ]);
        Craft::$app->getFields()->saveField($new);
    }

    /**
     * Create an Entries field
     * @param array $settings The field settings (see field settings in Control Panel for details)
     *      - handle
     *      - name
     *      - sources
     *      - limit
     */
    public function createEntriesField($settings) {
        $handle = $settings['handle'];
        $name = $settings['name'];
        $sources = $settings['sources'];
        $limit = $settings['limit'];

        if (!is_null(Craft::$app->getFields()->getFieldByHandle($handle))) {
            // TODO: Add log to indicate the field was skipped.
            return;
        }

        $new = Craft::$app->getFields()->createField([
            'type' => 'craft\\fields\\Entries',
            'groupId' => $this->fieldGroup->id,
            'name' => $name,
            'handle' => $handle,
            'settings' => [
                'sources' => $sources,
                'limit' => $limit,
            ]
        ]);
        Craft::$app->getFields()->saveField($new);
    }

    /**
     * Create a Many to Many field
     * @param array $settings The field settings (see field settings in Control Panel for details)
     *      - handle
     *      - name
     *      - instructions
     *      - source
     *      - singleField
     */
    public function createManyToManyField($settings) {
        $handle = $settings['handle'];
        $name = $settings['name'];
        $instructions = isset($settings['instructions']) ? $settings['instructions']: '';
        $source = $settings['source'];
        $singleField = $settings['singleField'];

        if (!is_null(Craft::$app->getFields()->getFieldByHandle($handle))) {
            // TODO: Add log to indicate the field was skipped.
            return;
        }

        $new = Craft::$app->getFields()->createField([
            'type' => 'Page8\\ManyToMany\\fields\\ManyToManyField',
            'groupId' => $this->fieldGroup->id,
            'name' => $name,
            'handle' => $handle,
            'instructions' => $instructions,
            'settings' => [
                'source' => [
                    'type' => 'section',
                    'value' => $source,
                ],
                'singleField' => $singleField
            ]
        ]);

        Craft::$app->getFields()->saveField($new);
    }

    /**
     * Create a section
     * @param array $settings The section settings (see section settings in Control Panel for details)
     *      - handle
     *      - name
     *      - type
     */
    public function createSection($settings)
    {
        $handle = $settings['handle'];
        $name = $settings['name'];
        $type = $settings['type'];

        if (!is_null(Craft::$app->getSections()->getSectionByHandle($handle))) {
            // TODO: Add log to indicate the section was skipped.
            return;
        }

        $new = new \craft\models\Section([
            'name' => $name,
            'handle' => $handle,
            'type' => $type,
            'siteSettings' => [
                new \craft\models\Section_SiteSettings([
                    'siteId' => Craft::$app->sites->getPrimarySite()->id,
                    'enabledByDefault' => true,
                    'hasUrls' => true,
                    'uriFormat' => "$handle/{slug}",
                    'template' => "$handle/_entry"
                ])
            ]
        ]);

        Craft::$app->getSections()->saveSection($new, true);

        Craft::error(var_export($new->getErrors(), true));
        
    }

    /**
     * Delete a section
     * @param string $handle
     */
    public function deleteSection($handle) {
        $section = Craft::$app->getSections()->getSectionByHandle($handle);

        if (is_null($section)) {
            // TODO: Add log to indicate the section was skipped.
            return;
        }

        Craft::$app->getSections()->deleteSection($section);
    }
}