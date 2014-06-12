<?php

namespace view;

class Template {
	protected $file;

	protected $parent = NULL;
	protected $child;

	protected $variables = array();
	protected $self;

	function __construct($file, Template $parent = NULL) {
		$file = dirname(__FILE__)."/../../templ/".$file;
		$this->file = $file;

		if (!is_null($parent)) $this->parent = $parent;
	}

	function setChild(Template $child) {
		$child->setParent($this);
		$this->child = $child;
	}

	function setParent(Template $parent=NULL) {
		$this->parent = $parent;
	}

	function addVariable($key, $val) {
		$this->variables[$key] = $val;
	}

	function render() {
		if ($this->parent) {
			extract($this->parent->variables);
		}

		extract($this->variables);
		include $this->file;
	}

	function redirect($url) {
		if (!empty($url) && $url[0] == '/') {
			$target = $this->url($url);
		} else {
			$target = $url;
		}

		header("Location: ".$target);
		echo "Redirecting to <a href=\"".htmlspecialchars($target)."\">".htmlspecialchars($target)."</a>.";
		exit;
	}

	function url($path) {
		$root = dirname($_SERVER["SCRIPT_NAME"]);

		if (!empty($path) && $path[0] == '/') {
			return $root.$path;
		} else {
			return $root."/".$path;
		}
	}

	function setSelf($self) {
		$this->self = $self;
	}

	function getSelf() {
		if (is_null($this->self) && !is_null($this->parent)) {
			return $this->parent->getSelf();
		} else {
			return $this->self;
		}
	}
}

class RootTemplate extends Template {
	function render() {
		$msg = array();

		while ($m = Messages::Get()) {
			$msg[] = $m;
		}

		$this->addVariable("Messages", $msg);
		$i = \lib\CurrentUser::i();
		if ($i->getId() > 0) {
			$this->addVariable("CurrentUser", $i);
		} else {
			$this->addVariable("CurrentUser", NULL);
		}

		parent::render();
	}
}

