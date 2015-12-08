<?php
/**
 * User: ansen.du
 * Date: 15-11-26
 */
namespace ANSIO;

class Daemon
{
    public static $breakEventLoop = false;
    public static $pid;
    public static $runName;
    public static $version = 0.1;
    public static $eventBase;
    private static $commands = array(
        'start', 'stop', 'reload', 'restart', 'status'
    );
    public static $currentSocketId = 1;
    public static $currentConnId = 1;
    public static $currentTimerEventId = 1;

    public static $timerEventPool = array();
    public static $connLastContactPool = array();
    public static $connCurrWriteStagePool = array();

    public static $signals = array(
        SIGHUP => 'SIGHUP',
        SIGINT => 'SIGINT',
        SIGQUIT => 'SIGQUIT',
        SIGILL => 'SIGILL',
        SIGTRAP => 'SIGTRAP',
        SIGABRT => 'SIGABRT',
        7 => 'SIGEMT',
        SIGFPE => 'SIGFPE',
        SIGKILL => 'SIGKILL',
        SIGBUS => 'SIGBUS',
        SIGSEGV => 'SIGSEGV',
        SIGSYS => 'SIGSYS',
        SIGPIPE => 'SIGPIPE',
        SIGALRM => 'SIGALRM',
        SIGTERM => 'SIGTERM',
        SIGURG => 'SIGURG',
        SIGSTOP => 'SIGSTOP',
        SIGTSTP => 'SIGTSTP',
        SIGCONT => 'SIGCONT',
        SIGCHLD => 'SIGCHLD',
        SIGTTIN => 'SIGTTIN',
        SIGTTOU => 'SIGTTOU',
        SIGIO => 'SIGIO',
        SIGXCPU => 'SIGXCPU',
        SIGXFSZ => 'SIGXFSZ',
        SIGVTALRM => 'SIGVTALRM',
        SIGPROF => 'SIGPROF',
        SIGWINCH => 'SIGWINCH',
        28 => 'SIGINFO',
        SIGUSR1 => 'SIGUSR1',
        SIGUSR2 => 'SIGUSR2',
    );

    // mark, when client close, must unset $connAppInstancePool[$connId].
    public static $connAppInstancePool = array();

    /*
     * array('websockets' => array(
     *                             'regService' => $obj,
     *                             'nodeClient' => $obj
     *                             ),
     *       'app2'       => array(
     *                             'app2' => $obj,
     *                             ),
     * )
     * 存储当前 daemon 下运行的所有 app 实例(服务器注册服务，战场服务，玩家服务等等)
     */
    public static $appInstances = array();

    public static function getAppInstanceByName($name)
    {

    }

    public static function getNextSocketId()
    {
        // todo: change to timestamp 防止链接不停的增加，整数溢出
        return self::$currentSocketId++;
    }

    public static function getNextTimerEventId()
    {
        // todo: change to timestamp 防止链接不停的增加，整数溢出
        return self::$currentTimerEventId++;
    }

    public static function getNextConnId()
    {
        // todo: change to timestamp 防止链接不停的增加，整数溢出
        return self::$currentConnId++;
    }

    public static function init()
    {
        Daemon::$eventBase = event_base_new();

        $args = $_SERVER['argv'];
        if (!isset($args[1]) || !isset($args[2])) {
            Terminal::drawStr('usage: php xxx.php sArea (start|stop|restart|reload|status)' . "\n");
            exit(-1);
        }
        // todo run multi app in one daemon.

        $sArea = $args[1];
        $command = $args[2];
        $appName = Config::get('socket_server_class', null, true);
        Daemon::$runName = $appName;

        $runAppInstance = array(
            $appName => $appName,
            //'testClient' => 'testClient',
            //'slaveService' => 'slaveService',
        );

        if ($command == 'start') {
            // fork later
            self::$pid = posix_getpid();

            Debug::log('start');

            foreach ($runAppInstance as $appName) {
                $obj = new $appName();
            }

            while (!Daemon::$breakEventLoop) {
                event_base_loop(daemon::$eventBase, EVLOOP_ONCE);
                // 清空本次写状态数组
                gc_collect_cycles();
                //daemon::debug('<== evet_base_loop() ending');
            }
        } elseif ($command == 'stop') {
            // cat pid, stop
        } elseif ($command == 'status') {
            // cat pid, show status
        } elseif ($command == 'restart') {
            //self::stop();
            //self::start();
        }
    }


    public static function autoGC()
    {
        $startTime = getDebugTime();
        $beforeSize = memory_get_usage();
        gc_enable();
        gc_collect_cycles();
        gc_disable();
        $freeSize = $beforeSize - memory_get_usage();
        $useSec = number_format((getDebugTime() - $startTime) * 1000, 3, '.', ',');
        //logConfig::runtimeEvent && daemon::debug( __CLASS__ . '::' . __METHOD__ . ' free memory ' . ( $freeSize > 0 ? '' : '-' ) . convertSize( abs( $freeSize ) ) . ', use ' . $useSec . 'ms' );
    }

    public static function closeTimeoutConnection()
    {
//        logConfig::runtimeEvent && daemon::log( __CLASS__ . '::' . __METHOD__ . ' invoked. ' );
        $closeTime = time() - 300;
        foreach (self::$connLastContactPool as $connId => $lastContactTime) {
            if ($lastContactTime < $closeTime) {
                if (isset(self::$connAppInstancePool[$connId]) && self::$connAppInstancePool[$connId] instanceof asyncBase) {
                    //fix bug: 这里需要使用 realCloseConnection 这样才会强制关闭，否则在 WriteStage 状态的 connect 不会断掉
                    self::$connAppInstancePool[$connId]->realCloseConnection($connId);
                } else {
//                    logConfig::netErrorEvent && daemon::error( __CLASS__ . '::' . __METHOD__ . ': can not find conn[' . $connId . ']\'s appInstance.' );
                }
            }
        }
    }


}
