<?php
/**
 * User: ansen.du
 * Date: 15-11-26
 */
namespace ANSIO;

class ANSIO
{
    /**
     * 项目目录
     * @var string
     */
    private static $rootPath;
    /**
     * 配置目录
     * @var string
     */
    private static $configPath = 'default';
    private static $appPath = 'app';
    private static $zPath;
    private static $libPath = 'lib';
    private static $classPath = array();
    public static $sArea = '';

    public static function getRootPath()
    {
        return self::$rootPath;
    }

    public static function setRootPath($rootPath)
    {
        self::$rootPath = $rootPath;
    }

    public static function getConfigPath()
    {
        $dir = self::getRootPath() . DS . 'config' . DS . self::$configPath;
        if (\is_dir($dir)) {
            return $dir;
        }
        return self::getRootPath() . DS . 'config' . DS . 'default';
    }

    public static function setConfigPath($path)
    {
        self::$configPath = $path;
    }

    public static function getsArea()
    {
        return self::$sArea;
    }

    public static function setsArea($sArea = '')
    {
        self::$sArea = $sArea;
    }

    public static function checkConfigPath()
    {
        $path = self::getRootPath() . DS . 'config' . DS . self::$configPath;
        if (!file_exists($path . DS . 'config.php')) {
            return false;
        }
        return true;
    }

    public static function getAppPath()
    {
        return self::$appPath;
    }

    public static function setAppPath($path)
    {
        self::$appPath = $path;
    }

    public static function getZPath()
    {
        return self::$zPath;
    }

    public static function getLibPath()
    {
        return self::$libPath;
    }

    final public static function autoLoader($class)
    {
        if (isset(self::$classPath[$class])) {
            require self::$classPath[$class];
            return;
        }
        $baseClasspath = \str_replace('\\', DS, $class) . '.php';
        $libs = array(
            self::$rootPath . DS . self::$appPath,
            self::$zPath,
            self::$libPath
        );
        foreach ($libs as $lib) {
            $classpath = $lib . DS . $baseClasspath;
            if (\is_file($classpath)) {
                self::$classPath[$class] = $classpath;
                require "{$classpath}";
                return;
            }
        }
    }

    public static function run($rootPath)
    {
        if (!defined('DS')) {
            define('DS', DIRECTORY_SEPARATOR);
        }
        self::$zPath = \dirname(__DIR__);
        self::setRootPath($rootPath);

        if (!isset($_SERVER['argv'][1])) {
            die('plz set config path.');
        }
        $configPath = $_SERVER['argv'][1];
        self::setConfigPath($configPath);
        self::setsArea($configPath); //sArea

        \spl_autoload_register(__CLASS__ . '::autoLoader');

        if (!self::checkConfigPath()) {
            die("wrong config path[{$configPath}]");
        }
        Config::load(self::getConfigPath());

        if (Config::get('debug_mode', 1)) {
//            error_reporting(E_ALL & ~E_NOTICE | E_STRICT);
            error_reporting(E_ALL & ~E_NOTICE & ~E_USER_NOTICE & ~E_USER_WARNING | E_STRICT);
            ini_set('display_errors', 1);
        } else {
            error_reporting(0);
            ini_set('display_errors', 0);
        }
        $appPath = Config::get('app_path', self::$appPath);
        self::setAppPath($appPath);
        //@todo set exception error handle...
        $timeZone = Config::get('time_zone', 'Asia/Shanghai');
        \date_default_timezone_set($timeZone);
        ini_set("memory_limit", Config::get('memory_limit', -1));

        Daemon::init();

    }
}
