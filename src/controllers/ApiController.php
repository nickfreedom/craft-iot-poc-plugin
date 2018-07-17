<?php
/**
 * Craft IoT PoC plugin for Craft CMS 3.x
 *
 * Companion Plugin for Craft IoT PoC Presentation.
 *
 * @copyright Copyright (c) 2018 Nick Le Guillou
 */

 namespace nickleguillou\craftiotpoc\controllers;

 require __DIR__ . '/../../vendor/autoload.php';

use nickleguillou\craftiotpoc\CraftIotPoc;

use Craft;
use craft\web\Controller;
use craft\helpers\Json;
use craft\elements\Entry;
use craft\elements\User;

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
    const ERROR_INVALID_CREDENTIALS = 'Invalid credentials.';

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['record', 'provision', 'control', 'poll', 'get-api-key-for-user'];

    /**
     * @var mixed
     * 
     * @access public 
     */
    public $requestJson;


    // Public Methods
    // =========================================================================
    

    /**
     * Request a user's API Key from their Craft user account.
     * 
     * Request params:
     * - username (required): Craft username or email
     * - password (required): Craft password
     * 
     * @throws BadRequestHttpException
     * @return mixed
     */
    public function actionGetApiKeyForUser() {
        $username = Craft::$app->getRequest()->getQueryParam('username');
        $password = Craft::$app->getRequest()->getQueryParam('password');

        if (!$username || !$password) {
            throw new BadRequestHttpException(self::ERROR_INVALID_CREDENTIALS);
        }

        $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($username);

        if (!$user) {
            throw new BadRequestHttpException(self::ERROR_INVALID_CREDENTIALS);
        }

        if (!$user->authenticate($password)) {
            throw new BadRequestHttpException(self::ERROR_INVALID_CREDENTIALS);
        }
        
        return $this->asJson(['apiKey' => $user->getFieldValue('key')]);
    }

    /**
     * Provision a device to start sending data to Craft.
     * 
     * Request params:
     * - provisionProvile (required): The entry ID of the Provision Profile that will validate the seial number
     * - serialNumber (required): The device serial number that matches a Provision Profile rule.
     * - apiKey (required): The API token provided to the Craft user in their profile.
     * - alias (optional): A user-friendly display name for the device. Defaults to serialNumber if missing.
     * 
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * 
     * @return mixed
     */
    public function actionProvision()
    {
        $this->requirePostRequest();
        
        $raw = Craft::$app->getRequest()->getRawBody();
        $this->requestJson = Json::decodeIfJson($raw);

        $provisionProfile = $this->requireAllowedDevice();

        $serialNumber = $this->requestJson['serialNumber'];

        $section = Craft::$app->sections->getSectionByHandle('devices');
        $entryTypes = $section->getEntryTypes();
        $entryType = reset($entryTypes);

        $entry = new Entry([
            'sectionId' => $section->id,
            'typeId' => $entryType->id,
            'fieldLayoutId' => $entryType->fieldLayoutId,
            'authorId' => $provisionProfile->authorId,
            'title' => isset($this->requestJson['alias'])
                ? $this->requestJson['alias']
                : $serialNumber,
        ]);    

        $fieldValues = [
            'key' => uniqid(),
            'serialNumber' => $serialNumber,
            'provisionProfile' => [ $provisionProfile->id ]
        ];

        $entry->setFieldValues($fieldValues);

        if(!Craft::$app->elements->saveElement($entry)) {
            throw new \Exception("Couldn't save new device: " . print_r($entry->getErrors(), true)); 
        }


        /** TODO: MOVE THIS INTO ANOTHER PLUGIN */
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
            'title' => $entry->title,
            'detailUrl' => "/device/{$entry->id}",
            'serialNumber' => $entry->getFieldValue('serialNumber'),
            'key' => $entry->getFieldValue('key'),
            'lastUpdate' => $entry->dateUpdated->format('Y-m-d H:i:s')
        ];

        $pusher->trigger("device_{$this->requestJson['apiKey']}", 'provision', $data);
        /** END TODO */



        return $this->asJson(['deviceKey' => $entry->getFieldValue('key')]);
    }

    /**
     * Record a signal from a device
     *
     * Request params:
     * - apiKey (required): The API token provided to the Craft user in their profile.
     * - deviceKey (required): The UUID that was generated for the device when it was provisioned.
     * - records (required): A JSON-formatted list of signals to record as Timeseries entries.
     *      [{ signal: 'mySignalName1', value: 'theValue1' }, { signal: 'mySignalName2', value: 'theValue2' }, { ... }]
     * 
     * @throws Exception
     * @throws BadRequestHttpException
     * 
     * @return mixed
     */
    public function actionRecord()
    {
        $this->requirePostRequest();

        $raw = Craft::$app->getRequest()->getRawBody();
        $this->requestJson = Json::decodeIfJson($raw);

        if (!array_key_exists('apiKey', $this->requestJson)) {
            throw new BadRequestHttpException('API key was not provided.');
        }

        $user = User::Find()
            ->key($this->requestJson['apiKey'])
            ->one();

        if (!$user) {
            throw new BadRequestHttpException('Invalid credentials provided.');
        }

        $deviceKey = $this->requestJson['deviceKey'];
        $device = $this->getDevice($deviceKey, $user->id);

        $section = Craft::$app->sections->getSectionByHandle('timeseries');
        $entryTypes = $section->getEntryTypes();
        $entryType = reset($entryTypes);

        $entries = [];

        foreach ($this->requestJson['records'] as $record) {
            $entry = new Entry([
                'sectionId' => $section->id,
                'typeId' => $entryType->id,
                'fieldLayoutId' => $entryType->fieldLayoutId,
                'authorId' => $user->id,
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

            $entries[] = [
                'timestamp' => $entry->postDate->format('U'),
                'device' => $entry->getFieldValue('device')->one()->getFieldValue('key'),
                'signal' => $entry->getFieldValue('signalName'),
                'value' => $entry->getFieldValue('signalValue')
            ];
        }

        $device->setFieldValues(['lastRecording' => $raw]);

        if(!Craft::$app->elements->saveElement($device)) {
            throw new \Exception("Couldn't update device: " . print_r($device->getErrors(), true)); 
        }

        /** TODO: Move this to its own Plugin */
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
            'title' => $device->title,
            'lastUpdate' => $device->dateUpdated->format('Y-m-d H:i:s'),
            'serialNumber' => $device->getFieldValue('serialNumber'),
            'key' => $device->getFieldValue('key'),
            'lastRecording' => $device->getFieldValue('lastRecording')
        ];

        $pusher->trigger("device_{$this->requestJson['apiKey']}", 'record', $data);
        /** end TODO */

        return $this->asJson($entries);
    }

    /**
     * Send a remote control request to a device
     * - apiKey (required): The API token provided to the Craft user in their profile.
     * - deviceKey (required): The UUID that was generated for the device when it was provisioned.
     * - commands (required): A JSON-formatted list of commands to execute on the device.
     *      [{ command: 'myCommand', params: ... }, { command: 'myCommand2', params: ... }]
     * 
     * @return mixed
     */
    public function actionControl()
    {
        $raw = Craft::$app->getRequest()->getRawBody();
        $this->requestJson = Json::decodeIfJson($raw);

        if (!array_key_exists('apiKey', $this->requestJson)) {
            throw new BadRequestHttpException('API key was not provided.');
        }

        $user = User::Find()
            ->key($this->requestJson['apiKey'])
            ->one();

        if (!$user) {
            throw new BadRequestHttpException('Invalid credentials provided.');
        }

        $deviceKey = $this->requestJson['deviceKey'];

        $device = $this->getDevice($deviceKey, $user->id);

        $device->setFieldValues(['lastRemoteControl' => $raw]);

        if(!Craft::$app->elements->saveElement($device)) {
            throw new \Exception("Couldn't update device: " . print_r($device->getErrors(), true)); 
        }
        
        return $raw;
    }

    /**
     * Get a device's last remote control request. Called by devices to act on requests sent by actionControl()
     * 
     * Request params:
     * - apiKey (required): The API token provided to the Craft user in their profile.
     * - device (required): The UUID that was generated for the device when it was provisioned.
     * 
     * @throws Exception
     * @throws BadRequestHttpException
     * 
     * @return void
     */
    public function actionPoll()
    {
        $apiKey = Craft::$app->getRequest()->getQueryParam('apiKey');

        if (!$apiKey) {
            throw new BadRequestHttpException('API key was not provided.');
        }

        $user = User::Find()
            ->key($apiKey)
            ->one();

        if (!$user) {
            throw new BadRequestHttpException(self::ERROR_INVALID_CREDENTIALS);
        }

        $deviceKey = Craft::$app->getRequest()->getQueryParam('device');

        if (!$deviceKey) {
            throw new BadRequestHttpException('Device not specified.');
        }

        $device = $this->getDevice($deviceKey, $user->id);

        $control = $device->getFieldValue('lastRemoteControl');
        $device->setFieldValues(['lastRemoteControl' => '']);

        if(!Craft::$app->elements->saveElement($device)) {
            throw new \Exception("Couldn't clear last control request from device: " . print_r($device->getErrors(), true)); 
        }

        if ($control == '') {
            return $this->asJson([ 'key' => $deviceKey, 'commands' => [] ]);
        }

        return $control;
    }

    /**
     * Determines whether the current device request should be allowed.
     * 
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

        if (!array_key_exists('apiKey', $this->requestJson)) {
            throw new BadRequestHttpException('API key was not provided.');
        }

        $user = User::Find()
            ->key($this->requestJson['apiKey'])
            ->one();

        if (!$user) {
            throw new BadRequestHttpException('Invalid credentials provided.');
        }

        $serialNumber = $this->requestJson['serialNumber'];
        $whitelist = Entry::find()
            ->section('provisionProfiles')
            ->id($this->requestJson['provisionProfile'])
            ->authorId($user->id)
            ->one();

        if (!$whitelist) {
            throw new BadRequestHttpException('Could not find a provision profile that matches the credentials provided.');
        }

        $whitelistRules = $whitelist->getFieldValue('whitelistRules')->all();

        foreach ($whitelistRules as $rule) {
            $rulePattern = $rule->rule;

            switch ($rule->getType()->handle) {
                case 'exactMatch':
                    if ($rulePattern == $serialNumber) {
                        return $whitelist;
                    }
                    break;
                case 'prefix':
                    $prefixLength = strlen($rulePattern);

                    if (substr($serialNumber, 0, $prefixLength) === $rulePattern) {
                        return $whitelist;
                    }
                    break;
                default:
            }
        }

        throw new ForbiddenHttpException('This device has not been whitelisted for provisioning.');
    }

    /**
     * Fetch a Device entry while ensuring it's owned by the requester.
     * 
     * @param string $deviceKey the UUID that was generated for the device when it was provisioned.
     * @param string $userId the Craft ID for the user.
     * 
     * @throws BadRequestHttpException
     * 
     * @return Entry
     */
    public function getDevice($deviceKey, $userId)
    {
        $device = Entry::find()
            ->section('devices')
            ->limit(1)
            ->key($deviceKey)
            ->authorId($userId)
            ->one();

        if (!$device) {
            throw new BadRequestHttpException('Could not find a devices that matches the credentials provided.'); 
        }

        return $device;
    }
}
