<?php
/**
 * User: ansen.du
 * Date: 15-12-4
 */
namespace socket\client;

use ANSIO\AsyncClient;
use ANSIO\Debug;
use ANSIO\SocketSession;


class testClient extends AsyncClient
{

    //put your code here
    public function init()
    {
        Debug::log('mark 1');
        $connId = $this->connectTo('127.0.0.1', '80');
        Debug::log('mark 2');
    }

    protected function onConnected($connId, $addr, $port)
    {
        Debug::log(get_class($this) . '::' . __METHOD__ . '(' . $connId . ') invoked. ');
        $sess = $this->setConnSocketSession($connId, new testClientSocketSession($this));
        $sess->write("GET / HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: Close\r\n\r\n");
    }

}

class testClientSocketSession extends SocketSession
{

    public function stdin($buf)
    {
        var_dump($buf);
    }

    public function onReadEOF()
    {
        $this->writeEOF();
    }

}

?>
