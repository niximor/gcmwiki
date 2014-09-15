<?php

namespace storage\MySQL;

require_once "storage/storage.php";
require_once "storage/MySQL/base.php";

class Attachments implements \storage\Attachments {
    protected $base;

    function __construct(\storage\MySQL\StorageBase $base) {
        $this->base = $base;
    }

    function load($name, $requiredColumns = NULL, $revision = NULL) {

    }

    function store(\modules\Attachment $attachment) {

    }
}


