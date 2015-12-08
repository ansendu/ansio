<?php
/**
 * User: ansen.du
 * Date: 15-11-26
 */
namespace ANSIO;

class Debug
{
    private static $DEBUG_TRACE = false;

    public static function exportBytes($str, $all = FALSE)
    {
        return preg_replace_callback(
            '~' . ($all ? '.' : '[^<> =\\"\\\'A-Za-z\d\.\{\}$:;\-_/\\\\]') . '~s',
            function ($m) use ($all) {
                if (!$all) {
                    if ($m[0] == "\r") {
                        return "\n" . '\r';
                    }

                    if ($m[0] == "\n") {
                        return '\n';
                    }
                }

                return sprintf('\x%02x', ord($m[0]));
            }, $str);
    }

    public static function backtrace()
    {
        ob_start();
        debug_print_backtrace();
        $dump = ob_get_contents();
        ob_end_clean();

        return $dump;
    }


    /**
     * Send print to terminal.
     */
    private static function _log($msgType, $args)
    {
        if (!Config::get('debug_mode', 0)) {
            return;
        }

        if (count($args) == 1) {
            $msg = is_scalar($args[0]) ? $args[0] : self::dump($args[0]);
        } else {
            $msg = self::dump($args);
        }
        $mt = explode(' ', microtime());
        if (self::$DEBUG_TRACE) {
            $trace = self::getTrace();
        } else {
            $trace = array();
        }

        $msg = ' ' . $msg . " mem: " . Utils::convertSize(memory_get_usage()) . "\n";
        Terminal::drawStr('[' . Daemon::$pid . '][' . date('H:i:s', $mt[1]) . '.' . sprintf('%06d', $mt[0] * 1000000) . ']', 'default');

        if ($msgType == 'debug') {
            Terminal::drawStr($msg, 'magenta');
        } else if ($msgType == 'log') {
            Terminal::drawStr($msg, 'lightgray');
        } else if ($msgType == 'error') {
            Terminal::drawStr($msg, 'red');
        } else if ($msgType == 'info') {
            Terminal::drawStr($msg, 'brown');
        } else {
            Terminal::drawStr($msg, 'default');
        }
        //echo "\n";
        !empty($trace) && Terminal::drawStr("\t" . implode(" <-- ", $trace) . "\n");
    }

    private static function getTrace()
    {
        $traces = debug_backtrace();
        // only display 2 to 6 backtrace
        for ($i = 2, $n = count($traces); $i < $n && $i < 7; $i++) {
            //for ($i = 3, $n = count($traces); $i < $n; $i++){
            $trace = $traces[$i];
            if (isset($trace['type'])) {
                $callInfo = $trace['class'] . $trace['type'] . $trace['function'] . '()';
            } else {
                $callInfo = 'internal:' . $trace['function'] . '()';
            }
            if (isset($trace['file'])) {
                $fileInfo = str_replace(ANSPHP::getRootPath() . '/', '', $trace['file']) . ':' . $trace['line'];
            } else {
                $fileInfo = '';
            }
            //$traces_data[] = $fileInfo . " " . $callInfo;
            $traces_data[] = $callInfo . " " . $fileInfo;
        }
        return $traces_data;
    }

    public static function dump()
    {
        ob_start();

        foreach (func_get_args() as $v) {
//            var_dump($v);
            print_r($v);
        }

        $dump = ob_get_contents();
        ob_end_clean();

        return $dump;
    }

    public static function log()
    {
        self::_log('log', func_get_args());
    }

    public static function info()
    {
        self::_log('info', func_get_args());
    }

    public static function debug($a)
    {
        self::_log('debug', func_get_args());
    }

    public static function error()
    {
        self::_log('error', func_get_args());
    }

    /*
     * Net event log
     */
    public static function netEvent()
    {
        self::_log('log', func_get_args());
    }

    public static function netErrorEvent()
    {
        self::_log('error', func_get_args());
    }

    public static function stdin()
    {
        self::_log('debug', func_get_args());
    }

    public static function netModuleEvent()
    {
        self::_log('log', func_get_args());
    }

    public static function timerEvent()
    {
        self::_log('log', func_get_args());
    }

    public static function errorEvent()
    {
        self::_log('error', func_get_args());
    }
}
