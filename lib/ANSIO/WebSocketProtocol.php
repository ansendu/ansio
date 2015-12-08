<?php
/**
 * User: ansen.du
 * Date: 15-12-3
 */
namespace ANSIO;
/**
 * Websocket protocol abstract class
 */
abstract class WebSocketProtocol {

    public $description;
    protected $webSocketSession;

    const STRING = NULL;
    const BINARY = NULL;

    public function __construct( $s ) {
        $this->webSocketSession = $s;
    }

    public function onHandshake() {
        return TRUE;
    }

    public function sendFrame( $data, $type ) {
        $this->webSocketSession->write( $this->_dataEncode( $data, $type ) );
    }

    public function recvFrame() {
        $this->_dataDecode();
    }

    /**
     * Returns handshaked data for reply
     * @param string Received data (no use in this class)
     * @return string Handshaked data
     */
    public function getHandshakeReply() {
        return FALSE;
    }

    /**
     * Data encoding
     */
    protected function _dataEncode( $decodedData, $type = NULL ) {
        return NULL;
    }

    /**
     * Data decoding
     */
    protected function _dataDecode() {
        return NULL;
    }

}