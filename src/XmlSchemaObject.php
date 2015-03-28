<?php

/**
 * Created by IntelliJ IDEA.
 * User: lents
 * Date: 3/28/15
 * Time: 15:52
 */
class XmlSchemaObject {
    // /xsd:schema/xsd:element[@name]
    // /*[local-name()='schema']/*[local-name()='element']/@name
    // /*[local-name()='schema']/*[local-name()='element' and position()=1]/@name
    // local-name(/*[local-name()='schema']/*[local-name()='element' and position()=1])
    protected $rootName;
    protected $dataTree;

    /**
     * @var DOMDocument $xmlDoc
     */
    public $xmlDoc;
    protected $xsDoc;
    protected $xPath;

    public function __construct( $xsd ) {
        $this->xsDoc = new DOMDocument();
        $this->xsDoc->load( $xsd );
        $this->xsDoc->preserveWhiteSpace = true;
        $this->xsDoc->formatOutput = true;
        $this->xPath = new DOMXPath( $this->xsDoc );
        $this->getRootName();
    }

    public function getRootName() {
        $expr = "string(/*[local-name()='schema']/*[local-name()='element' and position()=1]/@name)";
        //$expr = "/*[local-name()='schema']/*[local-name()='element' and position()=1]/@name";
        $this->rootName = $this->xPath->evaluate( $expr );
    }

    public function createDataObject( $doName ) {
        return new XmlDataObject( $doName );
    }

    public function getRootDataObject() {
        $this->dataTree = new XmlDataObject( $this->rootName );
        return $this->dataTree;
    }

    public function createDocument() {
        $this->xmlDoc = new DOMDocument( '1.1', 'UTF-8' );
        $this->xmlDoc->formatOutput = true;
        $rootNode = $this->xmlDoc->createElement( $this->rootName );
        $rootNode->setAttributeNS( 'http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance' );
        $this->xmlDoc->appendChild( $rootNode );
        $this->walkElements( $this->dataTree, $rootNode );
        return $this->XmlDoc;
    }

    /**
     * @param XmlDataObject $xmlDo
     * @param DOMElement    $xmlNode
     */
    public function walkElements( $xmlDo, $xmlNode ) {
        $nodes = $xmlDo->getChildren();
        $iter = $nodes->getIterator();
        while ( $iter->valid() ) {
            if ( is_scalar( $iter->current() ) ) {
                $node = $this->xmlDoc->createElement( $iter->key(), htmlentities( $iter->current(), ENT_XML1 ) );
                $xmlNode->appendChild( $node );
                // $iter->next();
            } elseif ( is_object( $iter->current() ) ) {
                if ( get_class( $iter->current() ) === 'ArrayObject' ) {
                    /** @noinspection ForeachSourceInspection */
                    foreach ( $iter->current() as $child ) {
                        if ( is_scalar( $child ) ) {
                            $node = $this->xmlDoc->createElement( $iter->key(), htmlentities( $iter->current(), ENT_XML1 ) );
                            $xmlNode->appendChild( $node );
                            // $iter->next();
                            continue;
                        } elseif ( is_object( $child ) && get_class( $child ) === 'XmlDataObject' ) {
                            $node = $this->xmlDoc->createElement( $iter->key() );
                            $xmlNode->appendChild( $node );
                            $this->walkElements( $child, $node );
                        } else {
                            // wrong object/type
                        }
                    }
                } else {
                    $node = $this->xmlDoc->createElement( $iter->key() );
                    $xmlNode->appendChild( $node );
                    $this->walkElements( $iter->current(), $node );
                }
            }
            $iter->next();
        }
    }

}