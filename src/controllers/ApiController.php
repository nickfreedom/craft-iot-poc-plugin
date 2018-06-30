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

 require __DIR__ . '/../../vendor/autoload.php';

use nickleguillou\craftiotpoc\CraftIotPoc;

use Craft;
use craft\web\Controller;
use craft\helpers\Json;
use craft\elements\Entry;

use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

use Pusher\Pusher;

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

    /**
     * @var mixed
     * 
     * @access public 
     */
    public $requestJson;


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

    /**
     * @return mixed
     */
    public function actionProvision()
    {
        $this->requirePostRequest();
        
        $raw = Craft::$app->getRequest()->getRawBody();
        $this->requestJson = Json::decodeIfJson($raw);

        $this->requireAllowedDevice();

        $section = Craft::$app->sections->getSectionByHandle('devices');
        $entryTypes = $section->getEntryTypes();
        $entryType = reset($entryTypes);

        $entry = new Entry([
            'sectionId' => $section->id,
            'typeId' => $entryType->id,
            'fieldLayoutId' => $entryType->fieldLayoutId,
            'authorId' => 1,
            'title' => $this->requestJson['alias'],
        ]);    

        $fieldValues = [
            'key' => uniqid(),
            'serialNumber' => $this->requestJson['serialNumber']
        ];

        $entry->setFieldValues($fieldValues);

        if(Craft::$app->elements->saveElement($entry)) {
            return $this->asJson(['deviceKey' => $entry->getFieldValue('key')]);
        } else {
            throw new \Exception("Couldn't save new device: " . print_r($entry->getErrors(), true)); 
        }
    }

    public function actionRecord()
    {
        $this->requirePostRequest();

        $raw = Craft::$app->getRequest()->getRawBody();
        $this->requestJson = Json::decodeIfJson($raw);

        $key = $this->requestJson['key'];

        $device = Entry::find()
            ->section('devices')
            ->limit(1)
            ->key($key)
            ->one();

        if (!$device) {
            throw new \Exception("Couldn't find device with key: " . print_r($key, true)); 
        }

        $section = Craft::$app->sections->getSectionByHandle('timeseries');
        $entryTypes = $section->getEntryTypes();
        $entryType = reset($entryTypes);

        $entries = [];

        foreach ($this->requestJson['records'] as $record) {
            $entry = new Entry([
                'sectionId' => $section->id,
                'typeId' => $entryType->id,
                'fieldLayoutId' => $entryType->fieldLayoutId,
                'authorId' => 1,
            ]);    

            $fieldValues = [
                'device' => [ $device->id ],
                'signalName' => $record['signal'],
                'signalValue' => $record['value']
            ];

            $entry->setFieldValues($fieldValues);

            if(!Craft::$app->elements->saveElement($entry)) {
                throw new \Exception("Couldn't save new time series: " . print_r($entry->getErrors(), true)); 
            }

            $options = array(
                'cluster' => 'us2',
                'encrypted' => true
            );
            
            $pusher = new Pusher(
                '9e129f0beb6fd9dbe0d9',
                'bc1e7e8b2c1143dc9cb4',
                '549019',
                $options
            );
            
            $data = [
                'timestamp' => $entry->postDate->format('U'),
                'device' => $entry->getFieldValue('device')->one()->getFieldValue('key'),
                'signal' => $entry->getFieldValue('signalName'),
                'value' => $entry->getFieldValue('signalValue')
            ];


            $pusher->trigger('my-channel', 'my-event', $data);

            $entries[] = $data;
        }

        $device->setFieldValues(['lastRecording' => $raw]);

        if(!Craft::$app->elements->saveElement($device)) {
            throw new \Exception("Couldn't update device: " . print_r($device->getErrors(), true)); 
        }
        
        return $this->asJson($entries);
    }

    /**
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     */
    public function requireAllowedDevice()
    {
        if (!array_key_exists('provisionProfile', $this->requestJson)) {
            throw new BadRequestHttpException('Provision profile reference was not provided.');
        }

        if (!array_key_exists('serialNumber', $this->requestJson)) {
            throw new BadRequestHttpException('Serial number was not provided.');
        }

        $serialNumber = $this->requestJson['serialNumber'];
        $whitelist = Craft::$app->entries->getEntryById($this->requestJson['provisionProfile']);

        if (!$whitelist) {
            throw new BadRequestHttpException('Could not find a provision profile.');
        }

        $whitelistRules = $whitelist->getFieldValue('whitelistRules')->all();

        foreach ($whitelistRules as $rule) {
            $rulePattern = $rule->rule;

            switch ($rule->getType()->handle) {
                case 'exactMatch':
                    if ($rulePattern == $serialNumber) {
                        return;
                    }
                    break;
                case 'prefix':
                    $prefixLength = strlen($rulePattern);

                    if (substr($serialNumber, 0, $prefixLength) === $rulePattern) {
                        return;
                    }
                    break;
                default:
            }
        }

        throw new ForbiddenHttpException('This device has not been whitelisted for provisioning.');
    }
}
