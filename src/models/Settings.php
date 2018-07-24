<?php

namespace nickleguillou\craftiotpoc\models;

use craft\base\Model;

class Settings extends Model
{
    public $pusherEnabled = false;

    public $pusherSettings = [
        'options' => [
            'cluster' => '',
            'encrypted' => true
        ],
        'appId' => '',
        'key' => '',
        'secret' => ''
    ];

    public $pusherMappings = [
        'device' => [
            'channelName' => 'device',
            'eventProvision' => 'provision',
            'eventRecord' => 'record'
        ],
    ];
}
