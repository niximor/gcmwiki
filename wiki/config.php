<?php

class UnknownSpecialPage extends Exception {
}

class Config {
	private static $options = array();
	private static $specials = array();

	public static function Set($key, $value) {
		return self::$options[$key] = $value;
	}

	public static function Get($key, $default=NULL) {
		if (isset(self::$options[$key])) {
			return self::$options[$key];
		} else {
			return $default;
		}
	}

	public static function registerSpecial($prefix, $class) {
		self::$specials[$prefix] = $class;
	}

	public static function getSpecial($prefix) {
		if (isset(self::$specials[$prefix])) {
			return self::$specials[$prefix];
		} else {
			throw new UnknownSpecialPage();
		}
	}
}
