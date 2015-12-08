<?php
/**
 * User: ansen.du
 * Date: 15-12-3
 */
namespace ANSIO;

class WebSocketServer extends AsyncServer
{

    public $pathHandles = array();

    public function init()
    {
        //        $this->bindSocket( '0.0.0.0', 54321 );
        //        $this->addPathHandle( 'debug', array( $this, 'handleDebug' ) );
    }

    public function addPathHandle($path, $cb)
    {
        if (isset($this->pathHandles[$path])) {
            Debug::log(__METHOD__ . ' path \'' . $path . '\' is already taken.');
            return FALSE;
        }

        $this->pathHandles[$path] = $cb;

        return TRUE;
    }

    public function removePathHandle($path)
    {
        if (!isset($this->pathHandles[$path])) {
            return FALSE;
        }

        unset($this->pathHandles[$path]);

        return TRUE;
    }

    protected function onAccepted($connId, $ip, $port)
    {
        $this->setConnSocketSession($connId, new webSocketSession($this));
    }


}

class webSocketSession extends SocketSession
{
    //const maxPacketSize = 16384; // 16k
    const maxPacketSize = 16384; // 16k

    public $secprotocol;
    public $resultKey;
    public $handshaked = FALSE;
    public $server = array();
    public $cookie = array();
    public $firstline = FALSE;
    public $writeReady = TRUE;
    public $callbacks = array();
    /* @var $routeHandle webSocketHandle */
    public $routeHandle = NULL;

    public $protocol; // Related WebSocket protocol

    public function sendFrame($data, $type = NULL, $callback = NULL)
    {
        if (!$this->handshaked) {
            return FALSE;
        }

        if (!isset($this->protocol)) {
            Debug::log(get_class($this) . '::' . __METHOD__ . ' : Cannot find session-related websocket protocol for client ' . $this->addr);
            return FALSE;
        }

        $this->protocol->sendFrame($data, $type);
        $this->writeReady = FALSE;

        if ($callback) {
            $this->callbacks[] = $callback;
        }

        return TRUE;
    }

    public function onFrame($data, $type)
    {

        if (!isset($this->routeHandle)) {
            return FALSE;
        }

        $this->routeHandle->onFrame($data, $type);

        return TRUE;
    }

    /**
     * Called when the connection is ready to accept new data.
     * @return void
     */
    public function onWrite()
    {
        $this->writeReady = TRUE;
        for ($i = 0, $s = sizeof($this->callbacks); $i < $s; ++$i) {
            call_user_func(array_shift($this->callbacks), $this);
        }
    }

    /**
     * Called when the connection is handshaked.
     * @return void
     */
    public function onHandshake()
    {
        $e = explode('/', $this->server['DOCUMENT_URI']);
        $handleName = isset($e[1]) ? $e[1] : '';

        if (!isset($this->appInstance->pathHandles[$handleName])) {
            Debug::log(__METHOD__ . ': undefined pathHandle \'' . $handleName . '\'.');
            return FALSE;
        }

        if (!$this->routeHandle = call_user_func($this->appInstance->pathHandles[$handleName], $this)) {
            return FALSE;
        }

        if (!isset($this->protocol)) {
            Debug::log(get_class($this) . '::' . __METHOD__ . ' : Cannot find session-related websocket protocol for client "' . $this->addr . '"');
            return FALSE;
        }

        if ($this->protocol->onHandshake() === FALSE) {
            return FALSE;
        }

        return TRUE;
    }

    public function handshake()
    {
        $this->handshaked = TRUE;

        if (!$this->onHandshake()) {
            $this->close();
            return FALSE;
        }

        // Handshaking...
        $handshake = $this->protocol->getHandshakeReply();

        if (!$handshake) {
            Debug::log(get_class($this) . '::' . __METHOD__ . ' : Handshake protocol failure for client "' . $this->addr . '"');
            $this->close();
            return FALSE;
        }

        if ($this->write($handshake)) {
            //            if (is_callable(array($this->upstream, 'onHandshake'))) {
            //                $this->upstream->onHandshake();
            //            }
        } else {
            Debug::log(get_class($this) . '::' . __METHOD__ . ' : Handshake send failure for client "' . $this->addr . '"');
            $this->close();
            return FALSE;
        }

        return TRUE;
    }

