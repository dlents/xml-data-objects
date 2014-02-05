<?php
/**
 * Created by David Lents <david@captina.net>
 * Date: 2014-02-04
 * Time: 11:39 PM
 * Created with PhpStorm
 */
/**
 * Class XmlNode
 */
class XmlNode {

    /**
     * @var null|string
     */
    public $name;

    /**
     * @var XmlNode
     */
    public $parentObj;

    /**
     * @var ArrayObject
     */
    public $childTags;

    /**
     * @var ArrayObject
     */
    public $childNodes;

    /**
     * @var DomElement
     */
    public $xmlElement;

    /**
     * @var DOMElement
     */
    // public $xmlRootNode; // TODO: May not be needed

    /**
     *
     */
    public function __construct($name = null) {
        $this->name       = $name; // element (tag) name
        $this->parentObj  = null; // XmlNode object of parent (XML) element
        $this->childTags  = new ArrayObject(array(), ArrayObject::STD_PROP_LIST); // order child element tags (names)
        $this->childNodes = new ArrayObject(array(), ArrayObject::STD_PROP_LIST); // ordered child element XmlNode objects
        $this->xmlElement = null; // This DOMElement
    }

    /**
     * @return bool
     */
    public function hasChildren() {
        return count($this->childTags);
    }

    /**
     * @return bool
     */
    public function hasParent() {
        return ($this->parentObj === null ? false : true);
    }

    public function isParent() {
        return ($this->hasParent() ? false : true);
    }

    public function __tostring() {
        return $this->name;
    }
}
