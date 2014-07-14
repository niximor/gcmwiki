<?php

namespace models;

class Comment extends Model {
	protected $id;
	protected $page_id;
	protected $revision;
	protected $created;
	protected $last_modified;
	protected $text_wiki;
	protected $text_html;
	protected $parent_id; // for storing comments
	protected $owner_user_id;
	protected $edit_user_id;
	protected $anonymous_name;
	protected $wiki_page_links = array();

	public $OwnerUser;
	public $EditUser;
	public $childs; // child comments

	public function updateText($text) {
		$this->setText_wiki($text);

		// TODO: Wiki formatting of text
		$f = new WikiFormatterSimple();
		$this->setText_html($f->format($text));

		// Process wiki links
		if (isset($f->getRootContext()->WIKI_LINKS) && is_array($f->getRootContext()->WIKI_LINKS)) {
			$this->wiki_page_links = $f->getRootContext()->WIKI_LINKS;
		}
	}
}
