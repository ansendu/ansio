<?php
/**
 * User: ansen.du
 * Date: 15-12-3
 */
namespace ANSIO;
/*
 * timeEvent::add($name, function_cb, $sleepSec);
 */

class TimerEvent
{

    private $name;
    private $ev;
    private $lastSleepSec = 1;
    private $cb;
    private $args;
    private $hold = false;
    private $forever = false;

    // 必须使用私有的构造函数，因为不能将当前的 obj 释放给外部的引用，防止使用不当，不能释放

    private function __construct($name, $cb)
    {
        if (!is_callable($cb)) {
            return false;
        }
        if ($name == '') {
            $name = 'tEv_' . Daemon::getNextTimerEventId();
        }
        $this->name = $name;
        $this->cb = $cb;
        $this->ev = event_new();

        event_set($this->ev, STDIN, EV_TIMEOUT, array(__CLASS__, 'eventCall'), array($name));
        event_base_set($this->ev, Daemon::$eventBase);

        Daemon::$timerEventPool[$name] = $this;
    }

    public static function add($name, $cb)
    {
        Debug::timerEvent(__CLASS__ . '::' . __METHOD__ . '() invoked. timerEvent (' . $name . ').');
        $obj = new self($name, $cb);
        return $obj->name;
    }

    public static function setArgs($name, $args)
    {
        if (isset(Daemon::$timerEventPool[$name])) {
            Daemon::$timerEventPool[$name]->args = $args;
            return true;
        }
        return false;
    }

    public static function setTimeout($name, $sleepSec)
    {
        if (isset(Daemon::$timerEventPool[$name])) {
            $obj = Daemon::$timerEventPool[$name];
            $obj->hold = false;
            $obj->forever = false;
            $obj->sleep($sleepSec);
            return true;
        }
        return false;
    }

    private function sleep($sec = null)
    {
        if ($sec !== null) {
            $this->lastSleepSec = $sec;
        }
        event_add($this->ev, $this->lastSleepSec * 1000 * 1000);
    }

    public static function eventCall($fd, $events, $args)
    {
        $name = $args[0];

        if (!isset(Daemon::$timerEventPool[$name])) {
            Debug::errorEvent(__CLASS__ . '::' . __METHOD__ . '() the timerEvent (' . $name . ') not found.');
            return;
        }

        Debug::timerEvent(__CLASS__ . '::' . __METHOD__ . '() timerEvent (' . $name . ') calling.');

        $obj = Daemon::$timerEventPool[$name];

        call_user_func_array($obj->cb, is_array($obj->args) ? $obj->args : array());

        // 因为调用的函数可能会清除当前的 timerEvent
        if (!isset(Daemon::$timerEventPool[$name])) {
            return;
        }

        if ($obj->hold === false) {
            unset(Daemon::$timerEventPool[$name]);
            return;
        }

        if ($obj->forever === true) {
            $obj->sleep();
            return;
        }
    }

    public static function clearTimeout($name)
    {
        return self::remove($name);
    }

    public static function clearInterval($name)
    {
        return self::remove($name);
    }

    public static function cancel($name)
    {
        if (isset(Daemon::$timerEventPool[$name])) {
            $obj = Daemon::$timerEventPool[$name];
            event_del($obj->ev);
            return true;
        }
        return false;
    }

    // 注意 remove() 后，仍然需要清除调用处的对象引用，只有无引用了，才会真的执行
    public static function remove($name)
    {
        if (isset(Daemon::$timerEventPool[$name])) {
            unset(Daemon::$timerEventPool[$name]);
            return true;
        }
        return false;
    }

    public function __destruct()
    {
        event_del($this->ev);
        event_free($this->ev);
    }

}
