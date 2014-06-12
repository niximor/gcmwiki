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

	public $OwnerUser;
	public $EditUser;
	public $childs; // child comments

	public function updateText($text) {
		$this->setText_wiki($text);

		// TODO: Wiki formatting of text
		$this->setText_html($text);
	}
}
