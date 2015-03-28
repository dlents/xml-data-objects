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
        // $this->child = null;
        // $this->isLeaf = false;
        $this->_data = new ArrayObject( [ ], ArrayObject::ARRAY_AS_PROPS );
        // print_r($this->_data);
    }

    public function __set($name, $value = null) {
        if ($this->hasOwnProperty($name)) {
            error_log( "XDO __set '$name' => '$value'" );
            $this->$name = $value;
        }
        else {
            error_log( "XDO __set DATA '$name' => '$value'" );
            if (is_array($value)) {
                $value = new ArrayObject($value);
            }
            $this->_data->$name = $value;
        }
    }

    public function __get($name) {
        if ( $this->hasOwnProperty( $name ) ) {
            return $this->$name;
        }
        else {
            return $this->_data->$name;
        }
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function getChildren() {
        return $this->_data;
    }

    /**
     * @return string
     */
    public function __toString() {
        return $this->name;
    }

    protected function hasOwnProperty($name) {
        return in_array($name, get_object_vars($this));
    }
}