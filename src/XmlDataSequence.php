<?php

/**
 * Created by David Lents <david@captina.net>
 * Date: 2014-02-01
 * Time: 6:09 PM
 * Created with PhpStorm
 */
class XmlDataSequence {

    use Debugger;
    protected $node;
    protected $parentName;// ??
    protected $_elements;
    protected $_elementData;
    protected $schemaHelper;
    protected $chosen;


    /**
     * @param DOMElement|DOMNode $elementNode
     */
    public function __construct($elementNode) {
        if (empty($elementNode)) {
            trigger_error(__METHOD__);
        }
        $this->node = $elementNode;

        $this->parentName   = $elementNode->parentNode->getAttribute('name');
        $this->schemaHelper = new XsdSchemaHelper();
        $this->_elementData = array();
        $this->_elements    = array();
        $this->chosen       = false;
        $this->_parseSequence();

        $this->debugOn();
        // $this->debugLog('Elements: ' . print_r($this->elements, true)); // DBG
        // $this->debugLog('   ' . __METHOD__ . " node: '" . $elementNode->getAttribute('type') . "'");
    }

    public function name() {
        return $this->nodeName;
    }

    public function __set($elementName, $value) {
        // if(array_key_exists($elementName, $this->_elementData)) {
        if (!empty($elementName) && $this->isDataElement($elementName)) {
            $this->_elementData[$elementName] = $value;
            $this->chosen                     = true;
            // $this->debugLog(__METHOD__ . "() - '$elementName' = '{$this->_elementData[$elementName]}'" );
        }
        // $db_bt = debug_backtrace(false, 5);
        //$this->debugLog('   == Backtrace: ' . print_r($db_bt, true));
    }

    public function __get($elementName) {
        // $this->debugLog(__METHOD__ . "  - Get value of '$elementName' (" . print_r($this->_elementData, true) . ')');
        if (array_key_exists($elementName, $this->_elementData)) {
            //$this->debugLog(__METHOD__ . "() - '$elementName' = '{$this->_elementData[$elementName]}'" );
            return $this->_elementData[$elementName];
        }
        else {
            return null;
        }
    }

    public function isChosen() {
        return $this->chosen;
    }

    public function hasElement($elementName) {
        return (array_key_exists($elementName, $this->_elements) && is_array($this->_elements[$elementName]));
    }

    public function isDataElement($elementName) {
        return ($this->hasElement($elementName) && $this->getType($elementName) === 'simpleType');
    }

    public function getType($elementName) {
        // $this->debugLog(__METHOD__ . "  - Get type of '$elementName' " . '($this->_elements[' . "'$elementName']['type'])");
        if (array_key_exists($elementName, $this->_elements)) {
            return $this->_elements[$elementName]['type'];
        }
        return null;
    }

    public function getElements() {
        // $this->debugLog(__METHOD__ . ' returning: ' . print_r($this->elements, true)); // DBG
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
        // $this->debugLog(__METHOD__ . ' with node type: ' . get_class($this->node) . ' :' . $this->node->localName);
        if ($this->node->hasChildNodes()) {
            $sequenceIterator = new XmlElementIterator($this->node);

            // The license Xsds specify this field with minOccurs="0", but requests are rejected unless they contain it
            //  + so I'm adding it here, there may be others, and there's probably a better way to handle it.
            //  + TODO: Take this up with UPS developer support
            //  + OR, perhaps best to simply rely on the data we have to determine what to include

            foreach ($sequenceIterator->getRecursiveIterator() as $element) {
                // $element may be DOMNodeList, DOMElement, our iterator handle either case
                // but it can also be DOMComment, in which case we skip
                if (!method_exists($element, 'hasAttribute')) {
                    continue;
                }

                // 2015-01-22, first encounter with this kind of element in UPS LocatorRequest.xsd main sequence:
                //    <xsd:element ref="Request"/>
                // so...
                $type = 'UnknownType';
                if ($element->hasAttribute('name')) {
                    $name = $element->getAttribute('name');
                }
                elseif ($element->hasAttribute('ref')) {
                    $name = $element->getAttribute('ref');
                    $type = $name;
                }
                else {
                    error_log('Skipping element with no name or ref attribute');
                    continue;
                }

                if ($element->hasAttribute('type')) {
                    $type = $element->getAttribute('type');
                }

                if ($element->localName === 'choice') {
                    // Choice of elements, meaning you can pick which one to use
                    // for now we're manually choosing, via $ups->rejectedChoiceElements below
                    continue;
                }

                // minOccurs="0" essentially means this element is optional
                // They break their own xsd with at least one element, see $requiredOptionalElements above
                if ($element->hasAttribute('minOccurs') && $element->getAttribute('minOccurs') === '0') {
                    // continue;
                    // TODO: flag this
                }

                if ($this->schemaHelper->isSimpleType($type)) {
                    $type        = 'simpleType';
                    $this->$name = '';
                }
                $this->_elements[$name] = [
                    'name' => $name,
                    'type' => $type
                ];
            }
        }
        else {
            $element = $this->node;
            $type    = 'UnknownType';
            if ($element->hasAttribute('name')) {
                $name = $element->getAttribute('name');
            }
            elseif ($element->hasAttribute('ref')) {
                $name = $element->getAttribute('ref');
                $type = $name;
            }
            else {
                error_log('Skipping element with no name or ref attribute');
                return;
            }

            if ($element->hasAttribute('type')) {
                $type = $element->getAttribute('type');
            }

            if ($this->schemaHelper->isSimpleType($type)) {
                $type        = 'simpleType';
                $this->$name = null;
            }
            $this->_elements[$name] = [
                'name' => $name,
                'type' => $type
            ];
        }
    }
}
