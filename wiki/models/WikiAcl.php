<?php

namespace models;

require_once "models/Model.php";

class WikiAcl extends Model {
	protected $page_read;
	protected $page_write;
	protected $page_admin;
	protected $comment_read;
	protected $comment_write;
	protected $comment_admin;
	protected $attachment_write;

	function __construct() {
		foreach (self::listAcls() as $name) {
			$this->_ensureBool($this->$name);
		}
	}

	protected function _ensureBool(&$value) {
		if ($value == "1") $value = true;
		elseif ($value == "0") $value = false;
		elseif ($value == "-1") $value = NULL;
	}

	public static function listAcls() {
		return array("page_read", "page_write", "page_admin", "comment_read", "comment_write", "comment_admin", "attachment_write");
	}
}

class WikiAclSet {
	public $default;
	public $users = array();
	public $groups = array();

	function __construct() {
		if (is_null($this->default)) {
			$this->default = new WikiAcl();
		}
	}
}

class WikiUserAcl extends WikiAcl {
	protected $id;
	protected $name;
}

class WikiGroupAcl extends WikiAcl {
	protected $id;
	protected $name;
}

