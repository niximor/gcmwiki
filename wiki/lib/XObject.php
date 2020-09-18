<?php

namespace lib;

class XObject {
    private $reflection;

    private static function methodToProperty($name) {
        if (!empty($name)) {
            return strtolower($name[0]).strtolower(preg_replace('/([a-z]|^)([A-Z])/', '\\1_\\2', substr($name, 1)));
        } else {
            return $name;
        }
    }

    protected function isPublic($name) {
        if (is_null($this->reflection)) {
            $this->reflection = new \ReflectionClass($this);
        }

        try {
            return $this->reflection->getProperty($name)->isPublic();
        } catch (\ReflectionException $e) {
            return true;
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
                return $this;
            } else {
                trigger_error("Method ".get_class($this)."::".$method." takes exactly one argument.", E_USER_ERROR);
            }
        } else {
            trigger_error("Trying to call non-existing method ".$method." of object of class ".get_class($this), E_USER_ERROR);
        }
    }

    public function getOrDefault($name, $default) {
        $name = self::methodToProperty($name);
        if (property_exists($this, $name) && $this->isPublic($name)) {
            return $this->$name;
        } else {
            return $default;
        }
    }

    public function __set($name, $value) {
        if ($this->isPublic($name)) {
            $this->$name = $value;
        }
    }

    public function __get($name) {
        if ($this->isPublic($name)) {
            return $this->$name;
        }
    }
}
