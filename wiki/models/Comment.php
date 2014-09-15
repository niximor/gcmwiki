<?php

namespace models;

require_once "models/Model.php";
require_once "models/WikiPage.php";
require_once "lib/Observer.php";

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

	protected $page;

	public $OwnerUser;
	public $EditUser;
	public $childs; // child comments

	public function updateText($text) {
		$this->setText_wiki($text);
	}
}

class CommentObserver implements \lib\Observer {
	public function notify(\lib\Observable $object) {
		$be = \Config::Get("__Backend");
		foreach ($be->getReferencedComments($object) as $comment) {
			$be->invalidateWikiCache("comment-".$comment->comment_id);
		}
	}
}

WikiPage::$nameChangeObserver->registerObserver(new CommentObserver());
