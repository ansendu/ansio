<?php
/**
 * User: ansen.du
 * Date: 15-12-3
 */
namespace ANSIO;
/**
 * Websocket protocol hixie-76
 * @see        http://tools.ietf.org/html/draft-hixie-thewebsocketprotocol-76
 */
class WebSocketProtocolHixie76 extends WebSocketProtocol
{
    const STRING = 0x00;
    const BINARY = 0x80;

    public function __construct($s)
    {
        parent::__construct($s);
        $this->description = "Deprecated websocket protocol (IETF drafts 'hixie-76' or 'hybi-00')";
    }

    public function onHandshake()
    {
        if (!isset($this->webSocketSession->server['HTTP_SEC_WEBSOCKET_KEY1']) || !isset($this->webSocketSession->server['HTTP_SEC_WEBSOCKET_KEY2'])) {
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Returns handshaked data for reply
     * @param string Received data (no use in this class)
     * @return string Handshaked data
     */
    public function getHandshakeReply()
    {

        $key3 = Utils::binarySubstr($this->webSocketSession->buf, 0, 8);
        $this->webSocketSession->buf = Utils::binarySubstr($this->webSocketSession->buf, 8);

        if ($this->onHandshake()) {
            $final_key = $this->_computeFinalKey($this->webSocketSession->server['HTTP_SEC_WEBSOCKET_KEY1'], $this->webSocketSession->server['HTTP_SEC_WEBSOCKET_KEY2'], $key3);

            if (!$final_key) {
                return FALSE;
            }

            if (!isset($this->webSocketSession->server['HTTP_SEC_WEBSOCKET_ORIGIN'])) {
                $this->webSocketSession->server['HTTP_SEC_WEBSOCKET_ORIGIN'] = '';
            }

            $reply = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n"
                . "Upgrade: WebSocket\r\n"
                . "Connection: Upgrade\r\n"
                . "Sec-WebSocket-Origin: " . $this->webSocketSession->server['HTTP_ORIGIN'] . "\r\n"
                . "Sec-WebSocket-Location: ws://" . $this->webSocketSession->server['HTTP_HOST'] . $this->webSocketSession->server['REQUEST_URI'] . "\r\n";

            if (isset($this->webSocketSession->server['HTTP_SEC_WEBSOCKET_PROTOCOL'])) {
                $reply .= "Sec-WebSocket-Protocol: " . $this->webSocketSession->server['HTTP_SEC_WEBSOCKET_PROTOCOL'] . "\r\n";
            }

            $reply .= "\r\n";
            $reply .= $final_key;

            return $reply;
        }

        return FALSE;
    }

    /**
     * Computes final key for Sec-WebSocket.
     * @param string Key1
     * @param string Key2
     * @param string Data
     * @return string Result
     */
    protected function _computeFinalKey($key1, $key2, $key3)
    {
        if (strlen($key3) < 8) {
            Debug::log(get_class($this) . '::' . __METHOD__ . ' : Invalid handshake data for client "' . $this->webSocketSession->addr . '"');
            return FALSE;
        }

        return md5($this->_computeKey($key1) . $this->_computeKey($key2) . $key3, TRUE);
    }

    /**
     * Computes key for Sec-WebSocket.
     * @param string Key
     * @return string Result
     */
    protected function _computeKey($key)
    {
        $spaces = 0;
        $digits = '';

        for ($i = 0, $s = strlen($key); $i < $s; ++$i) {
            $c = Utils::binarySubstr($key, $i, 1);

            if ($c === "\x20") {
                ++$spaces;
            } elseif (ctype_digit($c)) {
                $digits .= $c;
            }
        }

        if ($spaces > 0) {
            $result = (float)floor($digits / $spaces);
        } else {
            $result = (float)$digits;
        }

        return pack('N', $result);
    }

    protected function _dataEncode($data, $type = NULL)
    {
        // Binary

        if (($type & self::BINARY) === self::BINARY) {
            $n = strlen($data);
            $len = '';
            $pos = 0;

            char:
            ++$pos;
            $c = $n >> 0 & 0x7F;
            $n = $n >> 7;

            if ($pos != 1) {
                $c += 0x80;
            }

            if ($c != 0x80) {
                $len = chr($c) . $len;
                goto char;
            };

            return chr(self::BINARY) . $len . $data;
        } // String
        else {
            return chr(self::STRING) . $data . "\xFF";
        }
    }

    protected function _dataDecode()
    {
        $data = & $this->webSocketSession->buf;

        while (($buflen = strlen($data)) >= 2) {
            $decodedData = '';
            $frametype = ord(Utils::binarySubstr($data, 0, 1));
            if (($frametype & 0x80) === 0x80) {
                // 二进制封包

                $len = 0;
                $i = 0;

                do {
                    $b = ord(Utils::binarySubstr($data, ++$i, 1));
                    $n = $b & 0x7F;
                    $len *= 0x80;
                    $len += $n;
                } while ($b > 0x80);

                if (webSocketSession::maxPacketSize <= $len) {
                    // Too big packet
                    $this->webSocketSession->close();
                    return;
                }

                if ($buflen < $len + 2) {
                    // not enough data yet
                    return;
                }

                $decodedData .= Utils::binarySubstr($data, 2, $len);
                $data = Utils::binarySubstr($data, 2 + $len);
                $this->webSocketSession->onFrame($decodedData, $frametype);

            } else {

                if (($p = strpos($data, "\xFF")) !== FALSE) {
                    // 出现包
                    if (webSocketSession::maxPacketSize <= $p - 1) {
                        // Too big packet
                        $this->webSocketSession->close();
                        return;
                    }
                    $decodedData .= Utils::binarySubstr($data, 1, $p - 1);

                    //下面的关闭检测，需要再考虑下是否保留
                    if ($decodedData === '') {
                        // close packet
                        $this->webSocketSession->close();
                        return;
                    }
                    $data = Utils::binarySubstr($data, $p + 1);
                    $this->webSocketSession->onFrame($decodedData, $frametype);
                } else {
                    //没有出现包
                    // not enough data yet
                    if (webSocketSession::maxPacketSize <= strlen($data)) {
                        // Too big packet
                        $this->webSocketSession->close();
                    }
                    return;
                }
            }
            //find single packet end
        }
        //while end
    }

}