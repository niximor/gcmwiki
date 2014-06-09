<?php

namespace models;

class Model {
    protected $_changed = array();

    private static function methodToProperty($name) {
        if (!empty($name)) {
            return strtolower($name[0]).substr($name, 1);
        } else {
            return $name;
        }
    }

    public function __call($method, $args) {
        if (strpos($method, "get") === 0) {
            $item = self::methodToProperty(substr($method, 3));
            return $this->__get($item);
        } elseif (strpos($method, "set") === 0) {
            if (count($args) == 1) {
                $item = self::methodToProperty(substr($method, 3));
                $this->__set($item, $args[0]);
            } else {
                trigger_error("Method ".get_class($this)."::".$method." takes exactly one argument.", E_USER_ERROR);
            }
        } else {
            trigger_error("Trying to call non-existing method ".$method." of object of class ".get_class($this), E_USER_ERROR);
        }
    }

    public function __get($name) {
        if ($name && $name[0] != '_' && property_exists($this, $name)) {
            return $this->$name;
        } else {
            trigger_error("Trying to get non-existing property `".$name."` of object of a class ".get_class($this), E_USER_WARNING);
        }
    }

    public function __set($name, $value) {
        if ($name && $name[0] != '_' && property_exists($this, $name)) {
            if ($this->$name !== $value) {
                $this->$name = $value;
                $this->_changed[$name] = true;
            }
        } else {
            trigger_error("Trying to set non-existing property `".$name."` of object of a class ".get_class($this), E_USER_WARNING);
        }
    }

    public function listChanged() {
        return array_keys($this->_changed);
    }

    public function isChanged($name) {
        return $name && $name[0] != '_' && isset($this->_changed[$name]);
    }

    public function clearChanged($onlyName = NULL) {
        if (!is_null($onlyName) && isset($this->_changed[$onlyName])) {
            unset($this->_changed[$onlyName]);
        } elseif (is_null($onlyName)) {
            $this->_changed = array();
        }
    }
}

