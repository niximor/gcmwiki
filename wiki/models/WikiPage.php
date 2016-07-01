<?php

namespace models;

require_once "models/Model.php";
require_once "lib/Observer.php";
require_once "lib/ascii.php";

class WikiPage extends Model implements \lib\Observable {
	protected $id;
	protected $name;
	protected $url;
	protected $created;
	protected $last_modified;
	protected $user_id;
	protected $revision;
	protected $body_wiki;
	protected $body_html;
	protected $redirect_to;
	protected $locked = false;
    protected $renderer;
    protected $template;

	protected $summary;
	protected $small_change;

	protected $parent_id;
	protected $parent;
	protected $redirected_from = NULL;

	protected $links;
	protected $references;

	protected $wiki_page_links = array();

	protected $is_current_revision = NULL;

	protected $was_cached = false;

	public $User;

	public static $nameChangeObserver;
	public static $pageChangeObserver;

	public static function init_static() {
		self::$nameChangeObserver = new \lib\ObserverCollection();
		self::$pageChangeObserver = new \lib\ObserverCollection();
	}

	public function updateBody($wikiText) {
		$this->setBody_wiki($wikiText);
	}

	public function getPath() {
		if (!is_null($this->parent)) {
			$path = array_merge($this->parent->getPath(), array($this->url));
		} else {
			$path = array($this->url);
		}
		return $path;
	}

	public function getFullUrl() {
		return implode("/", $this->getPath());
	}

	public static function nameToUrl($name) {
		$url = \lib\utfToAscii($name);
		$url = preg_replace("/[^a-zA-Z0-9\s-_\s:]+/", "_", $url);
		$url = preg_replace("/[\s]+/", " ", $url);
		return $url;
	}
}

WikiPage::init_static();

// FIXME: This is ugly hack to allow registering pageChangeObserver.
require_once "lib/format/Category.php";
