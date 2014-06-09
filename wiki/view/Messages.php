<?php

namespace view;

class Message {
	const Error = 0;
	const Information = 1;
	const Warning = 2;
	const Success = 3;

	protected $text;
	protected $type;

	/**
	 * Constructor. Create new message.
	 * @param message Message text
	 * @param type Message type
	 */
	function __construct($message, $type = Message::Error) {
		$this->text = base64_encode($message);
		$this->type = $type;
	} // Message::__construct()

	/**
	 * Get properties of the message
	 * @param name Property name
	 */
	function __get($name) {
		switch ($name) {
			case "text":
				return base64_decode($this->text);

			case "type":
				return $this->type;
		}
	} // Message::__get()

	/**
	 * Convert message to printable form
	 */
	function __toString() {
		return base64_decode($this->text);
	} // Message::__toString()

}

class Messages {
	/**
	 * Just dummy constructor to avoid creating instance of messages
	 */
	protected function __construct() {
	} // Messages::__construct()

	/**
	 * Add new message to current session's storage
	 * @param message Message text
	 * @param type Message type
	 */
	static function Add($message, $type = Message::Error) {
		$msg = \lib\Session::Get("messages");
		if (!is_array($msg)) $msg = array();
		$msg[] = new Message($message, $type);
		\lib\Session::Set("messages", $msg);
	} // Messages::Add()

	/**
	 * Get last added message from current session's storage
	 */
	static function Get() {
		$msg = \lib\Session::Get("messages");
		if (!is_array($msg)) return NULL;
		$return = array_shift($msg);
		\lib\Session::Set("messages", $msg);
		return $return;
	} // Messages::Get()

	static function Count() {
		$msg = \lib\Session::Get("messages");
		if (!is_array($msg)) return 0;
		else return count($msg);
	}
}

