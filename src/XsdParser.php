<?php
/**
 * Created by David Lents <david@captina.net>
 * Date: 2014-01-19
 * Time: 3:26 AM
 * Created with PhpStorm
 * helful links:
 * @link http://en.wikibooks.org/wiki/XML_Schema
 *       http://docstore.mik.ua/orelly/xml/schema/index.htm
 *
 *
 */

// TODO: better class names:
// XsdDataObject / XsdSequence ?

class XsdParser {
    use Debugger;

    protected $xsd;
    protected $xmlDOM;
    protected $xPath;
    protected $rootName;
    protected $nodeTree;
    public $dataElements; // keep fetched elements cached

    public function __construct($xsd = '', $rootElement = '') {
        // TODO: Should allow URL argument
        if (!file_exists($xsd) || !is_readable($xsd)) {
            trigger_error(__METHOD__ . " argument '$xsd' not found or can't be read");
        }
        // Set up DOM document and xpath objects
        $this->xmlDOM = new DOMDocument();
        $this->xmlDOM->preserveWhiteSpace = false;
        $this->xmlDOM->formatOutput = true;
        $this->xmlDOM->load($xsd);
        $this->xsd = $xsd;
        $this->xPath = new DOMXPath($this->xmlDOM);
        $this->nodeTree = new XmlNodeTree();
        $this->dataElements = array();
        if (!empty($rootElement)) {
            $this->setRootElement($rootElement);
            $this->getRootDataObject();
        }
    }

    public function __destruct() {
        // $this->debugLog("dataElements:\n" . var_export($this->dataElements, true));
    }
    public function getNodeTree() {
        return $this->nodeTree;
    }

    public function setRootElement($rootName = '') {
        // $rootElement = $this->xPath->query('//xsd:element'); // TODO: how? see SDO
        // $rootElement = $this->xPath->query("/xsd:element[@name = '$rootName']");
        if (!empty($rootName)) {
            $this->rootName = $rootName;
        }
        else {
            // TODO:fix this
            $this->rootName = 'AccessLicenseAgreementRequest';
        }
    }
    public function getRootName() {
        return $this->rootName;
    }

    /**
     *
     * @return xmlDataSequence
     */
    public function getRootDataObject() {
        return $this->getDataObject($this->rootName);
    }

    /**
     * @param string $elementName
     *
     * @return xmlDataSequence
     */
    public function getDataObject($elementName = '') {
        $this->debugOn();
        $dataObject = null;
        $this->debugLog(__METHOD__ . "(): Searching for '$elementName'");
        if (array_key_exists($elementName, $this->dataElements) && is_object($this->dataElements[$elementName])) {
            $this->debugLog(" -- Found '$elementName' in existing object");
            return $this->dataElements[$elementName];
        }
        elseif (!empty($this->dataElements)) {
            $this->debugLog("-- Searching existing elements for '$elementName'");
            foreach ($this->dataElements as $seqName => $seqObject) {
                $this->debugLog("-- Searching in '$seqName' for '$elementName'");
                if ($seqObject->hasElement($elementName)) {
                    $this->debugLog(" -- Found '$elementName' in existing '$seqName' object");
                    return $seqObject;
                }
                else {
                    $this->debugLog(" -- '$elementName' not found in '$seqName' object'");
                }
            }
        }
        else {
            $this->debugLog(" -- dataElements is empty");
        }
        $elementNode = $this->_getNode($elementName);
        // $this->debugLog(" -- got node :\n" . var_export($elementNode, true));
        $dataObject = new XmlDataSequence($elementNode);
        $this->dataElements[$elementName] = $dataObject;
        // $this->debugLog("$elementName Data Elements: " . print_r($this->dataElements[$elementName], true)); // DBG
        $this->debugOff();
        // $this->debugLog(" -- dataObject now:\n" . var_export($dataObject, true));

        return $dataObject;
    }

    /**
     * @param string $elementName
     * @return DOMNode | DOMElement
     */
    public function _getNode($elementName = '') {
        $this->debugOn();
        $this->debugLog(__METHOD__ . " - searching for node '$elementName'");
        if (empty($elementName)) {
            trigger_error(__METHOD__ . ' Error: element name argument required');
        }
        // works:
        //  descendant-or-self::*[@name='AccessLicenseAgreementRequest']
        // descendant::*[@name='AccessLicenseAgreementRequest']//*[local-name()='sequence']
        // descendant::*[@name='$elementName']//*[local-name()='element']
        // To find type sequence:
        //  //*[@name='$elementType']
        // $this->debugLog("Searching for '$elementName'");
        $this->xPath = new DOMXPath($this->xmlDOM);
        $xpSequenceQuery = "/descendant::*[@name='$elementName']//*[local-name()='sequence']";
        $this->debugLog("Trying xpath query: '$xpSequenceQuery'");
        $nodeList = $this->xPath->query($xpSequenceQuery);
        $this->debugLog('First search found ' . $nodeList->length . ' nodes');
        if ($nodeList->length === 0) {
            $xpElementQuery = "/descendant::*[@name='$elementName' and local-name()='element']";
            $this->debugLog("Trying xpath query: '$xpElementQuery'");
            $nodeList = $this->xPath->query($xpElementQuery);
        }
        if ($nodeList === false) {
            // TODO: this indicates a malformed expression of invalid contextnode
            $node = null;
        }
        elseif ($nodeList->length === 0) {
            // TODO: do something here - can't find requested element
            $this->debugLog("Did not find element '$elementName'");
            $node = null;
        }
        else {
            $node = $nodeList->item(0);
        }

        //$this->debugLog(var_export($node, true));
        $this->debugOff();
        return $node;
    }

