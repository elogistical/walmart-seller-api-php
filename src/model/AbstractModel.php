<?php
namespace WalmartSellerAPI\model;

use \ArrayObject;
use WalmartSellerAPI\util\XSDParser;

abstract class AbstractModel extends ArrayObject {

    private $type;

    public function asXML() {
        $xml = new \SimpleXMLElement('<'.$this->type['name'].' xmlns="'.$this->type['namespace'].'"></'.$this->type['name'].'>');

        $this->parseFields($this->type, $this, $xml);

        return $xml->asXML();
    }

    private function parseFields($type, $source, $xml) {
        foreach($type['_fields'] as $field) {
            if($field['type'] == 'choice') {
                foreach($field['options'] as $option) {
                    if(isset($source[$option['name']])) {
                        if($field['max'] > 1) {
                            foreach($source[$option['name']] as $value) {
                                $this->parseFields($option, $value, $xml->addChild($option['name']));
                            }
                        } else {
                            if(isset($option['_fields'])) {
                                $this->parseFields($option, $source[$option['name']], $xml->addChild($option['name']));
                            } else {
                                if(is_array($source[$option['name']])) {
                                    foreach($source[$option['name']] as $value) {
                                        $xml->addChild($option['name'], $value);
                                    }
                                } else {
                                    if(isset($option['attribute'])) {
                                        $xml->addAttribute($option['name'], $source[$option['name']]);
                                    } else {
                                        $xml->addChild($option['name'], $source[$option['name']]);
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                if(isset($source[$field['name']])) {
                    if(isset($field['_fields'])) {
                        if(array_keys($source[$field['name']]) === range(0, count($source[$field['name']]) - 1)) {
                            foreach($source[$field['name']] as $value) {
                                $this->parseFields($field, $value, $xml->addChild($field['name']));
                            }
                        } else {
                            $this->parseFields($field, $source[$field['name']], $xml->addChild($field['name']));
                        }
                    } else {
                        if(is_array($source[$field['name']])) {
                            foreach($source[$field['name']] as $value) {
                                $xml->addChild($field['name'], $value);
                            }
                        } else {
                            if(isset($field['attribute'])) {
                                $xml->addAttribute($field['name'], $source[$field['name']]);
                            } else {
                                $xml->addChild($field['name'], $source[$field['name']]);
                            }
                        }
                    }
                }
            }
        }
    }

    private function parseXML($type, $source, &$target) {
        if(!isset($type['_fields'])) print_r($type);
        foreach($type['_fields'] as $field) {
            if($field['type'] == 'choice') {
                foreach($field['options'] as $option) {
                    $name = $option['name'];
                    if(isset($source->$name)) {
                        $target[$name] = array();

                        if($field['max'] > 1) {
                            if(isset($option['_fields'])) {
                                foreach($source->$name as $value) {
                                    $entry = array();
                                    $this->parseXML($option, $value, $entry);
                                    $target[$name][] = $entry;
                                }
                            } else {
                                foreach($source->$name as $value) {
                                    $target[$name][] = $value;
                                }
                            }
                        } else {
                            if(isset($option['_fields'])) {
                                $this->parseXML($option, $source->$name, $target[$name]);
                            } else {
                                $target[$name] = $source->$name;
                            }
                        }
                    }
                }
            } else {
                $name = $field['name'];
                if(isset($source->$name)) {
                    if(isset($field['_fields'])) {
                        $target[$name] = array();
                        if(isset($field['maxOccurs']) && $field['maxOccurs'] > 1) {
                            if(count($source->$name) > 1) {
                                foreach($source->$name as $value) {
                                    $entry = array();
                                    $this->parseXML($field, $value, $entry);
                                    $target[$name][] = $entry;
                                }
                            } else {
                                $entry = array();
                                $this->parseXML($field, $source->$name, $entry);
                                $target[$name][] = $entry;
                            }
                        } else {
                            $this->parseXML($field, $source->$name, $target[$name]);
                        }
                    } else {
                        if(is_array($source->$name)) {
                            $target[$name] = array();
                            foreach($source->$name as $value) {
                                $target[$name][] = $value;
                            }
                        } else {
                            $target[$name] = $source->$name;
                        }
                    }
                } else if(isset($field['attribute'])) {
                    foreach($source->attributes() as $id => $value) {
                        if($id == $name) {
                            $target[$name] = $value;
                            break;
                        }
                    }
                }
            }
        }
    }

    public function __construct($type, $data = null) {
        $this->type = XSDParser::load($type);

        if($data != null) {
            if(is_string($data)) $data = new \SimpleXMLElement($data, 0, false, $this->type['namespace']);
            $this->parseXML($this->type, $data, $this);
        }
    }
}
?>