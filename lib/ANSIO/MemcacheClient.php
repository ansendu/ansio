<?php
/**
 * User: ansen.du
 * Date: 15-11-27
 */
namespace ANSIO;

class MemcacheClient extends AsyncClient
{

    public $servConn = array(); // Active connections

    public function init()
    {

    }

    public function getConnection($callback, $url = NULL)
    {

        $url = 'memcache://127.0.0.1:11211';

        if (isset($this->servConn[$url])) {
            foreach ($this->servConn[$url] as $connId) {
                if (
                    isset($this->connSocketSessionPool[$connId])
                    && !count($this->connSocketSessionPool[$connId]->callbacks)
                ) {
                    call_user_func($callback, $this->connSocketSessionPool[$connId]);
                    return;
                }
            }
        } else {
            $this->servConn[$url] = array();
        }

        $u = parse_url($url);

        if (!isset($u['port'])) {
            $u['port'] = 3306;
        }

        $connId = $this->connectTo($u['host'], $u['port']);

        $this->connetingPool[$connId] = array();
        $this->connetingPool[$connId]['cb'] = $callback;
        $this->connetingPool[$connId]['url'] = $url;

        if (!$connId) {
            return FALSE;
        }
    }

    public function onConnected($connId, $ip, $port)
    {
        Debug::log(get_class($this) . '::' . __METHOD__ . '(' . $connId . ') invoked. ');

        $this->setConnSocketSession($connId, new memcacheClientSession($this));

        $url = $this->connSocketSessionPool[$connId]->url = $this->connetingPool[$connId]['url'];

        $this->servConn[$url][$connId] = $connId;

        if (isset($this->connetingPool[$connId]['cb'])) {
            call_user_func($this->connetingPool[$connId]['cb'], $this->connSocketSessionPool[$connId]);
            unset($this->connetingPool[$connId]);
        }
    }

}

class memcacheClientSession extends socketSession
{

    public $callbacks = array(); // stack of onResponse callbacks
    public $state = 0; // current state of the connection
    public $result; // current result
    public $valueFlags; // flags of incoming value
    public $valueLength; // length of incoming value
    public $valueSize = 0; // size of received part of the value
    public $error; // error message
    public $key; // current incoming key

    public function get($key, $onResponse)
    {
        if (
            !is_string($key)
            || !strlen($key)
        ) {
            return;
        }

        $this->callbacks[] = $onResponse;
        $this->write('get ' . $key);
        $this->write("\r\n");
    }

    public function set($key, $value, $exp = 0, $onResponse = 'noopCb')
    {
        if (
            !is_string($key)
            || !strlen($key)
        ) {
            return;
        }

        $this->callbacks[] = $onResponse;

        $flags = 0;

        $this->write('set ' . $key . ' ' . $flags . ' ' . $exp . ' '
            . strlen($value) . "\r\n"
        );
        $this->write($value);
        $this->write("\r\n");
    }

    public function add($key, $value, $exp = 0, $onResponse = NULL)
    {
        if (
            !is_string($key)
            || !strlen($key)
        ) {
            return;
        }

        if ($onResponse !== NULL) {
            $this->callbacks[] = $onResponse;
        }

        $flags = 0;

        $this->write('add ' . $this->prefix . $key . ' ' . $flags . ' ' . $exp . ' ' . strlen($value)
        . ($onResponse === NULL ? ' noreply' : '') . "\r\n");
        $this->write($value);
        $this->write("\r\n");
    }

    public function delete($key, $onResponse = NULL, $time = 0)
    {
        if (
            !is_string($key)
            || !strlen($key)
        ) {
            return;
        }
        $this->callbacks[] = $onResponse;

        $this->write('delete ' . $key . ' ' . $time . "\r\n");
    }

    public function replace($key, $value, $exp = 0, $onResponse = NULL)
    {

        if ($onResponse !== NULL) {
            $this->callbacks[] = $onResponse;
        }

        $flags = 0;

        $this->write('replace ' . $this->prefix . $key . ' ' . $flags . ' ' . $exp . ' ' . strlen($value)
        . ($onResponse === NULL ? ' noreply' : '') . "\r\n");
        $this->write($value);
        $this->write("\r\n");
    }

    public function prepend($key, $value, $exp = 0, $onResponse = NULL)
    {

        if ($onResponse !== NULL) {
            $this->callbacks[] = $onResponse;
        }

        $flags = 0;

        $this->write('prepend ' . $this->prefix . $key . ' ' . $flags . ' ' . $exp . ' ' . strlen($value)
        . ($onResponse === NULL ? ' noreply' : '') . "\r\n");
        $this->write($value);
        $this->write("\r\n");
    }

    public function stats($onResponse)
    {
        $this->callbacks[] = $onResponse;
        $this->write('stats' . "\r\n");
    }

    public function stdin($buf)
    {
        Debug::debug(Debug::exportBytes($buf));
        $this->buf .= $buf;

        start:

        if ($this->state === 0) {
            while (($l = $this->gets()) !== FALSE) {
                $e = explode(' ', rtrim($l, "\r\n"));

                if ($e[0] == 'VALUE') {
                    $this->key = $e[1];
                    $this->valueFlags = $e[2];
                    $this->valueLength = $e[3];
                    $this->result = '';
                    $this->state = 1;
                    break;
                } elseif ($e[0] == 'STAT') {
                    if ($this->result === NULL) {
                        $this->result = array();
                    }

                    $this->result[$e[1]] = $e[2];
                } elseif (
                    ($e[0] === 'STORED')
                    || ($e[0] === 'END')
                    || ($e[0] === 'DELETED')
                    || ($e[0] === 'ERROR')
                    || ($e[0] === 'CLIENT_ERROR')
                    || ($e[0] === 'SERVER_ERROR')
                ) {
                    if ($e[0] !== 'END') {
                        $this->result = FALSE;
                        $this->error = isset($e[1]) ? $e[1] : NULL;
                    }

                    $cb = array_shift($this->callbacks);

                    if ($cb) {
                        call_user_func($cb, $this);
                    }

                    $this->valueSize = 0;
                    $this->result = NULL;
                }
            }
        }

        if ($this->state === 1) {
            if ($this->valueSize < $this->valueLength) {
                $n = $this->valueLength - $this->valueSize;
                $buflen = strlen($this->buf);

                if ($buflen > $n) {
                    $this->result .= binarySubstr($this->buf, 0, $n);
                    $this->buf = binarySubstr($this->buf, $n);
                } else {
                    $this->result .= $this->buf;
                    $n = $buflen;
                    $this->buf = '';
                }

                $this->valueSize += $n;

                if ($this->valueSize >= $this->valueLength) {
                    $this->state = 0;
                    goto start;
                }
            }
        }
    }

    public function onDestory()
    {
        unset($this->appInstance->servConn[$this->addr][$this->connId]);
    }

}
