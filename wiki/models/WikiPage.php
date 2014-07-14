<?php

namespace models;

require_once "models/Model.php";

class WikiPage extends Model {
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

	public function updateBody($wikiText) {
		$this->setBody_wiki($wikiText);

		// TODO: Wiki formatting here
		$this->setBody_html($wikiText);

		// Process wiki links
		if (isset($f->getRootContext()->WIKI_LINKS) && is_array($f->getRootContext()->WIKI_LINKS)) {
			$this->wiki_page_links = $f->getRootContext()->WIKI_LINKS;
		}
	}
}

