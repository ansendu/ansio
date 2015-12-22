<?php
/**
 * User: ansen.du
 * Date: 15-12-15
 */
namespace common;

use ANSIO\Debug;
use ANSIO\CbEvent;
use common\Instance;

class SqlPool
{
    public static $baseSqlPool = array();

    public static function prepareObjSqlPool($sqlPool)
    {
        $sqlOut = array();
        foreach ($sqlPool as $key => $sql) {
            if (is_array($sql)) {
                if (strpos($key, 'u_') !== false) {
                    if (isset($sql['dbChanged'])) {
                        // update sql
                        $sqlStr = "update " . $sql['tableName'] . ' set ' . implode(',', $sql['dbChanged']) . ' where ' . $sql['keyName'] . "='" . $sql['id'] . "'";
                        $sqlOut[] = $sqlStr;
                    }
                } else if (strpos($key, 'i_') !== false) {
                    if (isset($sql['dbChanged'])) {
                        // insert sql
                        $sqlOut[] = $sql['dbChanged'];
                    }
                } else {
                    Debug::error(__CLASS__ . '::' . __METHOD__ . '() [' . print_r($sql, true) . '] unknown sql type.');
                }
            } else {
                $sqlOut[] = $sql;
            }
        }
        return $sqlOut;

    }

    public static function runUpdateQuery($sqlPool, $cbEvent = null)
    {
        Instance::getMysql()->getConnection(function ($sess) use ($sqlPool, $cbEvent) {
            foreach ($sqlPool as $sql) {
                $sess->query($sql, function ($sess, $ok) use ($sql, $cbEvent) {
                    if ($ok) {
                        Debug::log(__METHOD__ . '() [' . $sql . '] complete run.');
                    } else {
                        Debug::error(__METHOD__ . '() [' . $sql . '] has error[' . $sess->errno . ':' . $sess->errmsg . '].');
                    }
                    if ($cbEvent instanceof CbEvent) {
                        $cbEvent->doCall($sess, $ok);
                    }
                });
            }
        });
    }


}