<?php
/**
 * Created by IntelliJ IDEA.
 * User: lents
 * Date: 3/27/15
 * Time: 21:42
 */

class XmlDataObject {

    /**
     * @var string
     */
    public $name;

    /**
     * @var ArrayObject
     */
    protected $_data;

    /**
     * @var bool
     */
    // protected $isLeaf;

    /**
     * @param string $name
     */
    public function __construct($name = '') {
        $this->name = $name;
        $this->child = null;
        $this->isLeaf = false;
        $this->_data = new ArrayObject( [ ], ArrayObject::ARRAY_AS_PROPS );
    }

    public function __set($name, $value = null) {
        $this->_data->offsetSet($name, $value);
        if (is_scalar($value)) {
            // $this->isLeaf = true;
        }
    }

    public function setName($name) {
        $this->name = $name;
    }
    /**
     * @return string
     */
    public function __toString() {
        return $this->name;
    }
}