<?php

namespace view;

require_once "lib/Observer.php";

class PageAction {
	public $url;
	public $name;

	function __construct($name, $url) {
		$this->name = $name;
		$this->url = $url;
	}

	function __toString() {
		if (!empty($this->url)) {
			return sprintf("<a href=\"%s\">%s</a>", htmlspecialchars($this->url), htmlspecialchars($this->name));
		} else {
			return htmlspecialchars($this->name);
		}
	}
}

class Template implements \lib\Observable {
	protected $file;

	protected $parent = NULL;
	protected $child;

	protected $variables = array();
	protected $self;

	public static $beforeRenderObserver;

	public static function init_static() {
		self::$beforeRenderObserver = new \lib\ObserverCollection();
	}

	function __construct($file, Template $parent = NULL) {
		$file = dirname(__FILE__)."/../../templ/".$file;
		$this->file = $file;

		if (!is_null($parent)) $this->parent = $parent;

		$this->variables["Actions"] = array();
		$this->variables["BottomNavigation"] = array();
		$this->variables["Navigation"] = array();
	}

	function setChild(Template $child) {
		$child->setParent($this);
		$this->child = $child;
	}

	function setParent(Template $parent=NULL) {
		$this->parent = $parent;

		if (isset($this->variables["Title"])) {
			$this->parent->setTitle($this->variables["Title"]);
		}

		if (isset($this->variables["Actions"])) {
			foreach ($this->variables["Actions"] as $Action) {
				$this->parent->variables["Actions"][] = $Action;
			}
			unset($this->variables["Actions"]);
		}

		if (isset($this->variables["Navigation"])) {
			foreach ($this->variables["Navigation"] as $Action) {
				$this->parent->variables["Navigation"][] = $Action;
			}
			unset($this->variables["Navigation"]);
		}
	}

	function getParent() {
		return $this->parent;
	}

	function setTitle($title) {
		if (!is_null($this->parent)) {
			$this->parent->setTitle($title);
		} else {
			$this->variables["Title"] = $title;
		}
	}

	function getTitle() {
		if (!is_null($this->parent)) {
			return $this->parent->getTitle();
		} else {
			return $this->variables["Title"];
		}
	}

	function addAction($name, $url) {
		// Need to bubble to root template.
		if (!is_null($this->parent)) {
			$this->parent->addAction($name, $this->url($url));
		} else {
			$this->variables["Actions"][] = new PageAction($name, $this->url($url));
		}
	}

	function addNavigation($name, $url, $bottom = false) {
		if (!is_null($this->parent)) {
			$this->parent->addNavigation($name, $this->url($url), $bottom);
		} else {
			if ($bottom) {
				$this->variables["BottomNavigation"][] = new PageAction($name, $this->url($url));
			} else {
				$this->variables["Navigation"][] = new PageAction($name, $this->url($url));
			}
		}
	}

	function prependNavigation($name, $url, $bottom = false) {
		if (!is_null($this->parent)) {
			$this->parent->prependNavigation($name, $this->url($url), $bottom);
		} else {
			if ($bottom) {
				array_unshift($this->variables["BottomNavigation"], new PageAction($name, $this->url($url)));
			} else {
				array_unshift($this->variables["Navigation"], new PageAction($name, $this->url($url)));
			}
		}
	}

	function addVariable($key, $val) {
		if (!in_array($key, array("Actions", "Key", "Navigation"))) {
			$this->variables[$key] = $val;
		} else {
			throw new \RuntimeException("Cannot set variable ".$key." directly. Use specialized function.");
		}
	}

	function render() {
		self::$beforeRenderObserver->notifyObservers($this);

		$parents = array();
		$parent = $this->parent;
		while ($parent != NULL) {
			$parents[] = $parent;
			$parent = $parent->parent;
		}

		foreach (array_reverse($parents) as $parent) {
			extract($parent->variables);
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

	function url($path, $cut = 0) {
		if (is_null($path)) return NULL;

		$root = dirname($_SERVER["SCRIPT_NAME"]);

		if ($cut < 0) {
			$parts = explode("/", $path);
			$parts = array_splice($parts, 0, $cut);
			$path = implode("/", $parts);
		}

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

Template::init_static();
