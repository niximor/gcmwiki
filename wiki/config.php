<?php

require_once "view/exceptions.php";

/**
 * Generic config class that stores system configuration. It is static class, that provides functionality only
 * through it's static methods.
 *
 * The configuration can be set in config file and later overwritten from configuration loaded from database
 * to allow modification of certain system properties without modifying the code. You can control which can
 * be overwritten from database by listing the property in system_config table in database.
 *
 * Most basic methods are Config::Set() to set the configuration value and Config::Get() to get the configuration
 * value.
 *
 * The GCM::Wiki system allows extending the core functionality by special handlers. List of registered handlers is
 * maintained by this class, too. You can use Config::registerSpecial() and Config::getSpecial() to access
 * registered handlers. For more information about special handlers, see \specials\SpecialController class
 * documentation.
 */
class Config {
	private static $options = array(); /**< There is stored the configuration structure. */
	private static $specials = array(); /**< Registered special handlers. Key is handler prefix,
		value is class name of the handler. */

	/**
	 * This is only static class, cannot be instantiated.
	 */
	private function __construct() {
	}

	/**
	 * Set property's value.
	 * @param string $key Key name. Key is resolved using dotted notation.
	 * @param mixed $value Key value. If array, new config structure will be created. Otherwise, you can specify
	 *     any arbitrary value here, that will be stored.
	 * @throws ConfigException if you try to set key that cannot be resolved because of parent has already plain value.
	 */
	public static function Set($key, $value) {
		$key = explode(".", $key);
		$path = "";

		// Last option is the leaf,
		$last = array_pop($key);

		// Resolve the option
		$arr = &self::$options;
		foreach ($key as $k) {
			if (!empty($path)) {
				$path .= ".";
			}
			$path .= $key;

			if (is_array($arr) && isset($arr[$k])) {
				$arr = &$arr[$k];
			} elseif (is_array($arr)) {
				$arr[$k] = array();
				$arr = &$arr[$k];
			} else {
				throw new ConfigException("Config option ".$path." is already set to a value. Cannot be struct.");
			}
		}

		if (is_array($arr)) {
			$arr[$last] = $value;
		}
	}

	/**
	 * Get key value and if it does not exists, return optional default value.
	 * @param string $key Key to return. It will be resolved using dotted notation.
	 * @param mixed $default Default value that will be returned when key is not found.
	 * @return Value of $key or $default if $key was not found.
	 */
	public static function Get($key, $default=NULL) {
		$key = explode(".", $key);

		$arr = self::$options;
		foreach ($key as $k) {
			if (is_array($arr) && isset($arr[$k])) {
				$arr = &$arr[$k];
			} else {
				$arr = NULL;
				break;
			}
		}

		if (is_null($arr)) return $default;
		else return $arr;
	}

	/**
	 * Register special handler.
	 * @param string $prefix Name of handler that is used in the URL. The name must be unique across the system, there
	 * can't be two handlers with same registered prefix.
	 * @param string $class Class name of the handler class. This must include full class name including namespace.
	 */
	public static function registerSpecial($prefix, $class) {
		if (isset(self::$specials[$prefix])) {
			throw new ConfigException("Handler with prefix '".$prefix."' already registered to the class ".self::$specials[$prefix]);
		}

		self::$specials[$prefix] = $class;
	}

	/**
	 * Returns special handler's class name for given handler name.
	 * @param string $prefix Special handler prefix previously registered using Config::registerSpecial() method call.
	 * @return string Class name of prefix that has been registered to given prefix name.
	 * @throws \view\UnknownSpecialPage if given prefix has not been registered to any handler.
	 */
	public static function getSpecial($prefix) {
		if (isset(self::$specials[$prefix])) {
			return self::$specials[$prefix];
		} else {
			throw new view\UnknownSpecialPage();
		}
	}
}

/**
 * Exception thrown for config-specific errors.
 */
class ConfigException extends RuntimeException {
}
