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

	public function updateBody($wikiText) {
		$this->setBody_wiki($wikiText);

		// TODO: Wiki formatting here
		$this->setBody_html($wikiText);
	}
}

