<?php
/**
 * Created by David Lents <david@captina.net>
 * Date: 2014-02-01
 * Time: 6:09 PM
 * Created with PhpStorm
 */


class XmlDataSequence {

    protected $node;
    protected $nodeName;// ??
    protected $_elements;
    protected $_elementData;
    protected $dtXsd;
    protected $dtDOM;
    protected $dtXpath;


    /**
     * @param DOMElement|DOMNode $elementNode
     */
    public function __construct($elementNode) {
        if(empty($elementNode)) {
            trigger_error(__METHOD__);
        }
        $this->node = $elementNode;
        // @link http://www.w3.org/2001/XMLSchema-datatypes.xsd
        $this->dtXsd = __DIR__ . '/xsd/XMLSchema-datatypes.xsd';
        $this->dtDOM = new DOMDocument();
        $this->dtDOM->preserveWhiteSpace = false;
        $this->dtDOM->formatOutput = false;
        $this->dtDOM->load($this->dtXsd);
        $this->dtXpath = new DOMXPath($this->dtDOM);
        $this->_elementData = array();
        $this->_elements = array();
        $this->_parseSequence();
        // error_log('Elements: ' . print_r($this->elements, true)); // DBG
        // error_log('   ' . __METHOD__ . " node: '" . $elementNode->getAttribute('type') . "'");
    }

    public function __set($elementName, $value) {
        //if(array_key_exists($elementName, $this->data)) {
            $this->_elementData[$elementName] = $value;
        //}
        // $db_bt = debug_backtrace(false, 5);
        //error_log('   == Backtrace: ' . print_r($db_bt, true));
    }

    public function __get($elementName) {
        // error_log(__METHOD__ . "  - Get value of '$elementName' (" . print_r($this->_elementData, true) . ')');
        if(array_key_exists($elementName, $this->_elementData)) {
            error_log("'$elementName' =" . $this->_elementData[$elementName] . ')');
            return $this->_elementData[$elementName];
        }
        else {
            return null;
        }
    }

    public function hasElement($elementName) {
        return (array_key_exists($elementName, $this->_elements) && is_array($this->_elements[$elementName]));
    }

    public function isDataElement($elementName) {
        return ($this->hasElement($elementName) && $this->getType($elementName) === 'simpleType');
    }

    public function getType($elementName) {
        // error_log(__METHOD__ . "  - Get type of '$elementName' " . '($this->_elements[' . "'$elementName']['type'])");
        if (array_key_exists($elementName, $this->_elements)) {
            return $this->_elements[$elementName]['type'];
        }
        return null;
    }

    public function getElements() {
        // error_log(__METHOD__ . ' returning: ' . print_r($this->elements, true)); // DBG
        return $this->_elements;
    }

    public function getData() {
        return $this->_elementData;
    }
    /**
     * @param DOMNodeList|DOMElement $elementNode
     *
     * @return ArrayObject
     * TODO: redo this, see lz_agreeToLicense.php
     */
    public function _parseSequence() { // TODO: might should be protected method
        // DOMNodeList: http://php.undmedlibrary.org/manual/en/domnodelist.item.php
        //  + also an interesting DOMNodeListIterator class in the comments
        //  + Look into DOMNamedNodeMap: http://php.undmedlibrary.org/manual/en/class.domnamednodemap.php

        // $ups = new UpsHelper();
        // $sequence = [];
        // error_log(__METHOD__ . ' with node type: ' . get_class($this->node) . ' :' . $this->node->localName);
        if ($this->node->hasChildNodes()) {
            $sequenceIterator = new XmlElementIterator($this->node);

            // The license Xsds specify this field with minOccurs="0", but requests are rejected unless they contain it
            //  + so I'm adding it here, there may be others, and there's probably a better way to handle it.
            //  + TODO: Take this up with UPS developer support
            //  + OR, perhaps best to simply rely on the data we have to determine what to include

            foreach ($sequenceIterator->getRecursiveIterator() as $element) {
                // $element may be DOMNodeList or DOMElement, our iterator handle either case
                if ($element->localName === 'choice') {
                    // Choice of elements, meaning you can pick which one to use
                    // for now we're manually choosing, via $ups->rejectedChoiceElements below
                    // continue;
                }
                $name = $element->getAttribute('name');

                // minOccurs="0" essentially means this element is optional
                // They break their own xsd with at least one element, see $requiredOptionalElements above
                if ($element->hasAttribute('minOccurs')
                    and $element->getAttribute('minOccurs') == '0'
                    // and !in_array($name, $ups->requiredOptionalElements)
                ) {
                    // continue;
                    // TODO: flag this
                }

                $type = $element->getAttribute('type');

                // strip off namespace if present
                if ($localType = strrchr($type, ':')) {
                    $type = substr($localType, 1);
                }
                // error_log("Type: $type\n");

                if ( $this->_isSimpleType($type) ) {
                    $type = 'simpleType';
                    $this->$name = null;
                }
                $this->_elements[$name] = [
                    'name' => $name,
                    'type' => $type
                ];
            }
        }
        else {
            $element = $this->node;
            $name = $element->getAttribute('name');
            $type = $element->getAttribute('type');
            // strip off namespace if present
            if ($localType = strrchr($type, ':')) {
                $type = substr($localType, 1);
            }
            // error_log("Type: $type\n");

            if ( $this->_isSimpleType($type) ) {
                $type = 'simpleType';
                $this->$name = null;
            }
            $this->_elements[$name] = [
                'name' => $name,
                'type' => $type
            ];
        }
    }

    protected function _isSimpleType($type) {
        // $xPath->registerNamespace('', 'http://www.w3.org/2001/XMLSchema-datatypes');
        // $xPath->registerNamespace('', 'http://www.w3.org/2001/XMLSchema');
        $xQuery = "//child::*[local-name() = 'schema']/*[local-name() = 'simpleType' and @name = '$type']";
        $found = $this->dtXpath->query($xQuery);
        if ($found->length === 0) {
            return false;
        }
        else {
            return true;
        }

    }
}
