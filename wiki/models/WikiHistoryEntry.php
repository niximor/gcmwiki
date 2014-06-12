<?php

namespace models;

class WikiHistoryEntry extends Model {
	protected $id;
	protected $revision;
	protected $last_modified;
	protected $user_id;
	protected $small_change;
	protected $summary;

	public $User;
}