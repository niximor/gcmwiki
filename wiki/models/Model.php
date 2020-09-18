<?php

namespace models;

require_once "lib/XObject.php";

class Model extends \lib\XObject {
    protected $_changed = array();

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

    /**
     * Return list of all changed columns.
     * @return array List of all column names that has been changed.
     */
    public function listChanged() {
        return array_keys($this->_changed);
    }

    /**
     * Determines whether given column is changed. If column is not specified, it means any column has been changed.
     * @param string $name Name of column. Can be NULL, which means any column.
     * @return true if $column is changed or if $column is NULL, if any column has been changed.
     *   false if $column has been changed or if none column has been changed if $column is NULL.
     */
    public function isChanged($name = NULL) {
        if (!is_null($name)) {
            return $name && $name[0] != '_' && isset($this->_changed[$name]);
        } else {
            return !empty($this->_changed);
        }
    }

    /**
     * Clear changed state of column or all columns if $onlyName is NULL.
     * @param string $onlyName If specified, clears changed stat only of that column. Otherwise, clears changed state
     *   of all columns.
     */
    public function clearChanged($onlyName = NULL) {
        if (!is_null($onlyName) && isset($this->_changed[$onlyName])) {
            unset($this->_changed[$onlyName]);
        } elseif (is_null($onlyName)) {
            $this->_changed = array();
        }
    }
}
