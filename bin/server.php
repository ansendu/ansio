<?php
/**
 * User: ansen.du
 * Date: 15-11-26
 */
use ANSIO\ANSIO;

$rootPath = dirname(__DIR__);
//require dirname($rootPath) . '/lib/ANSIO/ANSIO.php';
require $rootPath . '/lib/ANSIO/ANSIO.php';
ANSIO::run($rootPath);