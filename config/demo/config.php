<?php
use ANSIO\ANSIO;

$config = array(
    'time_zone' => 'Asia/Shanghai',
    'debug_mode' => 1,
    'memory_limit' => "500M",
    'socket_server_class' => "socket\\server\\demo",
    'host' => '0.0.0.0',
    'port' => '9999',
);

$publicConfig = array('mysql.php');
foreach ($publicConfig as $file) {
    $file = ANSIO::getConfigPath() . DS . $file;
    $config += include "{$file}";
}
return $config;