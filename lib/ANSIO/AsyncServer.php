<?php
/**
 * User: ansen.du
 * Date: 15-11-27
 */
namespace ANSIO;

abstract class AsyncServer extends AsyncBase
{
    /*
     * 因为 event 和 socket resource 在函数结束后会 gc ，所以需要增加引用计数
     */
    protected $bindSocketEvPool = array(); // for listening socket event.
    protected $bindSocketPool = array(); // for listening socket event.

    protected function bindSocket($addr, $port)
    {
        $bindSocket = stream_socket_server("tcp://{$addr}:{$port}", $errno, $errstr);
        stream_set_blocking($bindSocket, 0);
        if (!is_resource($bindSocket)) {
            Debug::netErrorEvent(get_class($this) . ': can not add error socket with address \'' . $addr . ':' . $port . '\'.');
            return false;
        }

        $socketId = daemon::getNextSocketId();

        $ev = event_new();
        // why not use EV_PERSIST, is because first event is acceptEvent?
        if (!event_set($ev, $bindSocket, EV_READ, array($this, 'onAcceptEvent'), array($socketId))) {
            Debug::netErrorEvent(get_class($this) . '::' . __METHOD__ . ': can not set onAcceptEvent() on binded socket: ' . Debug::dump($bindSocket));
            return false;
        }
        $this->bindSocketEvPool[$socketId] = $ev;
        $this->bindSocketPool[$socketId] = $bindSocket;
        event_base_set($ev, daemon::$eventBase);
        event_add($ev);

    }

    public function onAcceptEvent($bindSocket, $events, $arg)
    {
        $bindSocketId = $arg[0];
        Debug::netEvent(get_class($this) . '::' . __METHOD__ . '(' . $bindSocketId . ') invoked.');
        // add to accept next event
        // why not use EV_PERSIST
        event_add($this->bindSocketEvPool[$bindSocketId]);
        $connSocket = stream_socket_accept($this->bindSocketPool[$bindSocketId]);
        if (!$connSocket) {
            Debug::netErrorEvent(get_class($this) . ': can not accept new TCP-socket');
            return;
        }
        stream_set_blocking($connSocket, 0);

        list($ip, $port) = explode(':', stream_socket_get_name($connSocket, true));

        $connId = daemon::getNextConnId();

        $evBuf = event_buffer_new(
            $connSocket, array($this, 'onEvBufReadEvent'), array($this, 'onEvBufWriteEvent'), array($this, 'onEventEvent'), array($connId)
        );

        event_buffer_base_set($evBuf, daemon::$eventBase);
        event_buffer_priority_set($evBuf, 10);
        event_buffer_watermark_set($evBuf, EV_READ, $this->evBufLowMark, $this->evBufHighMark);

        if (!event_buffer_enable($evBuf, (EV_READ | EV_WRITE | EV_PERSIST))) {
            Debug::netErrorEvent(get_class($this) . '::' . __METHOD__ . ': can not set base of buffer. #' . $connId);
            //close socket
            stream_socket_shutdown($connSocket, STREAM_SHUT_RDWR);
            fclose($connSocket);
            return;
        }

        // 调试这里时，浪费了很多时间，必须注意的是，以上 event 所用的变量如果在函数中，如果没有交给其他的变量引用，在函数结束时就会销毁，
        // 造成连接直接断或者bufferevent 不能触发。晕啊晕。

        $this->connSocketPool[$connId] = $connSocket;
        $this->connEvBufPool[$connId] = $evBuf;

        $this->updateLastContact($connId);
        $this->onAccepted($connId, $ip, $port);


    }

    protected function onAccepted($connId, $ip, $port)
    {
        $this->setConnSocketSession($connId, new SocketSession($this));
    }
}