    protected function _parseNode($nodeParentName = '', $element = []) {
        // print("Processing node '{$element['name']}', type '{$element['type']}'\n");
        $nodeTree = $this->nodeTree;
        // $this->debugLog(__METHOD__ . " ($nodeParentName): " . print_r($element, true));
        $nodeName = $element['name'];
        // $this->debugLog(__METHOD__ . " Parsing '{$element['name']}', type: '{$element['type']}'");
        // already parsed
        if ($nodeTree->hasNode($nodeName)) {
            return;
        }

        // print("  : Adding node, parent: '{$nodeParentName}'\n");
        $node = new XMLNode($nodeName);
        // $node->parent = $rootNode;
        $node->parentObj = $nodeTree->$nodeParentName;
        $node->parentObj->childNodes->append($node);
        $node->parentObj->childTags->append($nodeName); // TODO: not needed


        if ($element['type'] === 'simpleType') { // leaf node, no children
            $dataSequence = $this->getDataObject($nodeName);
            // $this->debugLog("Getting value for '$nodeName");
            $value = $dataSequence->$nodeName;
            $this->debugLog("Found value for '{$nodeName}': $value"); // DBG
            $node->xmlElement = $nodeTree->xmlDoc->createElement($nodeName, $value);
            $nodeTree->addNode($nodeName, $node);
            $node->parentObj->xmlElement->appendChild($node->xmlElement);
        }
        else {
            $node->xmlElement = $nodeTree->xmlDoc->createElement($node->name);
            $nodeTree->addNode($node->name, $node);
            // recurse until node is a leaf
            // $this->debugLog(__METHOD__ . " - '$elementName' dataElements: " . print_r($this->dataElements[$elementName], true)); // DBG
            $typeSequence = $this->getDataObject($element['type']);
            // $this->debugLog($element['type'] . ': ' . print_r($typeSequence->getElements(), true));
            foreach ($typeSequence->getElements() as $childElement) {
                $this->_parseNode($nodeName, $childElement);
                if ($nodeTree->hasNode($childElement['name'])) {
                    $node->xmlElement->appendChild($nodeTree->$childElement['name']->xmlElement);
                }
            }
        }
        return;
    }

    public function saveDocument() {
        // Initialize the nodeTree
        $doc = new DOMDocument('1.1', 'UTF-8');

        // $nodeTree->treeName = $rootName; //TODO: not needed
        $this->nodeTree->xmlDoc   = $doc;
        // $nodeTree->xsdFile  = $this->xsd; // not neeeded?

        // add the top node based on the type of UPS request
        $rootNode = new XmlNode($this->rootName);
        // $node->name = $this->rootName;
        $rootNode->parentObj  = null;
        $rootNode->xmlElement = $doc->createElement($this->rootName);
        // TODO: This should have a method
        $rootNode->xmlElement->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $this->nodeTree->addNode($this->rootName, $rootNode); // TODO: nodes don't need to be double named
        $this->getRootDataObject(); // TODO: Fixme
        $rootSequence = $this->dataElements[$this->rootName]->getElements();
        // $this->debugLog(__METHOD__ . " - '{$this->rootName}' dataElements: " . print_r($this->dataElements[$this->rootName], true)); // DBG
        // $this->debugLog("Root sequence ($this->rootName)" . ': ' . print_r($rootSequence, true));
        // get top level nodes, in the order required by the xsd
        foreach ($rootSequence as $childElement) {
            $this->_parseNode($this->rootName, $childElement);
            if ($this->nodeTree->hasNode($childElement['name'])) {
                $rootNode->xmlElement->appendChild($this->nodeTree->$childElement['name']->xmlElement);
            }
        }
        // The XML is all ready to go
        $doc->appendChild($rootNode->xmlElement);
        $valid = $this->_validateDocument($doc);
        if ($valid['result'] !== 'OK') {
            return "XML Validation errors:\n" . $valid['error'];
        }
        return $doc;
    }

    protected function _validateDocument() {
        $doc = $this->nodeTree->xmlDoc;
        $ret          = array();
        $ret['error'] = '';

        libxml_use_internal_errors(true); // supress error output for manual capture

        // Validate our XML against the UPS xsd
        $isValid = $doc->schemaValidate($this->xsd);

        if (!$isValid) {
            libxml_log_errors();
            $ret['result'] = 'FAIL';
            $ret['error']  = 'Schema validation failed, please contact Captina Support for assistance.';

        }
        else {
            $ret['result'] = 'OK';
        }
        libxml_clear_errors();
        return $ret;
    }
}


