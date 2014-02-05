<?php
/**
 * Created by David Lents <david@captina.net>
 * Date: 2014-01-19
 * Time: 3:14 AM
 * Created with PhpStorm
 */

// Simple classes used to implement a tree structure for xml nodes
// This is the best/simplest way I could think of to manage the
// strict order of nodes imposed by the UPS xsd schemas


/**
 * Class XmlNodeTree
 *   Interesting: http://snipplr.com/view/63613/
 */
class XmlNodeTree {
    /**
     * @var SplObjectStorage
     */
    private $tree;


    /**
     * @var string|null
     */
    public $treeName; // Name of the top-level XML node

    /**
     * @var string
     */
    public $xsdFile;
    /**
     * @var DOMElement
     */
    public $xmlRootNode; // root node DOMElement

    /**
     * @var DOMDocument
     */
    public $xmlDoc;

    /**
     *
     */
    public function __construct() {
        $this->tree = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS); // Storage for XmlNodes
        // $this->tree->setFlags(ArrayObject::ARRAY_AS_PROPS);
        $this->treeName    = '';
        $this->xsdFile     = '';
        $this->xmlRootNode = null; // needed?
        $this->xmlDoc      = null;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasNode($name) {
        // return isset($name); // __isset() broken
        // return isset($this->tree[$name]);
        return $this->tree->offsetExists($name);
    }

    /**
     * @param string $name
     * @param XmlNode   $node
     */
    public function addNode($name, XmlNode $node) {
        $this->tree->offsetSet($name, $node);
    }


    /**
     * @param $name
     *
     * @return XmlNode $node|null
     */
    public function __get($name) {
        // if() {
        if ($this->hasNode($name)) { // should use magic __isset() method
            return $this->tree->offsetGet($name);
        }
        return null;
    }

    /**
     * @param      $name
     * @param XmlNode $node
     */
    public function __set($name, $node) {
        // echo "setting '$name' to " . print_r($val, true) . "\n";
        $this->tree->offsetSet($name, $node);
        // $this->tree->attach($node, $data);
    }

    public function __BROKEN_isset($name) {
        echo "In __isset()\n";
        if (isset($this->tree[$name])) {
            echo "isset() says '$name' is set!\n";
        }
        return isset($this->tree[$name]);
        // return $this->tree->offsetExists($name);
        // return $this->tree->contains($name);
    }

    /**
     * @return mixed
     */
    public function walkTree() {
        // return print_r($this, true);
        foreach ($this->tree as $node) {
            echo "Node: '{$node->name}' has " . $node->hasChildren() . " children\n";
            if ($node->hasChildren() > 0) {
                foreach($node->childNodes as $child) {
                    echo "   - $child\n";
                }
            }
        }
    }

    public function dumpTree() {
        return print_r($this->tree, true);
    }
}
