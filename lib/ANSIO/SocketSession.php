<?php
/**
 * User: ansen.du
 * Date: 15-11-26
 */
namespace ANSIO;

class SocketSession
{

    public $buf = '';
    protected $connId;
    protected $EOL = "\n"; // 行结束符
    protected $appInstance;
    public $addr;
    public $ip;
    public $port;
    public $isAlive = true;

    public function __construct($appInstance)
    {
        $this->appInstance = $appInstance;
        $this->init();
    }

    // 这个函数由 asyncServer or asyncClient 中的 setConnSocketSession() 方法中的 setConnId() 调用
    public function setConnId($connId)
    {
        $this->connId = $connId;
    }

    public function isAlive()
    {
        return $this->isAlive;
    }

    public function getConnId()
    {
        return $this->connId;
    }

    public function init()
    {

    }

    /**
     * Read a first line ended with \n from buffer, removes it from buffer and returns the line
     * @return string Line. Returns false when failed to get a line
     */
    public function gets()
    {
        $p = strpos($this->buf, $this->EOL);

        if ($p === FALSE) {
            return FALSE;
        }

        $sEOL = strlen($this->EOL);
        $r = Utils::binarySubstr($this->buf, 0, $p + $sEOL);
        $this->buf = Utils::binarySubstr($this->buf, $p + $sEOL);

        return $r;
    }

    /**
     * Called when the connection is ready to accept new data
     * @todo protected?
     * @return void
     */
    public function onWrite()
    {

    }

    /**
     * Send data to the connection. Note that it just writes to buffer that flushes at every baseloop
     * @param string Data to send.
     * @return boolean Success.
     */
    public function write($s)
    {
        return $this->appInstance->write($this->connId, $s);
    }

    /**
     * Send data and appending \n to connection. Note that it just writes to buffer that flushes at every baseloop
     * @param string Data to send.
     * @return boolean Success.
     */
    public function writeln($s)
    {
        return $this->appInstance->write($this->connId, $s . $this->EOL);
    }

    /**
     * Called when new data received
     * @todo +on & -> protected?
     * @param string New received data
     * @return void
     */
    public function stdin($buf)
    {
        $this->buf .= $buf;
        //var_dump($buf);
    }

    public function readUntil($s, $cb)
    {
        // set read until($cb)
        //读到某个字符串的时候 callback
    }


    /*
     * 默认读到 EOF 就关闭当前的连接
     * 注意继承类本方法的实现
     */
    public function onReadEOF()
    {

        Debug::netEvent(get_class($this) . '::' . __METHOD__ . '(' . $this->connId . ') invoked. ');
        $this->close();
    }

    //    从性能考虑，不使用以下两个事件
    //    public function onReadError() {
    //        daemon::log(get_class($this) . '::' . __METHOD__ . '(' . $this->connId . ') invoked. ');
    //    }

    //    public function onWriteError() {
    //        daemon::log(get_class($this) . '::' . __METHOD__ . '(' . $this->connId . ') invoked. ');
    //    }

    public function writeEOF()
    {
        $this->appInstance->writeEOF($this->connId);
    }

    public function close()
    {
        $this->appInstance->closeConnection($this->connId);
    }

    public function onDestory()
    {

    }

    //销毁 socketSession 继承对象本身的数据
    public function onClose()
    {

    }

    //    public function __destruct() {
    //        daemon::debug( __CLASS__ . ' is destruct' );
    //    }
}
