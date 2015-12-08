<?php
/**
 * User: ansen.du
 * Date: 15-12-3
 */
namespace ANSIO;
class Utils
{
    public static function binarySubstr($s, $p, $l = NULL)
    {
        if ($l === NULL) {
            $ret = substr($s, $p);
        } else {
            $ret = substr($s, $p, $l);
        }

        if ($ret === FALSE) {
            $ret = '';
        }

        return $ret;
    }

    public static function ucs_parse_str($s, &$array)
    {
        static $cb;
        if ($cb === NULL) {
            $cb = function ($m) {
                return urlencode(html_entity_decode('&#' . hexdec($m[1]) . ';', ENT_NOQUOTES, 'utf-8'));
            };
        }

        if (
            (stripos($s, '%u') !== false)
            && preg_match('~(%u[a-f\d]{4}|%[c-f][a-f\d](?!%[89a-f][a-f\d]))~is', $s, $m)
        ) {
            $s = preg_replace_callback('~%(u[a-f\d]{4}|[a-f\d]{2})~i', $cb, $s);
        }

        self::ucs_parse_str($s, $array);
    }

    public static function getDebugTime()
    {
        $mt = explode(' ', microtime());
        return date('His', $mt[1]) . '.' . sprintf('%06d', $mt[0] * 1000000);
    }

    public static function convertSize($size)
    {
        static $sizeName = array("Byte", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB");
        return round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . $sizeName[$i];
    }


}

?>