<?php

namespace models;

require_once "models/Model.php";

class WikiAcl extends Model {
	protected $page_read;
	protected $page_write;
	protected $page_admin;
	protected $comment_read;
	protected $comment_write;

	function __construct() {
		$this->_ensureBool($this->page_read);
		$this->_ensureBool($this->page_write);
		$this->_ensureBool($this->page_admin);
		$this->_ensureBool($this->comment_read);
		$this->_ensureBool($this->comment_write);
	}

	protected function _ensureBool(&$value) {
		if ($value == "1") $value = true;
		elseif ($value == "0") $value = false;
		elseif ($value == "-1") $value = NULL;
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

