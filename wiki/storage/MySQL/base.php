<?php

namespace storage\MySQL;

require_once "drivers/mysql/MySQL.php";
require_once "storage/MySQL/sessions.php";
require_once "storage/MySQL/attachments.php";
require_once "storage/MySQL/pages.php";
require_once "storage/MySQL/cache.php";
require_once "storage/MySQL/system.php";
require_once "storage/MySQL/users.php";
require_once "storage/MySQL/comments.php";

class StorageBase {
    public $db;
    public $currentTransaction;

    // Modules
    public $pages;
    public $users;
    public $commnets;
    public $system;
    public $cache;
    public $attachments;

    private $sessions; // Sessions with lazy loading to skip creating session id when not required.

    public function __construct() {
        $cfgMaster = \Config::Get("MySQLMaster");
        $cfgSlave = \Config::Get("MySQLSlave");

        $master = new \drivers\mysql\Config($cfgMaster["host"], $cfgMaster["user"], $cfgMaster["password"], $cfgMaster["database"]);
        if (!is_null($cfgSlave)) {
            $slave = new \drivers\mysql\Config($cfgSlave["host"], $cfgSlave["user"], $cfgSlave["password"], $cfgSlave["database"]);
        } else {
            $slave = NULL;
        }

        $this->db = new \drivers\mysql\MySQL($master, $slave);

        // Init modules
        $this->pages = new Pages($this);
        $this->users = new Users($this);
        $this->comments = new Comments($this);
        $this->system = new System($this);
        $this->cache = new Cache($this);
        $this->attachments = new Attachments($this);
    }

    public function getSessions() {
        if (is_null($this->sessions)) {
            $this->sessions = new SessionStorage($this);
        }
        return $this->sessions;
    }
}

class Module {
    protected $base;

    function __construct(StorageBase $base) {
        $this->base = $base;
    }
}
