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
    protected $_choices; // keep track of choices so only one is included

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
        $this->_choices = array();
        if (!empty($rootElement)) {
            $this->setRootElement($rootElement);
            // $this->getRootDataObject();
        }
    }

    public function __destruct() {
        $this->debugLog("dataElements:\n" . var_export(array_keys($this->dataElements), true));
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
        return $this->getDataObject( $this->rootName );
    }

    /**
     * @param string $elementName
     *
     * @return xmlDataSequence
     */
    public function getDataObject($element) {
        if (empty($element)) {
            return null;
        }

        //TODO: redoing to get data elements vs. sequences/complexTypes correctly

        $this->debugOn();
        $dataObject = null;
        //$this->debugLog(__METHOD__ . "(): Searching for '$elementName'");
        if ($this->_hasDataObject($element)) {
            //$this->debugLog(" -- Found '$elementName' in existing object");
            return $this->dataElements[$element];
        }
        elseif (!empty($this->dataElements)) {
            //$this->debugLog("-- Searching existing elements for '$elementName'");
            foreach ($this->dataElements as $seqName => $seqObject) {
                //$this->debugLog("-- Searching in '$seqName' for '$elementName'");
                if ($seqObject->hasElement($element)) {
                    //$this->debugLog(" -- Found '$elementName' in existing '$seqName' object");
                    return $seqObject;
                }
                else {
                    //$this->debugLog(" -- '$elementName' not found in '$seqName' object'");
                }
            }
        }
        // TODO: need isSimpleType() method here
        $elementNode = $this->_getNode($element);
        // //$this->debugLog(" -- got node :\n" . var_export($elementNode, true));
        if ($elementNode) {
            $dataObject = new XmlDataSequence($elementNode);
            $this->dataElements[$element] = $dataObject;
        }

        $this->debugLog("$element Data Elements: " . print_r(array_keys($this->dataElements), true)); // DBG
        $this->debugOff();
        // $this->debugLog(" -- dataObject now:\n" . var_export($dataObject, true));
        return $dataObject;
    }

    protected function _hasDataObject($elementName) {
        return array_key_exists($elementName, $this->dataElements) && is_object($this->dataElements[$elementName]);
    }

    /**
     * @method _isChoice() - is this element one of a schema choice group?
     * @param string $elementName
     *
     * @return bool
     */
    protected function _isChoice($elementName = '') {
        if (empty($elementName) || empty($this->_choices)) {
            return false;
        }
        foreach ($this->_choices as $choice) {
            if (in_array($elementName, $choice)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $elementName
     * @return DOMNode | DOMElement
     */
    public function _getNode($elementName = '') {
        $this->debugOn();
        //$this->debugLog(__METHOD__ . " - searching for node '$elementName'");
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

        // $xpSequenceQuery = "/descendant::*[@name='$elementName']//*[local-name()='sequence']";
        // try to account for <xsd:sequence> within <xsd:sequence> items in UPS XSDs, e.g:
        //<xsd:complexType name="RequestType">
        //   <xsd:sequence>
        //       <xsd:sequence>
        //          <xsd:element name="TransactionReference" type="TransactionReferenceType" minOccurs="0"/>
        //          <xsd:element name="RequestAction" type="xsd:string"/>
        //          <xsd:element name="RequestOption" type="xsd:string" minOccurs="0"/>
        //       </xsd:sequence>
        //    </xsd:sequence>
        //</xsd:complexType>

        $node = null; // this is the node we'll return
        $type = '';

        if ($node = $this->_findChoiceByName($elementName)) {
            $type = 'choice';
        }
        elseif ($node = $this->_findSequenceByName($elementName)) {
            $type = 'sequence';
        }
        elseif ($node = $this->_findElementByName($elementName)) {
            $type = 'element';
        }
        else {
            $type = 'no';
        }

        $this->debugLog("Found $type node for name '$elementName'");
        $this->debugOff();
        return $node;
    }

    /**
     * @param string $name
     * @return DOMNode|null
     */
    protected function _findChoiceByName($elementName) {
        if (empty($elementName)) {
            return null;
        }
        // $this->debugLog(__METHOD__ . "() - searching for choice '$elementName'");

        $node = null;
        $xPathQuery = "//*[@name='$elementName' or @type='$elementName']/parent::*[local-name()='choice']/*";
        $nodeList = $this->xPath->query($xPathQuery);
        if ($nodeList->length) {
            // $this->debugLog(__METHOD__ . "() - Found choice '$elementName'");
            $choices = [];
            foreach ($nodeList as $elem) {
                $elemName = $elem->getAttribute('name');
                if ($elemName == $elementName) {
                    $node = $elem;
                }
                $choices[] = $elemName;
            }
            natcasesort($choices);
            if (!in_array($choices, $this->_choices)) {
                $this->_choices[] = $choices;
            }
        }

        return $node;
    }

    /**
     * @param string $name
     * @return DOMNode|null
     */
    protected function _findSequenceByName($elementName) {
        if (empty($elementName)) {
            return null;
        }
        $node = null;
        $xPathQuery = "(/descendant::*[@name='$elementName']//*[local-name()='sequence'])[last()]";
        $nodeList = $this->xPath->query($xPathQuery);

        if ($nodeList->length) {
            $node = $nodeList->item(0);
        }
        return $node;
    }

    /**
     * @param string $name
     * @return DOMNode|null
     */
    protected function _findElementByName($elementName) {
        if (empty($elementName)) {
            return null;
        }
        $node = null;
        $xPathQuery = "/descendant::*[@name='$elementName' and local-name()='element']";
        $nodeList = $this->xPath->query($xPathQuery);
        if ($nodeList->length) {
            $node = $nodeList->item(0);
        }
        return $node;
    }

    protected function _parseNode($nodeParentName = '', $element = []) {
        if (empty($element) || !array_key_exists('name', $element)) {
            return;
        }
        //$this->debugLog(__METHOD__ . " :: Processing node '(parent: '$nodeParentName'}, element: " . var_export($element, true));
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
            $data = $this->getDataObject($element['name']);
            // $this->debugLog("Getting value for '$nodeName");
            $value = $data->$nodeName;
            //$this->debugLog(__METHOD__ . " :: Found value for '{$nodeName}': $value"); // DBG
            if (!empty($value)) {
                $node->xmlElement = $nodeTree->xmlDoc->createElement($nodeName, htmlentities($value, ENT_XML1));
                // $node->xmlElement = $nodeTree->xmlDoc->createTextNode($nodeName, $value);
                $nodeTree->addNode($nodeName, $node);
                $node->parentObj->xmlElement->appendChild($node->xmlElement);
            }
        }
        // TODO: check for sub-objects?
        else {
            // recurse until node is a leaf
            // $this->debugLog(__METHOD__ . " - '$elementName' dataElements: " . print_r($this->dataElements[$elementName], true)); // DBG
            $typeSequence = $this->getDataObject($element['type']);
            // $this->debugLog($element['type'] . ': ' . print_r($typeSequence->getElements(), true));
            if (is_object($typeSequence)) {
                $typeElements = $typeSequence->getElements();
                $node->xmlElement = $nodeTree->xmlDoc->createElement($nodeName);
                $nodeTree->addNode($nodeName, $node);
                foreach ($typeElements as $childElement) {
                    $this->_parseNode($nodeName, $childElement);
                    if ($nodeTree->hasNode($childElement['name'])) {
                        $node->xmlElement->appendChild($nodeTree->$childElement['name']->xmlElement);
                    }
                }
            }
        }
        return;
    }

    public function getDocument() {
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
        // $this->debugLog(__METHOD__ . "() - choices are:\n" . var_export($this->_choices, true));
        foreach ($rootSequence as $childElement) {
            // if ($childElement['name'] === 'Address' || $childElement['name'] === 'AddressType') continue; // TODO: remove and fix!!
            // $this->debugLog(__METHOD__ . "() - checking '{$childElement['name']}':\n" . var_export($childElement, true));
            if ($this->_isChoice($childElement['name'])) {
                $dataKey = ($childElement['type'] === 'simpleType' ? $childElement['name'] : $childElement['type']);
                if (!$this->_hasDataObject($dataKey)) {
//                    $this->debugLog(__METHOD__ . "() - Skipping choice element '{$childElement['name']}'\n"
//                        . var_export(array_keys($this->dataElements), true));
                    continue;
                }
            }
            $this->_parseNode($this->rootName, $childElement);
            if ($this->nodeTree->hasNode($childElement['name'])) {
                $rootNode->xmlElement->appendChild($this->nodeTree->$childElement['name']->xmlElement);
            }
        }
        // The XML is all ready to go
        $doc->appendChild($rootNode->xmlElement);
        $valid = $this->_validateDocument($doc);
        if ($valid['result'] !== 'OK') {
            trigger_error("XML Validation error:\n" . implode("\n", $valid['error']));
            exit;
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
            $ret['result'] = 'FAIL';
            $ret['error']  = $this->_processValidationErrors();

        }
        else {
            $ret['result'] = 'OK';
        }
        return $ret;
    }

    protected function _processValidationErrors() {
        $errors = libxml_get_errors();
        $msgs   = array();
        foreach ($errors as $error) {
            $msgs[] = $this->_formatLibXmlErrors($error);
        }
        libxml_clear_errors();
        return $msgs; // TODO: maybe store these in object?
    }

    protected function _formatLibXmlErrors($error) {
        $return = '';
        switch ($error->level) {
            case LIBXML_ERR_WARNING:
                $return .= "Warning {$error->code}: ";
                break;
            case LIBXML_ERR_ERROR:
                $return .= "Error {$error->code}: ";
                break;
            case LIBXML_ERR_FATAL:
                $return .= "Fatal Error {$error->code}: ";
                break;
        }
        $return .= trim($error->message);
        if ($error->file) {
            $return .= " in file '{$error->file}'";
        }
        $return .= " on line {$error->line}\n";
        return $return;
    }
}


