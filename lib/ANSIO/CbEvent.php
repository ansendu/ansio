<?php
/**
 * User: ansen.du
 * Date: 15-11-27
 */
namespace ANSIO;

class CbEvent
{

    public $cb;
    public $args;
    public $cArgs;

    public function __construct($cb = null, $cbArgs = null)
    {
        $this->cb = $cb;
        $this->args = $cbArgs;
    }

    public function doCall()
    {
        $this->cArgs = func_get_args();
        if (is_callable($this->cb)) {
            return call_user_func($this->cb, $this);
        } else {
            //@todo 增加 cb 定义的文件以及行信息，方便调试
            trigger_error('CbEvent::cb is not callbacks', E_USER_WARNING);
        }
    }

}
