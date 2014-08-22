<?php

namespace models;

require_once "models/Model.php";
require_once "lib/Observer.php";

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

	protected $summary;
	protected $small_change;

	protected $parent;

	protected $wiki_page_links = array();

	public $User;

	public static $nameChangeObserver;

	public static function init_static() {
		self::$nameChangeObserver = new \lib\ObserverCollection();
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
}

WikiPage::init_static();

class WikiPageObserver implements \lib\Observer {
	public function notify(\lib\Observable $object) {
		$be = \Config::Get("__Backend");
		foreach ($be->getReferencedPages($object) as $page) {
			$be->invalidateWikiCache("wiki-page-".$page->wiki_page_id);
		}
	}
}

WikiPage::$nameChangeObserver->registerObserver(new WikiPageObserver());

