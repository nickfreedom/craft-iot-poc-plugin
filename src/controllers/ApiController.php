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
use craft\helpers\ArrayHelper;

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
     * @var Pusher
     * 
     * @access protected
     */
    protected $pusher;

    /**
     * @var boolean
     * 
     * @access public
     */
    public $enableCsrfValidation = false;

    /**
     * @var array
     * 
     * @access protected
     */
    protected $pusherMappings;

    /**
     * @var array The defined settings for the CraftIoTPoC plugin
     * 
     * @access public
     */
    public $pluginSettings;
    
    /**
     * @var mixed
     * 
     * @access public 
     */
    public $requestJson;


    // Public Methods
    // =========================================================================
    
    /**
     * @return void
     */
    public function init()
    {
        $this->pluginSettings = CraftIoTPoc::getInstance()->getSettings();

        if (!$this->pluginSettings->pusherEnabled) {
            return;
        }

        $this->pusher = new Pusher(
            $this->pluginSettings->pusherSettings['key'],
            $this->pluginSettings->pusherSettings['secret'],
            $this->pluginSettings->pusherSettings['appId'],
            $this->pluginSettings->pusherSettings['options']
        );

        $this->pusherMappings = $this->pluginSettings->pusherMappings;
    }

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
        try {
            $user = $this->getUserFromRequestParams();
        } catch (\Exception $e) {
            return $this->returnErrorJson($e);
        }
        
        $key = $user->getFieldValue(CraftIotPoc::FIELD_HANDLE_KEY);
        
        if (!$key) {
            $key = CraftIotPoc::generateApiKey();
            $user->setFieldValue(CraftIotPoc::FIELD_HANDLE_KEY, $key);
            Craft::$app->getElements()->saveElement($user);
        }

        return $this->asJson(['apiKey' => $user->getFieldValue(CraftIotPoc::FIELD_HANDLE_KEY)]);
    }

    /**
     * Provision a device to start sending data to Craft.
     * 
     * @uses $_POST['provisionProvile'] (required): The entry ID of the Provision Profile that will validate the seial number
     * @uses $_POST['serialNumber'] (required): The device serial number that matches a Provision Profile rule.
     * @uses $_POST['apiKey'] (required): The API token provided to the Craft user in their profile.
     * @uses $_POST['alias'] (optional): A user-friendly display name for the device. Defaults to $_POST['serialNumber'] if missing.
     * 
     * @return mixed
     */
    public function actionProvision()
    {
        try {
            $this->requirePostRequest();
        } catch (\Exception $e) {
            return $this->returnErrorJson($e);
        }

        $raw = Craft::$app->getRequest()->getRawBody();
        $this->requestJson = Json::decodeIfJson($raw);

        try {
            $provisionProfile = $this->requireAllowedDevice();
        } catch (\Exception $e) {
            return $this->returnErrorJson($e);
        }

        $serialNumber = $this->requestJson['serialNumber'];

        $section = Craft::$app->sections->getSectionByHandle(CraftIotPoc::SECTION_HANDLE_DEVICES);
        
        $entryTypes = $section->getEntryTypes();
        
        $$entryType = ArrayHelper::firstValue(ArrayHelper::filterByValue(
            $entryTypes,
            'handle',
            CraftIotPoc::SECTION_HANDLE_DEVICES
        ));

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
            CraftIotPoc::FIELD_HANDLE_KEY => uniqid(),
            CraftIotPoc::FIELD_HANDLE_SERIAL_NUMBER => $serialNumber,
            CraftIotPoc::FIELD_HANDLE_PROVISION_PROFILE => [ $provisionProfile->id ]
        ];

        $entry->setFieldValues($fieldValues);

        if(!Craft::$app->elements->saveElement($entry)) {
            throw new \Exception("Couldn't save new device: " . print_r($entry->getErrors(), true)); 
        }

        $key = $entry->getFieldValue(CraftIotPoc::FIELD_HANDLE_KEY);

        if ($this->pluginSettings->pusherEnabled) {
            $data = [
                'title' => $entry->title,
                'detailUrl' => "/" . CraftIotPoc::SECTION_HANDLE_DEVICES . "/{$entry->id}",
                'serialNumber' => $serialNumber,
                'key' => $key,
                'lastUpdate' => $entry->dateUpdated->format('Y-m-d H:i:s')
            ];
    
            $this->publish('device', 'Provision', $data);
        }

        return $this->asJson(['deviceKey' => $key]);
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

        $section = Craft::$app->sections->getSectionByHandle(CraftIotPoc::SECTION_HANDLE_TIME_SERIES);
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
                CraftIotPoc::FIELD_HANDLE_DEVICE => [ $device->id ],
                CraftIotPoc::FIELD_HANDLE_SIGNAL_NAME => $record['signal'],
                CraftIotPoc::FIELD_HANDLE_SIGNAL_VALUE => $record['value']
            ];

            $entry->setFieldValues($fieldValues);

            if(!Craft::$app->elements->saveElement($entry)) {
                throw new \Exception("Couldn't save new time series: " . print_r($entry->getErrors(), true)); 
            }

            $entries[] = [
                'timestamp' => $entry->postDate->format('U'),
                'device' => $entry->getFieldValue(CraftIotPoc::FIELD_HANDLE_DEVICE)->one()->getFieldValue(CraftIotPoc::FIELD_HANDLE_KEY),
                'signal' => $entry->getFieldValue(CraftIotPoc::FIELD_HANDLE_SIGNAL_NAME),
                'value' => $entry->getFieldValue(CraftIotPoc::FIELD_HANDLE_SIGNAL_VALUE)
            ];
        }

        $device->setFieldValues([CraftIotPoc::FIELD_HANDLE_LAST_RECORDING => $raw]);

        if(!Craft::$app->elements->saveElement($device)) {
            throw new \Exception("Couldn't update device: " . print_r($device->getErrors(), true)); 
        }

        if ($this->pluginSettings->pusherEnabled) {
            $data = [
                'title' => $device->title,
                'lastUpdate' => $device->dateUpdated->format('Y-m-d H:i:s'),
                'serialNumber' => $device->getFieldValue(CraftIotPoc::FIELD_HANDLE_SERIAL_NUMBER),
                'key' => $device->getFieldValue(CraftIotPoc::FIELD_HANDLE_KEY),
                'lastRecording' => $device->getFieldValue(CraftIotPoc::FIELD_HANDLE_LAST_RECORDING)
            ];

            $this->publish('device', 'Record', $data);
        }

        return $this->asJson($entries);
    }

    /**
     * Send a remote control request to a device
     * - apiKey (required): The API token provided to the Craft user in their profile.
     * - deviceKey (required): The UUID that was generated for the device when it was provisioned.
     * - commands (required): A JSON-formatted list of commands to execute on the device.
     *      [{ command: 'myCommand', params: ... }, { command: 'myCommand2', params: ... }]
     * 
     * @throws Exception
     * @throws BadRequestHttpException
     * 
     * @return mixed
     */
    public function actionControl()
    {
        $raw = Craft::$app->getRequest()->getRawBody();
        $this->requestJson = Json::decodeIfJson($raw);

        // Used to handle OPTIONS requests
        if (!$this->requestJson) {
            return $raw;
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

        $deviceKey = $this->requestJson['deviceKey'];

        $device = $this->getDevice($deviceKey, $user->id);

        $device->setFieldValues([CraftIotPoc::FIELD_HANDLE_LAST_REMOTE_CONTROL => $raw]);

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

        $control = $device->getFieldValue(CraftIotPoc::FIELD_HANDLE_LAST_REMOTE_CONTROL);
        $device->setFieldValues([CraftIotPoc::FIELD_HANDLE_LAST_REMOTE_CONTROL => '']);

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
            throw new BadRequestHttpException('Provision profile    .');
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
            throw new BadRequestHttpException(self::ERROR_INVALID_CREDENTIALS);
        }

        $serialNumber = $this->requestJson['serialNumber'];
        $whitelist = Entry::find()
            ->section(CraftIotPoc::SECTION_HANDLE_PROVISION_PROFILES)
            ->id($this->requestJson['provisionProfile'])
            ->authorId($user->id)
            ->one();

        if (!$whitelist) {
            throw new BadRequestHttpException('Could not find a provision profile that matches the credentials provided.');
        }

        $whitelistRules = $whitelist->getFieldValue(CraftIotPoc::FIELD_HANDLE_WHITELIST_RULES)->all();

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
            ->section(CraftIotPoc::SECTION_HANDLE_DEVICES)
            ->limit(1)
            ->key($deviceKey)
            ->authorId($userId)
            ->one();

        if (!$device) {
            throw new BadRequestHttpException('Could not find a devices that matches the credentials provided.'); 
        }

        return $device;
    }

    /**
     * Fetch and return a Craft user using the request params
     * 
     * @uses $_GET['username']
     * @uses $_GET['password']
     * 
     * @throws BadRequestHttpException
     * 
     * @return User
     */
    public function getUserFromRequestParams() {
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
        return $user;
    }
    /**
     * Publish an event to Pusher
     *
     * @param string $channel
     * @param string $event
     * @param mixed $data
     * 
     * @return void
     */
    public function publish($channel, $event, $data)
    {
        $channelPrefix = $this->pusherMappings['device']['channelName'];
        $channelName = "{$channelPrefix}_{$this->requestJson['apiKey']}";
        $eventName = $this->pusherMappings['device']["event{$event}"];

        $this->pusher->trigger($channelName, $eventName, $data);
    }

    /**
     * Builds a JSON object out of an error object, and sets the Craft response HTTP status code
     * 
     * @param Exception $error
     * @return mixed
     */
    public function returnErrorJson($error) {
        if (empty($error->statusCode)): throw $error; endif;
        $statusCode = isset($error->statusCode) ? $error->statusCode : 400;

        $responseBody = [
            'error' => [
                'code' => $statusCode,
                'message' => $error->getMessage()
            ]
        ];

        Craft::$app->response->setStatusCode($statusCode);
        return $this->asJson($responseBody);

    }
}
