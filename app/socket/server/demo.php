<?php
/**
 * User: ansen.du
 * Date: 15-12-3
 */
namespace socket\server;
use ANSIO\AsyncServer;
use ANSIO\Factory;
use ANSIO\Debug;
use ANSIO\SocketSession;
use ANSIO\Config;
use ANSIO\ColorCli;
use ANSIO\TimerEvent;
use ANSIO\MysqlClient;
use ANSIO\CbEvent;


class demo extends AsyncServer
{
    public $debugServer = NULL;
    public $debugClientConnIdPool = array();

    public static $mysql;
    public static $globalData;


    protected function init()
    {
        // 运行2个服务实例分别监听不同的端口 @todo 也可以在daemon中运行多个实例
        $this->debugServer = new debugWS('0.0.0.0', 8888);
//        $this->debugServer->bindSocket();

        $this->bindSocket(Config::get('host'), Config::get('port'));
    }

    protected function onAccepted($connId, $addr, $port)
    {
        Debug::netEvent(get_class($this) . '::' . __METHOD__ . '(' . $connId . ') invoked.');
        $this->setConnSocketSession($connId, new demoSocketSession($this));
    }

    public function dispatch($connId, $data)
    {
        $this->writeDebugData(" input: connId[{$connId}]" . $data);


        var_dump($connId, $data);
        //mysql async demo
        self::mysqlDemo();
        $this->cbeventDemo(0);


        $this->writeDebugData(" output connId[{$connId}] result output [xxx].");

    }

    public function writeDebugData($s, $connId = NULL)
    {
        static $i = 1;

        $i++;
        $mt = explode(' ', microtime());
        $s = date('H:i:s', $mt[1]) . '.' . sprintf('%06d', $mt[0] * 1000000) . $s;
        debugWSHandle::sendDebugData($s);
    }

    public static function mysqlDemo()
    {

        self::$mysql = new MysqlClient();
        $sql = "select * from school where id = 100000";

        self::$mysql->getConnection(function ($sess) use ($sql) {
            $sess->query($sql, function ($sess, $ok) {
                foreach ($sess->resultRows as $val) {
                    self::$globalData['school'][$val['id']] = $val;
                }
                print_r(self::$globalData);
            });
        });
        Debug::log('async mysql has been executed.');

    }

    public function cbeventDemo($connId)
    {
        $cbEvent = new CbEvent(array($this, 'cbEventCall'), array($connId));

        $objName = 'industry';
        $keyName = 'id';
        $sql = "select * from $objName where $keyName = 1";
        self::loadDataBysql($objName, $keyName, $sql, $cbEvent, $args = null);

    }

    public function cbEventCall($cbEvent)
    {
        echo 'cbEvent called.' . "\n";
//        print_r($cbEvent->cArgs);
        print_r(self::$globalData);
    }

    private static function loadDataBysql($tableName, $keyName, $sql, $cbEvent = null)
    {
        self::$mysql->getConnection(function ($sess) use ($sql, $tableName, $keyName, $cbEvent) {
            $sess->query($sql, function ($sess, $ok) use ($tableName, $keyName, $cbEvent) {
                foreach ($sess->resultRows as $val) {
                    self::$globalData[$tableName][$val[$keyName]] = $val;
                }
                $cbEvent->tableName = $tableName;
                if ($cbEvent instanceof CbEvent) {
                    $cbEvent->doCall($sess, $ok);
                }
            });
        });
    }

}


//@todo socketSession可以分离开来单独对应
class demoSocketSession extends SocketSession
{
    public function onWrite()
    {
        Debug::netModuleEvent(get_class($this) . '::' . __METHOD__ . '(' . $this->connId . ') invoked. ');
    }

    public function onReadEOF()
    {
        parent::onReadEOF();
    }

    public function stdin($buf)
    {
        $this->buf .= $buf;

        // echo '~~debug~~' | nc 127.0.0.1 12346
        // nc 127.0.0.1 12346  stdin ~~debug~~
        // 以上两行命令表现不同，后面一条在敲回车的时候不停出现 connId [3] debug accepted.
        // 这是因为第一条已经 readEof
        if (strpos($this->buf, "~~debug~~") !== false) {
            $this->writeln('connId [' . $this->connId . '] debug accepted.');
            $this->appInstance->debugClientConnIdPool[$this->connId] = $this->connId;
            return;
        }


//        {"c":"test\\test","p":{"argu1":123,"argu2":456}}~~code~end~~
        //为了防止在一个 stdin 中出现两个命令包，所以这里改为 strpos
        //todo: 优化，参考 node server, 使用 startPos 优化， 只在最后对 $this->buf 做一次 substr()
        while (($p = strpos($this->buf, "~~code~end~~")) !== FALSE) {
            $code = substr($this->buf, 0, $p);
            $this->buf = substr($this->buf, $p + strlen('~~code~end~~'));

            $this->appInstance->dispatch($this->connId, $code);
        }
    }


}