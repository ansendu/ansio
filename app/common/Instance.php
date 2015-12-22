<?php
/**
 * User: ansen.du
 * Date: 15-12-15
 */
namespace common;

use ANSIO\Factory;
use ANSIO\Config;

class instance
{

    public static $mysql;
    public static $pdo;

    public static function getMysql()
    {
        return Factory::getInstance("ANSIO\\MysqlClient");
    }

    public static function getPdo()
    {
        $user = Config::getField('mysql', 'user');
        $pwd = Config::getField('mysql', 'pwd');
        $host = Config::getField('mysql', 'host');
        $db = Config::getField('mysql', 'db');
        $port = Config::getField('mysql', 'port');

        $pdoConfg = array(
            'dsn' => "mysql:host={$host};port={$port}",
            'user' => $user,
            'pass' => $pwd,
            'dbname' => $db,
        );
        return Factory::getInstance("ANSIO\\Pdo", $pdoConfg);
    }


}