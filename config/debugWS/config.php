<?php
use ANSIO\ANSIO;

$config = array(
    'time_zone' => 'Asia/Shanghai',
    'debug_mode' => 1,
    'memory_limit' => "500M",
    'socket_server_class' => "socket\\server\\debugWS",
    'host' => '0.0.0.0',
    'port' => '8888',
);

return $config;