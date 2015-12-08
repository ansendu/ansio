<?php
/**
 * User: ansen.du
 * Date: 15-12-3
 */
namespace ANSIO;

/**
 * Websocket protocol hybi-17
 * @see        http://tools.ietf.org/html/draft-ietf-hybi-thewebsocketprotocol-17
 */
class WebSocketProtocolHybi17 extends WebSocketProtocolHybi10
{

    public function __construct($s)
    {
        parent::__construct($s);
        $this->description = "Websocket protocol version " . $this->webSocketSession->server['HTTP_SEC_WEBSOCKET_VERSION'] . " (IETF draft 'hybi-17')";
    }

    /**
     * Returns handshaked data for reply
     * @param string Received data (no use in this class)
     * @return string Handshaked data
     */
    public function getHandshakeReply()
    {
        if ($this->onHandshake()) {
            if (!isset($this->webSocketSession->server['HTTP_ORIGIN'])) {
                $this->webSocketSession->server['HTTP_ORIGIN'] = '';
            }

            $reply = "HTTP/1.1 101 Switching Protocols\r\n"
                . "Upgrade: websocket\r\n"
                . "Connection: Upgrade\r\n"
                . "Sec-WebSocket-Origin: " . $this->webSocketSession->server['HTTP_ORIGIN'] . "\r\n"
                . "Sec-WebSocket-Location: ws://" . $this->webSocketSession->server['HTTP_HOST'] . $this->webSocketSession->server['REQUEST_URI'] . "\r\n"
                . "Sec-WebSocket-Accept: " . base64_encode(sha1(trim($this->webSocketSession->server['HTTP_SEC_WEBSOCKET_KEY']) . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true)) . "\r\n";

            if (isset($this->webSocketSession->server['HTTP_SEC_WEBSOCKET_PROTOCOL'])) {
                $reply .= "Sec-WebSocket-Protocol: " . $this->webSocketSession->server['HTTP_SEC_WEBSOCKET_PROTOCOL'] . "\r\n";
            }

            $reply .= "\r\n";

            return $reply;
        }

        return FALSE;
    }

}