    public function stdin($buf)
    {

        $this->buf .= $buf;

        if (!$this->handshaked) {
            $i = 0;
            while (($l = $this->gets()) !== FALSE) {
                if ($i++ > 100) {
                    break;
                }

                if ($l === "\r\n") {
                    if (
                        !isset($this->server['HTTP_CONNECTION'])
                        || (!preg_match('/upgrade/i', $this->server['HTTP_CONNECTION'])) // "Upgrade" is not always alone (ie. "Connection: Keep-alive, Upgrade")
                        || !isset($this->server['HTTP_UPGRADE'])
                        || (strtolower($this->server['HTTP_UPGRADE']) !== 'websocket') // Lowercase compare important
                    ) {
                        $this->close();
                        return;
                    }

                    if (isset($this->server['HTTP_COOKIE'])) {
                        ucs_parse_str(strtr($this->server['HTTP_COOKIE'], array(';' => '&', ' ' => '')), $this->cookie);
                    }

                    // ----------------------------------------------------------
                    // Protocol discovery, based on HTTP headers...
                    // ----------------------------------------------------------
                    if (isset($this->server['HTTP_SEC_WEBSOCKET_VERSION'])) { // HYBI
                        // see socket.io websocket transports
                        if ($this->server['HTTP_SEC_WEBSOCKET_VERSION'] == 8 || $this->server['HTTP_SEC_WEBSOCKET_VERSION'] == 7) { // At the moment, managing only version 8 (FF7, Chrome14)
                            $this->protocol = new WebSocketProtocolHybi10($this);
                        } else if ($this->server['HTTP_SEC_WEBSOCKET_VERSION'] == 13) { // At the moment, managing only version 8 (FF11, Chrome16)
                            $this->protocol = new WebSocketProtocolHybi17($this);
                        } else {
                            Debug::log(get_class($this) . '::' . __METHOD__ . " : Websocket protocol version " . $this->server['HTTP_SEC_WEBSOCKET_VERSION'] . ' is not yet supported for client "' . $this->addr . '"');

                            $this->close();
                        }
                    } else { // Defaulting to HIXIE (Safari5 and many non-browser clients...)
                        $this->protocol = new WebSocketProtocolHixie76($this);
                    }
                    // ----------------------------------------------------------
                    // End of protocol discovery
                    // ----------------------------------------------------------

                    break;
                }

                if (!$this->firstline) {
                    $this->firstline = TRUE;
                    $e = explode(' ', $l);
                    $u = parse_url(isset($e[1]) ? $e[1] : '');

                    $this->server['REQUEST_METHOD'] = $e[0];
                    $this->server['REQUEST_URI'] = $u['path'] . (isset($u['query']) ? '?' . $u['query'] : '');
                    $this->server['DOCUMENT_URI'] = $u['path'];
                    $this->server['PHP_SELF'] = $u['path'];
                    $this->server['QUERY_STRING'] = isset($u['query']) ? $u['query'] : NULL;
                    $this->server['SCRIPT_NAME'] = $this->server['DOCUMENT_URI'] = isset($u['path']) ? $u['path'] : '/';
                    $this->server['SERVER_PROTOCOL'] = isset($e[2]) ? trim($e[2]) : '';

                    $this->server['REMOTE_ADDR'] = $this->addr;
                } else {
                    $e = explode(': ', $l);

                    if (isset($e[1])) {
                        $this->server['HTTP_' . strtoupper(strtr($e[0], array('-' => '_')))] = rtrim($e[1], "\r\n");
                    }
                }
            }
        }

        if ($this->handshaked) {
            if (!isset($this->protocol)) {
                Debug::log(get_class($this) . '::' . __METHOD__ . ' : Cannot find session-related websocket protocol for client "' . $this->addr . '"');
                $this->close();
                return;
            }
            // protocol 里面会使用引用处理 buf
            $this->protocol->recvFrame();
        } else {
            // 注意，在 webSocketProtocolHixie76 会处理 buf，会截取 8 个字节作为 key3
            $this->handshake();
        }
    }

    public function onClose()
    {
        if ($this->routeHandle) {
            $this->routeHandle->onClose();
        }
    }

    public function onDestory()
    {
        Debug::log(get_class($this) . '::' . __METHOD__ . ' : webSocketSession onDestory."');
        $this->onClose();
    }


}

class WebSocketHandle
{
    /* @var $client webSocketSession */
    public $client; // Remote client
    /* @var $appInstance asyncServer */
    public $appInstance;

    public function __construct($client, $appInstance = NULL)
    {
        $this->client = $client;
        if ($appInstance) {
            $this->appInstance = $appInstance;
        }
    }

    public function onHandshake()
    {
    }

    public function onClose()
    {
    }

    public function onFrame($data, $type)
    {
    }

}