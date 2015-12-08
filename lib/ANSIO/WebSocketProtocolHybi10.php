<?php
/**
 * User: ansen.du
 * Date: 15-12-3
 */
namespace ANSIO;
/**
 * Websocket protocol hybi-10
 * @see        http://tools.ietf.org/html/draft-ietf-hybi-thewebsocketprotocol-10
 */
class WebSocketProtocolHybi10 extends WebSocketProtocol
{

    // @todo manage only the 4 last bits (opcode), as described in the draft
    const STRING = 0x01;
    const BINARY = 0x02;
    const CLOSE = 0x08;
    const PING = 0x09;
    const PONG = 0x0A;

    public function __construct($s)
    {
        parent::__construct($s);
        $this->description = "Websocket protocol version " . $this->webSocketSession->server['HTTP_SEC_WEBSOCKET_VERSION'] . " (IETF draft 'hybi-10')";
    }

    public function onHandshake()
    {
        if (!isset($this->webSocketSession->server['HTTP_SEC_WEBSOCKET_KEY']) || !isset($this->webSocketSession->server['HTTP_SEC_WEBSOCKET_VERSION']) || ($this->webSocketSession->server['HTTP_SEC_WEBSOCKET_VERSION'] < 8)) {
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
        if ($this->onHandshake()) {
            if (!isset($this->webSocketSession->server['HTTP_SEC_WEBSOCKET_ORIGIN'])) {
                $this->webSocketSession->server['HTTP_SEC_WEBSOCKET_ORIGIN'] = '';
            }

            $reply = "HTTP/1.1 101 Switching Protocols\r\n"
                . "Upgrade: websocket\r\n"
                . "Connection: Upgrade\r\n"
                . "Sec-WebSocket-Origin: " . $this->webSocketSession->server['HTTP_SEC_WEBSOCKET_ORIGIN'] . "\r\n"
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

    /**
     * Data encoding, according to related IETF draft
     *
     * @see http://tools.ietf.org/html/draft-ietf-hybi-thewebsocketprotocol-10#page-16
     */
    protected function _dataEncode_masked($decodedData, $type = NULL)
    {
        $frames = array();
        $maskingKeys = chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255));
        $frames[0] = ($type === NULL) ? self::STRING : $type;

        // 128 是为了 set fin bit
        $frames[0] = $frames[0] + 128;

        $dataLength = strlen($decodedData);

        // 128 是为了 set mask bit
        if ($dataLength <= 125) {
            $frames[1] = $dataLength + 128;
        } elseif ($dataLength <= 65535) {
            $frames[1] = 254; // 126 + 128
            $frames[2] = $dataLength >> 8;
            $frames[3] = $dataLength & 0xFF;
        } else {
            $frames[1] = 255; // 127 + 128
            $frames[2] = $dataLength >> 56;
            $frames[3] = $dataLength >> 48;
            $frames[4] = $dataLength >> 40;
            $frames[5] = $dataLength >> 32;
            $frames[6] = $dataLength >> 24;
            $frames[7] = $dataLength >> 16;
            $frames[8] = $dataLength >> 8;
            $frames[9] = $dataLength & 0xFF;
        }

        $maskingFunc = function ($data, $mask) {
            for ($i = 0, $l = strlen($data); $i < $l; $i++) {
                // Avoid storing a new copy of $data...
                $data[$i] = $data[$i] ^ $mask[$i % 4];
            }

            return $data;
        };

        return implode('', array_map('chr', $frames)) . $maskingKeys . $maskingFunc($decodedData, $maskingKeys);
    }

    //fix: 最新的谷歌浏览器要求服务器发送的数据必须没有 mask
    protected function _dataEncode($decodedData, $type = NULL)
    {
        $frames = array();
        $frames[0] = ($type === NULL) ? self::STRING : $type;

        // 128 是为了 set fin bit
        $frames[0] = $frames[0] + 128;

        $dataLength = strlen($decodedData);

        // 128 是为了 set mask bit
        //$maskBit = 128;
        $maskBit = 0;

        if ($dataLength <= 125) {
            $frames[1] = $dataLength + $maskBit;
        } elseif ($dataLength <= 65535) {
            $frames[1] = 126 + $maskBit;
            $frames[2] = $dataLength >> 8;
            $frames[3] = $dataLength & 0xFF;
        } else {
            $frames[1] = 127 + $maskBit;
            $frames[2] = $dataLength >> 56;
            $frames[3] = $dataLength >> 48;
            $frames[4] = $dataLength >> 40;
            $frames[5] = $dataLength >> 32;
            $frames[6] = $dataLength >> 24;
            $frames[7] = $dataLength >> 16;
            $frames[8] = $dataLength >> 8;
            $frames[9] = $dataLength & 0xFF;
        }

        return implode('', array_map('chr', $frames)) . $decodedData;
    }

    /**
     * Data decoding, according to related IETF draft
     *
     * @see http://tools.ietf.org/html/draft-ietf-hybi-thewebsocketprotocol-10#page-16
     */
    protected function _dataDecode()
    {

        //参考 http://blog.csdn.net/fenglibing/article/details/6852497
        //这里仅仅处理最基本的

        $encodedData = & $this->webSocketSession->buf;

        // 至少有2个字节的头
        while (($buflen = strlen($encodedData)) >= 2) {

            $len = 0;

            $isMasked = (bool)(ord($encodedData[1]) >> 7);
            $opcode = ord($encodedData[0]) & 15;
            $dataLength = ord($encodedData[1]) & 127;

            $len += 2;

            if ($dataLength === 126) {
                $extDataLength = hexdec(sprintf('%02x%02x', ord($encodedData[2]), ord($encodedData[3])));
                $len += 2;
            } else if ($dataLength === 127) {
                $extDataLength = hexdec(sprintf('%02x%02x%02x%02x%02x%02x%02x%02x', ord($encodedData[2]), ord($encodedData[3]), ord($encodedData[4]), ord($encodedData[5]), ord($encodedData[6]), ord($encodedData[7]), ord($encodedData[8]), ord($encodedData[9])));
                $len += 8;
            } else {
                $extDataLength = $dataLength;
            }

            if (webSocketSession::maxPacketSize <= $extDataLength) {
                // Too big packet
                $this->webSocketSession->close();
                return;
            }

            if ($isMasked) {
                $maskingKey = Utils::binarySubstr($encodedData, $len, 4);
                $len += 4;
            }

            if ($extDataLength + $len > strlen($encodedData)) {
                //没有出现包
                // not enough data yet
                return;
            }

            $data = Utils::binarySubstr($encodedData, $len, $extDataLength);

            //这里用的引用，所以会处理掉 socketSession->buf
            $encodedData = Utils::binarySubstr($encodedData, $len + $extDataLength);

            if ($opcode === self::CLOSE) {
                //客户端主动关闭连接
                $this->webSocketSession->close();
                return;
            }

            if ($opcode === self::PING) {
                $this->sendFrame('', 'PONG');
                continue;
            }


            if ($opcode === self::PONG) {
                //todo: 收到 pong 包，不做任何事情，以后应该更新最后的连接时间
                daemon::log(get_class($this) . '::' . __METHOD__ . ' : receive PONG packet. ' . $this->webSocketSession->addr);
                continue;
            }

            if ($isMasked) {
                $unmaskingFunc = function ($data, $mask) {
                    for ($i = 0, $l = strlen($data); $i < $l; $i++) {
                        // Avoid storing a new copy of $data...
                        $data[$i] = $data[$i] ^ $mask[$i % 4];
                    }

                    return $data;
                };
                $this->webSocketSession->onFrame($unmaskingFunc($data, $maskingKey), $opcode);
            } else {
                $this->webSocketSession->onFrame($data, $opcode);
            }
        }
    }

}