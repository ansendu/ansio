<?php
/**
 * User: ansen.du
 * Date: 15-11-26
 */
namespace ANSIO;

abstract class AsyncBase
{
    const TYPE_TCP = 0;
    const TYPE_SOCKET = 1; // todo add unix socket support

    const EV_TIMEOUT = 0x01;
    const EV_READ = 0x02;
    const EV_WRITE = 0x04;
    const EV_SIGNAL = 0x08;
    const EV_PERSIST = 0x10;

    //(a) EV_TIMEOUT: 超时
    //(b) EV_READ: 只要网络缓冲中还有数据，回调函数就会被触发
    //(c) EV_WRITE: 只要塞给网络缓冲的数据被写完，回调函数就会被触发
    //(d) EV_SIGNAL: POSIX信号量，参考manual吧
    //(e) EV_PERSIST: 不指定这个属性的话，回调函数被触发后事件会被删除
    //(f) EV_ET: Edge-Trigger边缘触发，参考EPOLL_ET

    const BEV_EVENT_READING = 0x01; //*< error encountered while reading
    const BEV_EVENT_WRITING = 0x02; //* < error encountered while writing
    const BEV_EVENT_EOF = 0x10; //* < eof file reached
    const BEV_EVENT_ERROR = 0x20; //* < unrecoverable error encountered
    const BEV_EVENT_TIMEOUT = 0x40; //* < user-specified timeout reached
    const BEV_EVENT_CONNECTED = 0x80; //* < connect operation finished.

    protected $EOL = "\n"; // 行结束符
    protected $readPacketSize = 4096;
    protected $evBufLowMark = 0x1;
    protected $evBufHighMark = 0xFFFFFF;

    /*
     * 因为 event 和 socket resource 在函数结束后会 gc ，所以需要增加引用计数
     */
    protected $connSocketPool = array(); // for connect socket
    protected $connEvBufPool = array(); // for connect socket buffer_event
    protected $connSocketSessionPool = array();


    public function __construct($name = '-')
    {
        $this->name = $name;
        $appName = get_class($this);
        Debug::netEvent($appName . '[' . $name . '] construct.');
        if (!isset(Daemon::$appInstances[$appName])) {
            Daemon::$appInstances[$appName] = array();
        }
        Daemon::$appInstances[$appName][$this->name] = $this;

        $this->init();
    }

    /*
     * 注意:这里使用libevent 的 eventBuffer 如果是使用原生的onDirectReadEvent要注意以下几点：
     * socket_read 返回 false 时，代表连接出错，这个时候如果不是 EAGAIN and EWOULDBLOCK 则调用 event_buffer 注册的 onEventEvent
     * socket_read 可能会返回空的字符串
     * php ext socket src 处理了非阻塞 IO 的情况，出现 EAGAIN 或者 EWOULDBLOCK 时，返回 false, 同时设定 errno
     * $no = socket_last_error();
     * 阻塞 io 在 linux errno = 11 is EAGAIN and EWOULDBLOCK 要特殊处理
     * 可能会多一次 onDirectReadEvent 调用 例：
     * 最后来的数据是 abc, 上面的 while 继续执行，这个时候 $read 已经是空字符串了，不过因为 $data 前面保存了 abc 的数据，不能够丢掉，需要留给 session 使用，
     * 需要额外的 $data === '' 的判断，留待下次 onDirectReadEvent 的时候读出空来处理
     * socket_read() returns a zero length string ("") when there is no more data to read.
     * @todo
     */

    public function onEvBufReadEvent($evBuf, $arg)
    {
        $connId = $arg[0];
        Debug::netEvent(get_class($this) . '::' . __METHOD__ . '(' . $connId . ') invoked. ');
        $this->updateLastContact($connId);

        $data = '';
        while (true) {
            $read = event_buffer_read($evBuf, $this->readPacketSize);

            if ($read === '' || $read === NULL || $read === false) {
                break;
            }
            $data .= $read;
        }
        if ($data) {
            Debug::netEvent(get_class($this) . '::' . __METHOD__ . '(' . $connId . ') stdin(' . Utils::convertSize(strlen($data)) . ') start... ');
            Debug::stdin('Server --> Get Client Data: ' . Debug::exportBytes($data));
            $this->connSocketSessionPool[$connId]->stdin($data);
            Debug::netEvent(get_class($this) . '::' . __METHOD__ . '(' . $connId . ') stdin(' . Utils::convertSize(strlen($data)) . ') end. ');
        }
    }


    /*//当客户端socket准备好写入时，libevent调用这个函数
    * 以下注释是在增加 onConnectedEvent() 以前添加的，在此处备忘，现在在对外连接失败后不会触发该事件
    * 连接完毕(see unpv1, 里面讲到连接后会调用可写事件)以及写完数据后会调用，因为不是使用 libevent 的 socket connect(libevent 的 connect 会 catch 掉连接错误的情况，会调用 eventCb) ，连接失败的时候也会触发
    *
    * nc 12345 命令，ctrl + c后，server 并不会马上收回连接，要在下次写数据的时候，并且经过观察，第一次写并不会马上触发 onEventEvent
    * 第二次写才会马上触发，同时注意，第一次写时候的 updateLastContact 也执行了
    * 为了健壮起见需要对wirtestagepool进行控制 待实现....
    */
    public function onEvBufWriteEvent($evBuf, $arg)
    {
        $connId = $arg[0];
        Debug::netEvent(get_class($this) . '::' . __METHOD__ . '(' . $connId . ') invoked. ');

        $this->updateLastContact($connId);

        $this->connSocketSessionPool[$connId]->onWrite();
    }

