<?php
/**
 * Created by David Lents <david@captina.net>
 * Date: 2014-02-04
 * Time: 11:46 PM
 * Created with PhpStorm
 */
// TODO: should go in another file

trait Debugger {
    protected $DEBUG = false;

    public function debugOn() {
        $this->DEBUG = true;
    }

    public function debugOff() {
        $this->DEBUG = false;
    }

    public function debugging() {
        return $this->DEBUG;
    }

    /**
     * Write to error log if debugging is enabled
     * @param string $log_msg
     * @param int    $log_level
     *
     * @return bool
     */
    public function debugLog($log_msg = 'None', $log_level = LOG_DEBUG) {
        if ($this->DEBUG) {
            error_log($log_msg, $log_level);
            return true;
        }
        return false;
    }
}
