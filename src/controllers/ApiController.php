<?php
/**
 * Craft IoT PoC plugin for Craft CMS 3.x
 *
 * Companion Plugin for Craft IoT PoC Presentation.
 *
 * @link      https://github.com/nickfreedom
 * @copyright Copyright (c) 2018 Nick Le Guillou
 */

namespace nickleguillou\craftiotpoc\controllers;

use nickleguillou\craftiotpoc\CraftIotPoc;

use Craft;
use craft\web\Controller;
use craft\helpers\Json;
use craft\elements\Entry;

/**
 * @author    Nick Le Guillou
 * @package   CraftIotPoc
 * @since     1.0.0
 */
class ApiController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['index', 'do-something', 'record', 'provision'];

    // Public Methods
    // =========================================================================

    /**
     * @return mixed
     */
    public function actionIndex()
    {
        $result = 'Welcome to the ApiController actionIndex() method';

        return $result;
    }

    /**
     * @return mixed
     */
    public function actionDoSomething()
    {
        $result = 'Welcome to the ApiController actionDoSomething() method';

        return $result;
    }

    public function actionProvision()
    {
        $this->requirePostRequest();
        
        $raw = Craft::$app->getRequest()->getRawBody();
        $json = Json::decodeIfJson($raw);

        $section = Craft::$app->sections->getSectionByHandle('devices');
        $entryTypes = $section->getEntryTypes();
        $entryType = reset($entryTypes);

        $entry = new Entry([
            'sectionId' => $section->id,
            'typeId' => $entryType->id,
            'fieldLayoutId' => $entryType->fieldLayoutId,
            'authorId' => 1,
            'title' => $json['alias'],
        ]);    

        $fieldValues = [
            'key' => uniqid(),
            'serialNumber' => $json['serialNumber']
        ];

        $entry->setFieldValues($fieldValues);

        if(Craft::$app->elements->saveElement($entry)) {
            return $this->asJson($entry);
        } else {
            throw new \Exception("Couldn't save new device: " . print_r($entry->getErrors(), true)); 
        }
    }
}
