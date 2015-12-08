<?php
/**
 * User: ansen.du
 * Date: 15-12-1
 */
namespace ANSIO;

abstract class AsyncClient extends AsyncBase
{
    //异步 connect
    private $checkConnSocketPool = array();
    private $checkConnEvPool = array();

    public function onConnectError($connId, $errno = null)
    {
        Debug::netErrorEvent(get_class($this) . '::' . __METHOD__ . '(' . $connId . ') invoked. ');
    }

    public function connectTo($host, $port, $blockConnect = false)
    {
        Debug::netEvent(get_class($this) . '::' . __METHOD__ . '(' . $host . ':' . $port . ') invoked.');
        $connSocket = stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 30);
        //add err connect

        stream_set_blocking($connSocket, 0);
        if (!is_resource($connSocket)) {
            Debug::netErrorEvent(get_class($this) . ': can not add errorneus socket with address \'' . $host . ':' . $port . '\'.');
            return false;
        }

        $connId = daemon::getNextSocketId();


        //@todo 增加阻塞 connect ， 这样可以返回 connId 的时候 socketSession 已经建立好
        if ($blockConnect) {

        }

        $ev = event_new();
        // why not use EV_PERSIST, is because first event is acceptEvent?
        if (!event_set($ev, $connSocket, EV_WRITE, array($this, 'onConnectedEvent'), array($connId))) {
            Debug::netErrorEvent(get_class($this) . '::' . __METHOD__ . ': can not set onAcceptEvent() on binded socket: ' . Debug::dump($connSocket));
            return false;
        }
        event_base_set($ev, daemon::$eventBase);
        event_add($ev, 1 * 1000 * 1000);

        $this->checkConnSocketPool[$connId] = $connSocket;
        $this->checkConnEvPool[$connId] = $ev;

        return $connId;

    }

    public function onConnectedEvent($connSocket, $events, $arg)
    {
        $connId = $arg[0];
        Debug::netEvent(get_class($this) . '::' . __METHOD__ . '(' . $connId . ') invoked. ');

        //处理两种状态，一种是直接连接成功，一种是异步通知
        if (isset($this->checkConnEvPool[$connId])) {
            // 异步通知
            // 因为 注册 EV_WRITE 事件是非持久模式的，所以这里不用 delete, 只需要 unset pool 即可
            unset($this->checkConnSocketPool[$connId]);
            unset($this->checkConnEvPool[$connId]);
        }

        $evBuf = event_buffer_new(
            $connSocket, array($this, 'onEvBufReadEvent'), array($this, 'onEvBufWriteEvent'), array($this, 'onEventEvent'), array($connId)
        );

        event_buffer_base_set($evBuf, daemon::$eventBase);
        event_buffer_priority_set($evBuf, 10);
        event_buffer_watermark_set($evBuf, EV_READ, $this->evBufLowMark, $this->evBufHighMark);

        if (!event_buffer_enable($evBuf, (EV_READ | EV_WRITE | EV_PERSIST))) {
            Debug::netErrorEvent(get_class($this) . '::' . __METHOD__ . ': can not set base of buffer. #' . $connId);

//            socket_close($connSocket);
            fclose($connSocket);
            return;
        }

        // 调试这里时，浪费了很多时间，必须注意的是，以上 event 所用的变量如果在函数中，如果没有交给其他的变量引用，在函数结束时就会销毁，
        // 造成连接直接断或者bufferevent 不能触发。晕啊晕。

        $this->connSocketPool[$connId] = $connSocket;
        $this->connEvBufPool[$connId] = $evBuf;

        list($ip, $port) = explode(':', stream_socket_get_name($connSocket, true));

        $this->updateLastContact($connId);

        $this->onConnected($connId, $ip, $port);
    }

    protected function onConnected($connId, $ip, $port)
    {
        $this->setConnSocketSession($connId, new socketSession($this));
    }

}
