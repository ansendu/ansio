<!DOCTYPE html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <script src="jquery.min.js"></script>
</head>
<style type="text/css">
    body {
        font-size: 12px;
        font-family: "Bitstream Vera Sans Mono", "Courier", monospace;
    }

    .box {
        padding: 10px;
        margin: 10px;
        border: black solid 1px;
    }

    .minibox {
        padding: 5px;
        margin: 5px;
        border: #D2D9DD solid 1px;
    }

    .code {
        background-color: #fff8dc;
    }

    .output {
        background-color: #ffffff;
        white-space: pre;
        color: #333;
    }

    .title {

    }
</style>
<div class='box'>
    <div class='title'>debug:</div>
    <div id='debugOut' class='box output'>
    </div>
</div>
<script>
	$(function(){
    var $debugOut = $( '#debugOut' );

    function debugWsStart() {
        debugWs = new WebSocket( 'ws://192.168.1.112:8888/debug' );
        debugWs.onmessage = function ( evt ) {
            console.log( evt );
            appendMsg( evt.data );
            autoScroll();
        }

        debugWs.onclose = function ( evt ) {
            //try to reconnect in 1 seconds
            appendMsg( 'debugWs disconneted (' + evt.code + ')' );
            setTimeout( debugWsStart, 5000 );
        };


        debugWs.onopen = function ( evt ) {
            appendMsg( 'debugWs connected (' + evt.toString() + ')' );
            debugWs.send('i debug websocket');
        };
        debugWs.onerror = function ( evt ) {
            appendMsg( 'debugWs has error (' + evt.toString() + ')' );
            setTimeout( debugWsStart, 5000 );
        };
    }

    function appendMsg( s ) {
        $( '<div class="minibox">' + s + '</div>' ).appendTo( $debugOut );
    }

    function autoScroll() {
        //window.scrollTo( 0, 1000000 );
    }

    debugWsStart();
  });
</script>

