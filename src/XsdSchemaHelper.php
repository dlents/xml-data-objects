<?php
/**
 * Created by David Lents <david@captina.net>
 * Date: 2014-02-24
 * Time: 8:40 PM
 * Created with PhpStorm
 */

class XsdSchemaHelper {

    protected $schemaXsd;
    protected $schemaDOM;
    protected $schemaXpath;

    public function __construct() {
        // @link http://www.w3.org/2001/XMLSchema-datatypes.xsd
        $this->schemaXsd = __DIR__ . '/xsd/XMLSchema-datatypes.xsd';
        $this->schemaDOM = new DOMDocument();
        $this->schemaDOM->preserveWhiteSpace = false;
        $this->schemaDOM->formatOutput = false;
        $this->schemaDOM->load($this->schemaXsd);
        $this->schemaXpath = new DOMXPath($this->schemaDOM);
    }

    public function isSimpleType($typeName) {
        // $xPath->registerNamespace('', 'http://www.w3.org/2001/XMLSchema-datatypes');
        // $xPath->registerNamespace('', 'http://www.w3.org/2001/XMLSchema');
        // strip off namespace if present
        if ($localType = strrchr($typeName, ':')) {
            $typeName = substr($localType, 1);
        }
        // $this->debugLog("Type: $type\n");
        $xQuery = "//child::*[local-name() = 'schema']/*[local-name() = 'simpleType' and @name = '$typeName']";
        $found = $this->schemaXpath->query($xQuery);
        if ($found->length === 0) {
            return false;
        }
        else {
            return true;
        }
    }
}
