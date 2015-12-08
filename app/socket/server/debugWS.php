<?php
/**
 * User: ansen.du
 * Date: 15-12-4
 */
namespace socket\server;

use ANSIO\Config;
use ANSIO\Debug;
use ANSIO\WebSocketServer;
use ANSIO\WebSocketHandle;
use ANSIO\TimerEvent;


class debugWS extends WebSocketServer
{
    public $handle;

    public function __construct($addr = null, $port = null)
    {
        parent::__construct($name = '-');
        $this->bindSocket($addr, $port);
    }


    public function init()
    {
        $this->addPathHandle('debug', array($this, 'handleDebug'));

        TimerEvent::add('test', function () {
            debugWSHandle::sendDebugData('test broadcast after 10 sec.');
        });
        TimerEvent::setTimeout('test', 10);
    }

    public function bindSocket($addr = null, $port = null)
    {

        if ($addr === null && $port === null) {
            $addr = Config::get('host');
            $port = Config::get('port');
        }

        parent::bindSocket($addr, $port);
    }

    public function handleDebug($sess)
    {
        return new debugWSHandle($sess, $this);
    }


}


class debugWSHandle extends WebSocketHandle
{
    public static $clientPool = array();

    public function onFrame($data, $type)
    {
        self::sendDebugData('receice frame:[' . $data . '] type:[' . $type . ']');

//        $this->sendDebugDataToClient('receice frame:[' . $data . '] type:[' . $type . ']');
    }

    public function __construct($client, $appInstance = NULL)
    {
        parent::__construct($client, $appInstance);
        self::$clientPool[$client->getConnId()] = $client;
    }

    public function sendDebugDataToClient($s)
    {
        $this->client->sendFrame($s);
    }


    public static function sendDebugData($s)
    {
        foreach (self::$clientPool as $client) {
            /* @var $client webSocketSession */
            if (!$client->isAlive()) {
                unset(self::$clientPool[$client->getConnId()]);
                continue;
            }
            $client->sendFrame($s);
        }
    }

    public function onClose()
    {
        Debug::log(get_class($this) . '::' . __METHOD__ . ' : webSocketSession onClose id[' . $this->client->getConnId() . ']');
        unset(self::$clientPool[$this->client->getConnId()]);
    }

}