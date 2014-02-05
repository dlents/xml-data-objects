<?php
/**
 * Created by David Lents <david@captina.net>
 * Date: 2014-01-19
 * Time: 3:17 AM
 * Created with PhpStorm
 */

/**
 * PHP's DOM classes are recursive but don't provide an implementation of
 * RecursiveIterator. This class provides a RecursiveIterator for looping over DOMNodeList
 * From comments at http://www.php.net/manual/en/class.domnodelist.php
 */


class DOMElementRecursiveIterator extends ArrayIterator implements RecursiveIterator {
    // Works on DOMNodeList or DOMElement
    public function __construct ($element) {
        $nodes = array();
        $elementType = get_class($element);
        switch ($elementType) {
            case 'DOMNodeList':
                foreach($element as $node) {
                    $nodes[] = $node;
                }
                break;
            case 'DOMElement':
                if ($element->hasChildNodes()) {
                    foreach($element->childNodes as $node) {
                        $nodes[] = $node;
                    }
                }
                break;
            default:
                trigger_error(__METHOD__ . "() called with an instance of '$elementType' as an argument"); // DBG
                break;
        }
        parent::__construct($nodes);
    }

    public function getRecursiveIterator() {
        return new RecursiveIteratorIterator($this, RecursiveIteratorIterator::SELF_FIRST);
    }

    public function hasChildren() {
        return $this->current()->hasChildNodes();
    }

    public function getChildren() {
        return new self($this->current()->childNodes);
    }
}

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
