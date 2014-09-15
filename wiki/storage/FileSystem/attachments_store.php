<?php

namespace \storage\FileSystem;

require_once "storage/storage.php";

class DataStore implements \storage\DataStore {
    function __construct() {
        $be = \Config::Get("")
    }

    function load(\models\Attachment $attachment, $subId);
    function store($fileHandle, \models\Attachment $attachment, $subId);
}