    /*
     * directRead 和 eventBuffer 读的时候，连接出错调用
     * 同样需要对wirtestagepool进行控制 待实现....
     */
    public function onEventEvent($evBufOrNULL, $what, $arg)
    {
        $connId = $arg[0];
        Debug::netEvent(get_class($this) . '::' . __METHOD__ . '(' . $connId . ')(' . implode(', ', self::convertEventConst($what)) . ') invoked. ');

        if ($what & self::BEV_EVENT_EOF) {
            //eof case
            $this->connSocketSessionPool[$connId]->onReadEOF();
            return;
        }

        $this->realCloseConnection($connId);
        return;
    }

    public static function convertEventConst($what)
    {
        $data = array();
        $data[] = $what;

//        if ($what & self::BEV_EVENT_DIRECT_READ) {
//            $data[] = 'DIRECT_READ';
//        }
        if ($what & self::BEV_EVENT_CONNECTED) {
            $data[] = 'BEV_CONNECTED';
        }
        if ($what & self::BEV_EVENT_EOF) {
            $data[] = 'EOF';
        }
        if ($what & self::BEV_EVENT_ERROR) {
            $data[] = 'BEV_ERROR';
        }
        if ($what & self::BEV_EVENT_READING) {
            $data[] = 'BEV_READING';
        }
        if ($what & self::BEV_EVENT_WRITING) {
            $data[] = 'BEV_WRITING';
        }
        if ($what & self::BEV_EVENT_TIMEOUT) {
            $data[] = 'BEV_TIMEOUT';
        }
        return $data;
    }

    public function close($connId)
    {
        $this->closeConnection($connId);
    }

    public function closeConnection($connId)
    {
        Debug::netEvent(get_class($this) . '::' . __METHOD__ . '(' . $connId . ') invoked. ');

        $this->realCloseConnection($connId);
        return TRUE;
    }

    /*
     * 如果来自 directRead, 不管是读到 EOF 还是 ERROR， 均删除掉事件注册，防止继续通知可读事件
     * 经过测试，发现 libevnet 中的 bufferevent_sock.c 中的 bufferevent_readcb 中有删除 EV_READ 的操作。
     * 另外在当前代码中发现，如果不关闭 socket ，会一直出现 EV_READ 的事件(使用eventbuffer则不会出现这样的问题)
     */

    private function realCloseConnection($connId)
    {
        Debug::netEvent(get_class($this) . '::' . __METHOD__ . '(' . $connId . ') invoked. ');

        if (!isset($this->connSocketPool[$connId])) {
            Debug::netErrorEvent(get_class($this) . '::' . __METHOD__ . '(' . $connId . ') can not find. ');
            return;
        }

        stream_socket_shutdown($this->connSocketPool[$connId], STREAM_SHUT_RDWR);
        fclose($this->connSocketPool[$connId]);
//        socket_close( $this->connSocketPool[$connId] );
        unset($this->connSocketPool[$connId]);

        event_buffer_free($this->connEvBufPool[$connId]);
        unset($this->connEvBufPool[$connId]);

        if (isset($this->connSocketSessionPool[$connId])) {
            $this->connSocketSessionPool[$connId]->onDestory();
            unset($this->connSocketSessionPool[$connId]);
        }

        unset(daemon::$connLastContactPool[$connId]);
    }

    public function isAlive($connId)
    {
        return isset($this->connEvBufPool[$connId]);
    }

    public function writeln($connId, $s)
    {
        return $this->write($connId, $s . $this->EOL);
    }

    public function write($connId, $s)
    {
        Debug::netEvent(get_class($this) . '::' . __METHOD__ . '(' . $connId . ',' . strlen($s) . ') invoked. ');

        // 如果连接已经关闭了， bufferEv 已经被销毁，这里不能执行 buffer_write
        if (!isset($this->connEvBufPool[$connId])) {
            Debug::netErrorEvent(get_class($this) . '::' . __METHOD__ . '(' . $connId . ',' . strlen($s) . ')(' . substr($s, 0, 50) . ') already closed, skip write. ');
            return FALSE;
        }

        return event_buffer_write($this->connEvBufPool[$connId], $s);
    }

    public function getConnSocketSession($connId)
    {
        Debug::netEvent(get_class($this) . '::' . __METHOD__ . '(' . $connId . ') invoked. ');
        return $this->connSocketSessionPool[$connId] ? $this->connSocketSessionPool[$connId] : FALSE;
    }

    public function setConnSocketSession($connId, socketSession $socketSession)
    {
        Debug::netEvent(get_class($this) . '::' . __METHOD__ . '(' . $connId . ') invoked. ');

        $socketSession->setConnId($connId);
        list($ip, $port) = explode(':', stream_socket_get_name($this->connSocketPool[$connId], true));
        $socketSession->addr = $ip . ':' . $port;
        $socketSession->ip = $ip;
        $socketSession->port = $port;

        return $this->connSocketSessionPool[$connId] = $socketSession;
    }

    protected function updateLastContact($connId)
    {
        Debug::netEvent(get_class($this) . '::' . __METHOD__ . '(' . $connId . ') invoked. ');
        daemon::$connLastContactPool[$connId] = time();
    }

//    public function closeTimeoutConnection()
//    {
//        Debug::netEvent(get_class($this) . '::' . __METHOD__ . ' invoked. ');
//        $closeTime = time() - 60;
//        // todo: 这里有 bug, 会出现不属于自己的连接， pool 是在 daemon 中的， 这里只能操作本对象实例的连接
//        foreach (daemon::$connLastContactPool as $connId => $lastContactTime) {
//            if ($lastContactTime < $closeTime) {
//                $this->realCloseConnection($connId);
//            }
//        }
//    }

    public function getConnectSockByConnId($connId)
    {
        return $this->connSocketPool[$connId];
    }

}