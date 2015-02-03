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
class XmlElementIterator extends ArrayIterator implements RecursiveIterator {
    // Works on DOMNodeList or DOMElement
    public function __construct($element) {
        $nodes       = array();
        $elementType = get_class($element);
        switch ($elementType) {
            // 2015-01-22 - added code to skip nodes that are DOMComment
            case 'DOMNodeList':
                foreach ($element as $node) {
                    if (get_class($node) == 'DOMComment') {
                        continue;
                    }
                    $nodes[] = $node;
                }
                break;
            case 'DOMElement':
                if ($element->hasChildNodes()) {
                    foreach ($element->childNodes as $node) {
                        if (get_class($node) == 'DOMComment') {
                            // error_log("Comment in DOMElement: " . var_export($node, true));
                            continue;
                        }
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
