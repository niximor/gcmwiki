<?php

namespace storage;

require_once "storage/storage.php";
require_once "drivers/mysql/MySQL.php";
require_once "models/WikiPage.php";
require_once "models/WikiHistoryEntry.php";
require_once "models/User.php";
require_once "models/Group.php";
require_once "models/SystemPrivilege.php";
require_once "models/SystemVariable.php";

class MySQL implements Storage {
    protected $db;

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
    }

    public function getDb() {
        return $this->db;
    }

    public function getSessionStorage() {
        return new DBSessionStorage($this->db);
    }

    public function loadPage($path, $requiredColumns = NULL, $revision = NULL) {
        $trans = $this->db->beginRO();

        if (!is_array($path) || count($path) == 0) {
            throw new Exception("You must specify page to load as array of page names.");
        }

        if (is_null($requiredColumns)) {
            $requiredColumns = array();
        } elseif (!is_array($requiredColumns)) {
            throw new Exception("Parameter requiredColumns must be array.");
        }

        $parent = NULL;

        $part_len = count($path);
        for ($i = 0; $i < $part_len; ++$i) {
            $part = $path[$i];

            if ($i == $part_len - 1) {
                $columns = array_merge(array("id", "url", "name", "revision", "last_modified", "user_id", "ip"), $requiredColumns);
            } else {
                $columns = array("id");
            }

            array_walk($columns, function(&$a) { $a = "p.".$a; });

            $columns[] = "u.name AS user_name";

            if (!is_null($revision)) {
                $table = "wiki_pages_history";
                $where = " AND revision = ".$revision;
            } else {
                $table = "wiki_pages";
                $where = "";
            }

            if (is_null($parent)) {
                $res = $trans->query("SELECT ".implode(",", $columns)." FROM ".$table." p
                    LEFT JOIN page_hierarchy ph ON (ph.child_id = p.id)
                    LEFT JOIN users u ON (p.user_id = u.id)
                    WHERE url = %s AND ph.parent_id IS NULL".$where, $part);
            } else {
                $res = $trans->query("SELECT ".implode(",", $columns)." FROM ".$table." p
                    LEFT JOIN page_hierarchy ph ON (ph.child_id = p.id)
                    LEFT JOIN users u ON (p.user_id = u.id)
                    WHERE url = %s AND ph.parent_id = %s".$where, $part, $parent->id);
            }

            try {
                $row = $res->fetch();

                $page = new \models\WikiPage;

                if (isset($row->id)) $page->id = $row->id;
                if (isset($row->name)) $page->name = $row->name;
                if (isset($row->url)) $page->url = $row->url;
                if (isset($row->created)) $page->created = $row->created;
                if (isset($row->last_modified)) $page->last_modified = $row->last_modified;
                if (isset($row->user_id)) $page->user_id = $row->user_id;
                if (isset($row->revision)) $page->revision = $row->revision;
                if (isset($row->body_wiki)) $page->body_wiki = $row->body_wiki;
                if (isset($row->body_html)) $page->body_html = $row->body_html;
                if (isset($row->summary)) $page->summary = $row->summary;
                if (isset($row->small_change)) $page->small_change = $row->small_change;

                if ($page->user_id > 0) {
                    $page->User = new \models\User;
                    $page->User->id = $row->user_id;
                    $page->User->name = $row->user_name;
                } else {
                    $page->User = new \models\FakeUser;
                    $page->User->ip = $row->ip;
                }

                $page->setParent($parent);
                $parent = $page;
            } catch (\drivers\EntryNotFoundException $e) {
                $trans->commit();
                throw new PageNotFoundException($part, $parent, $e);
            }
        }

        $trans->commit();
        return $parent;
    }

    protected function updatePage(\models\WikiPage $page, $trans = NULL) {
        $transactionStarted = false;
        if (is_null($trans)) {
            $trans = $this->db->beginRW();
            $transactionStarted = true;
        }

        $columns = array();
        $values = array();

        foreach ($page->listChanged() as $column) {
            if (in_array($column, array("name", "url", "body_wiki", "body_html", "user_id", "small_change", "summary"))) {
                $columns[] = $column." = %s";
                $values[] = $page->$column;
            }
        }

        if (!empty($columns)) {
            $trans->query("INSERT INTO wiki_pages_history (page_id, name, url, body_wiki, body_html, user_id, small_change, summary, ip, last_modified, revision)
                            SELECT                         id,      name, url, body_wiki, body_html, user_id, small_change, summary, ip, last_modified, revision
                            FROM wiki_pages WHERE id = %s", $page->getId());

            $columns[] = "last_modified = NOW()";

            $columns[] = "user_id = %s";
            $values[] = \lib\CurrentUser::ID();

            $columns[] = "ip = %s";
            $values[] = \lib\Session::IP();

            $columns[] = "revision = revision + 1";

            $values[] = $page->getId();

            $trans->query("UPDATE wiki_pages SET ".implode(",", $columns)." WHERE id = %s", $values);
        }

        if ($transactionStarted) {
            $trans->commit();
        }
    }

    protected function createPage(\models\WikiPage $page, $trans = NULL) {
        $transactionStarted = false;
        if (is_null($trans)) {
            $trans = $this->db->beginRW();
            $transactionStarted = true;
        }

        $trans->query("INSERT INTO wiki_pages (name, url, created, last_modified, user_id, body_wiki, body_html, small_change, summary, ip)
                              VALUES          (%s,   %s,  NOW(),   NOW(),         %s,      %s,        %s,        %s,           %s,      %s)",
                $page->getName(), $page->getUrl(), \lib\CurrentUser::ID(), $page->getBody_wiki(), $page->getBody_html(),
                $page->getSmall_change(), $page->getSummary(), \lib\Session::IP());
        $page->setId($trans->lastInsertId());

        if ($transactionStarted) {
            $trans->commit();
        }
    }

    public function storePage(\models\WikiPage $page) {
        $diag = new Diagnostics();

        $name = $page->getName();
        if (empty($name)) {
            $diag->addError("name", "name_must_be_present", "Page name must be filled in.");
        }

        $url = $page->getUrl();
        if (empty($url)) {
            $diag->addError("url", "url_must_be_present", "Page url must be filled in.");
        }

        $summary = $page->getSummary();
        if (!$page->getSmall_change() && empty($summary)) {
            $diag->addError("summary", "summary_must_be_present", "Summary field is required if not small change.");
        }

        if ($diag->getErrors()) {
            throw $diag;
        }

        $trans = $this->db->beginRW();

        $nameChanged = $page->isChanged("name");

        if ($page->getId()) {
            $this->updatePage($page, $trans);
        } else {
            $this->createPage($page, $trans);
        }

        // Store wiki page links
        $trans->query("DELETE FROM wiki_pages_links WHERE comment_id = %s", $comment->getId());

        $query = "INSERT INTO wiki_pages_links (wiki_page_id, ref_page_id, ref_page_name) VALUES ";
        $ins = array();
        $vals = array();

        foreach ($page->wiki_page_links as $link {
            $ins[] = "(%s, %s, %s)";
            $vals[] = $page->getId();
            if (is_int($link)) {
                $vals[] = $link;
                $vals[] = NULL;
            } else {
                $vals[] = NULL;
                $vals[] = implode('/', $link);
            }
        }

        if (!empty($ins)) {
            $query .= implode(", ", $ins);
            $trans->query($query, $vals);
        }

        // Query for pages that needs changed.
        if ($nameChanged) {
            $q = $trans->query("SELECT p.id, p.body_wiki FROM wiki_pages_links pl JOIN wiki_pages p ON (pl.wiki_page_id = p.id) WHERE ref_page_id = %s OR ref_page_name = %s",
                $page->getId(), $page->getName());
            $q->setClassFactory("\\models\\WikiPage");

            foreach ($q as $row) {
                $row->updateBody($row->getBody_wiki());
                // Update manually to do not create history entry only when changing links.
                $trans->query("UPDATE wiki_pages SET body_html = %s WHERE id = %s", $row->getId(), $row->getBody_html());
            }
        }

        // When creating, convert page names to ids.
        $trans->query("UPDATE wiki_pages_links SET ref_page_id = %s WHERE ref_page_name = %s", $page->getId(), $page->getName());

        $trans->commit();
    }

    function getHistorySummary($pageId) {
        $trans = $this->db->beginRO();

        $res = $trans->query("SELECT h.id, h.revision, h.last_modified, h.user_id, h.small_change, h.summary,
            u.name AS user_name, h.ip
            FROM wiki_pages_history h
            LEFT JOIN users u ON (h.user_id = u.id)
            WHERE page_id = %s
            ORDER BY revision DESC", $pageId);

        $result = array();
        foreach ($res as $row) {
            $item = new \models\WikiHistoryEntry;
            $item->id = $row->id;
            $item->revision = $row->revision;
            $item->last_modified = $row->last_modified;
            $item->user_id = $row->user_id;
            $item->small_change = $row->small_change;
            $item->summary = $row->summary;

            if ($row->user_id > 0) {
                $item->User = new \models\User;
                $item->User->id = $row->user_id;
                $item->User->name = $row->user_name;
            } else {
                $item->User = new \models\FakeUser;
                $item->User->ip = $row->ip;
            }

            $result[] = $item;
        }

        $trans->commit();

        return $result;
    }

    public function loadUserInfo($id, $matchColumn = "id", $columns = array(), $trans = NULL) {
        $transactionStarted = false;
        if (is_null($trans)) {
            $trans = $this->db->beginRO();
            $transactionStarted = true;
        }

        $columns = array_merge(
            array("id", "name", "email", "registered", "last_login", "email_verified"),
            $columns
        );

        $getSessionInfo = false;
        if (in_array("logged_in", $columns)) {
            $getSessionInfo = true;
            $key = array_search("logged_in", $columns);
            unset($columns[$key]);
        }

        $query = "SELECT ".implode(",", $columns);

        if ($getSessionInfo) {
            $query .= " IF(s.id IS NOT NULL, 1, 0) AS logged_in";
        }

        $query .= " FROM users ";

        if ($getSessionInfo) {
            $query .= "LEFT JOIN sessions s ON (s.name = 'UserId' AND s.value = u.id AND s.activity > DATE_ADD(NOW(), INTERVAL -15 MINUTE))";
        }

        $query .= "WHERE ";
        $vals = array();
        if (is_array($matchColumn)) {
            $first = true;
            foreach ($matchColumn as $col) {
                if ($first) $first = false;
                else $query .= " OR ";
                $query .= $col." = %s";
                $vals[] = $id;
            }
        } else  {
            $query .= $matchColumn." = %s";
            $vals[] = $id;
        }

        if ($getSessionInfo) {
            $query .= " GROUP BY u.id";
        }

        try {
            $res = $trans->query($query, $vals)->fetch("\\models\\User");

            if ($transactionStarted) {
                $trans->commit();
            }
        } catch (\Exception $e) {
            if ($transactionStarted) {
                $trans->commit();
            }

            throw $e;
        }

        return $res;
    }

    public function verifyUser($username, $password) {
        $trans = $this->db->beginRW();

        $result = false;

        try {
            $res = $trans->query("SELECT id, salt, password FROM users WHERE name = %s AND status_id = %s", $username, \models\User::STATUS_LIVE)->fetch("\\models\\User");

            if (hash("sha256", $res->salt.$password) == $res->password) {
                $result = $this->loadUserInfo($res->id, "id", array(), $trans);

                $trans->query("UPDATE users SET last_login = NOW() WHERE id = %s", $result->getId());
            }
        } catch (\drivers\EntryNotFoundException $e) {
        }

        $trans->commit();

        return $result;
    }

    public function storeUserInfo(\models\User $user) {
        $trans = $this->db->beginRW();

        if ($user->getId()) {
            $columns = array();
            $values = array();

            $destroySession = false;

            foreach ($user->listChanged() as $column) {
                if ($column == "password") {
                    $columns[] = $column." = SHA2(CONCAT(salt, %s), 256)";
                    $values[] = $user->$column;
                } else {
                    $columns[] = $column." = %s";
                    $values[] = $user->$column;

                    if ($column == "status_id" && $user->$column == \models\User::STATUS_BANNED) {
                        $destroySession = true;
                    }
                }
            }

            $values[] = $user->getId();

            if (count($columns) > 0) {
                $trans->query("UPDATE users SET ".implode(", ", $columns)." WHERE id = %s", $values);
            }

            if ($destroySession) {
                $trans->query("DELETE FROM sessions WHERE name = 'UserId' AND value = %s", $user->getId());
            }
        } else {
            $columns = array();
            $values = array();
            $strings = array();

            $salt = \mcrypt_create_iv(64, MCRYPT_DEV_URANDOM);

            foreach ($user->listChanged() as $column) {
                $columns[] = $column;
                $strings[] = "%s";

                if ($column == "password") {
                    $values[] = \hash("sha256", $salt.$user->$column);
                } else {
                    $values[] = $user->$column;
                }
            }

            $columns[] = "salt";
            $strings[] = "%s";
            $values[] = $salt;

            $columns[] = "registered";
            $strings[] = "NOW()";

            $trans->query("INSERT INTO users (".implode(", ", $columns).") VALUES (".implode(", ", $strings).")", $values);
        }

        $trans->commit();
    }

    public function loadPageAcl(\models\WikiPage $wikiPage, \models\User $user) {
        $acl = new \models\WikiAcl;

        // Admin has anything, do not need to bother the database.
        if ($user->hasPriv("admin_pages")) {
            $acl->page_read = true;
            $acl->page_write = true;
            $acl->page_admin = true;
            $acl->comment_read = true;
            $acl->comment_write = true;

            return $acl;
        }

        $trans = $this->db->beginRO();

        $res = $trans->query("SELECT
                NULL AS user_id,
                NULL AS group_id,
                acl_page_read AS page_read,
                acl_page_write AS page_write,
                acl_page_admin AS page_admin,
                acl_comment_read AS comment_read,
                acl_comment_write AS comment_write
                FROM wiki_pages WHERE id = %s
            UNION
                SELECT
                    NULL AS user_id,
                    acl.group_id,
                    acl.page_read,
                    acl.page_write,
                    acl.page_admin,
                    acl.comment_read,
                    acl.comment_write
                FROM user_group AS ug JOIN page_acl_group AS acl ON (acl.group_id = ug.group_id)
                WHERE acl.page_id = %s AND ug.user_id = %s
            UNION
                SELECT
                    user_id,
                    NULL AS group_id,
                    page_read,
                    page_write,
                    page_admin,
                    comment_read,
                    comment_write
                FROM page_acl_user
                WHERE page_id = %s AND user_id = %s",
                $wikiPage->getId(),
                $wikiPage->getId(), $user->getId(),
                $wikiPage->getId(), $user->getId());

        // Merge ACLs.
        foreach ($res as $row) {
            if (!is_null($row->page_read)) $acl->page_read = $row->page_read;
            if (!is_null($row->page_write)) $acl->page_write = $row->page_write;
            if (!is_null($row->page_admin)) $acl->page_admin = $row->page_admin;
            if (!is_null($row->comment_read)) $acl->comment_read = $row->comment_read;
            if (!is_null($row->comment_write)) $acl->comment_write = $row->comment_write;
        }

        // If we have any uncertain privilege, ask parent.
        if (is_null($acl->page_read)
            || is_null($acl->page_write)
            || is_null($acl->page_admin)
            || is_null($acl->comment_read)
            || is_null($acl->comment_write)) 
        {
            if (!is_null($wikiPage->getParent())) {
                $to_merge = $this->loadPageAcl($wikiPage->getParent(), $user);
            } else {
                // Default ACLs
                $to_merge = $this->loadDefaultAcl($user, $trans);
            }

            if (is_null($acl->page_read)) $acl->page_read = $to_merge->page_read;
            if (is_null($acl->page_write)) $acl->page_write = $to_merge->page_write;
            if (is_null($acl->page_admin)) $acl->page_admin = $to_merge->page_admin;
            if (is_null($acl->comment_read)) $acl->comment_read = $to_merge->comment_read;
            if (is_null($acl->comment_write)) $acl->comment_write = $to_merge->comment_write;
        }

        $trans->commit();

        return $acl;
    }

    public function loadDefaultAcl(\models\User $user, $trans = NULL) {
        $acl = new \models\WikiAcl;
        $acl->page_read = $user->hasPriv("acl_page_read");
        $acl->page_write = $user->hasPriv("acl_page_write");
        $acl->page_admin = $user->hasPriv("acl_page_admin");
        $acl->comments_read = $user->hasPriv("acl_comment_read");
        $acl->comments_write = $user->hasPriv("acl_comment_write");
        return $acl;
    }

    public function listPageAcl(\models\WikiPage $page) {
        $trans = $this->db->beginRO();

        $row = $trans->query("SELECT
            acl_page_read AS page_read,
            acl_page_write AS page_write,
            acl_page_admin AS page_admin,
            acl_comment_read AS comment_read,
            acl_comment_write AS comment_write
            FROM wiki_pages WHERE id = %s", $page->getId())->fetch("\\models\\WikiAcl");

        $set = new \models\WikiAclSet();
        $set->default = $row;

        $set->users = $this->listPageUsersAcl($page, $trans);
        $set->groups = $this->listPageGroupsAcl($page, $trans);

        $trans->commit();

        return $set;
    }

    public function listPageUsersAcl(\models\WikiPage $page, $trans = NULL) {
        $transactionStarted = false;
        if (is_null($trans)) {
            $trans = $this->db->beginRO();
            $transactionStarted = true;
        }

        $res = $trans->query("SELECT
            u.id, u.name, acl.page_read, acl.page_write, acl.page_admin, acl.comment_read, acl.comment_write
            FROM page_acl_user AS acl JOIN users AS u ON (acl.user_id = u.id) WHERE page_id = %s", $page->getId());
        $res->setClassFactory("\\models\\WikiUserAcl");

        $out = array();

        foreach ($res as $row) {
            $out[] = $row;
        }

        if ($transactionStarted) {
            $trans->commit();
        }

        return $out;
    }

    public function listPageGroupsAcl(\models\WikiPage $page, $trans = NULL) {
        $transactionStarted = false;
        if (is_null($trans)) {
            $trans = $this->db->beginRO();
            $transactionStarted = true;
        }

        $res = $trans->query("SELECT
            g.id, g.name, acl.page_read, acl.page_write, acl.page_admin, acl.comment_read, acl.comment_write
            FROM page_acl_group AS acl JOIN groups AS g ON (acl.group_id = g.id) WHERE page_id = %s", $page->getId());
        $res->setClassFactory("\\models\\WikiGroupAcl");

        $out = array();

        foreach ($res as $row) {
            $out[] = $row;    
        }

        if ($transactionStarted) {
            $trans->commit();
        }

        return $out;
    }

    public function storePageAcl(\models\WikiPage $page, \models\WikiAclSet $set) {
        $trans = $this->db->beginRO();

        $trans->query("UPDATE wiki_pages SET
            acl_page_read = %s,
            acl_page_write = %s,
            acl_page_admin = %s,
            acl_comment_read = %s,
            acl_comment_write = %s
            WHERE id = %s",

            $set->default->page_read,
            $set->default->page_write,
            $set->default->page_admin,
            $set->default->comment_read,
            $set->default->comment_write,
            $page->getId());

        $users = array();
        $res = $trans->query("SELECT user_id FROM page_acl_user WHERE page_id = %s", $page->getId());
        foreach ($res as $u) {
            $users[$u->user_id] = true;
        }

        if (count($set->users) > 0) {
            $sets = array();
            $values = array();
            foreach ($set->users as $uacl) {
                if (is_null($uacl->page_read) && is_null($uacl->page_write) && is_null($uacl->page_admin) && is_null($uacl->comment_read) && is_null($uacl->comment_write)) {
                    continue;
                }

                $sets[] = "(%s, %s, %s, %s, %s, %s, %s)";
                $values += array(
                    $page->getId(),
                    $uacl->id,
                    $uacl->page_read,
                    $uacl->page_write,
                    $uacl->page_admin,
                    $uacl->comment_read,
                    $uacl->comment_write
                );

                if (isset($users[$uacl->id])) unset($users[$uacl->id]);
            }
            $trans->query("INSERT INTO page_acl_user (page_id, user_id, page_read, page_write, page_admin, comment_read, comment_write)
                VALUES ".implode(",", $sets)."
                ON DUPLICATE KEY UPDATE
                    page_read = VALUES(page_read),
                    page_write = VALUES(page_write),
                    page_admin = VALUES(page_admin),
                    comment_read = VALUES(comment_read),
                    comment_write = VALUES(comment_write)", $values);
        }

        if (count($users) > 0) {
            $values = array($page->getId());
            $strings = array();
            foreach ($users as $id=>$dummy) {
                $values[] = $id;
                $strings[] = "%s";
            }
        
            $trans->query("DELETE FROM page_acl_user WHERE page_id = %s AND user_id IN (".implode(",", $strings).")", $values);
        }

        $groups = array();
        $res = $trans->query("SELECT group_id FROM page_acl_group WHERE page_id = %s", $page->getId());
        foreach ($res as $g) {
            $groups[$g->group_id] = true;
        }

        if (count($set->groups) > 0) {
            $sets = array();
            $values = array();
            foreach ($set->groups as $gacl) {
                if (is_null($gacl->page_read) && is_null($gacl->page_write) && is_null($gacl->page_admin) && is_null($gacl->comment_read) && is_null($gacl->comment_write)) {
                    continue;
                }

                $sets[] = "(%s, %s, %s, %s, %s, %s, %s)";
                $values += array(
                    $page->getId(),
                    $gacl->id,
                    $gacl->page_read,
                    $gacl->page_write,
                    $gacl->page_admin,
                    $gacl->comment_read,
                    $gacl->comment_write
                );

                if (isset($groups[$gacl->id])) unset($groups[$gacl->id]);
            }
            $trans->query("INSERT INTO page_acl_group (page_id, group_id, page_read, page_write, page_admin, comment_read, comment_write)
                VALUES ".implode(",", $sets)."
                ON DUPLICATE KEY UPDATE
                    page_read = VALUES(page_read),
                    page_write = VALUES(page_write),
                    page_admin = VALUES(page_admin),
                    comment_read = VALUES(comment_read),
                    comment_write = VALUES(comment_write)", $values);
        }

        if (count($groups) > 0) {
            $values = array($page->getId());
            $strings = array();
            foreach ($groups as $id=>$dummy) {
                $values[] = $id;
                $strings[] = "%s";
            }
            $trans->query("DELETE FROM page_acl_user WHERE page_id = %s AND user_id IN (".implode(",", $strings).")", $values);
        }

        $trans->commit();
    }

    public function listUsers(\models\Group $inGroup = NULL, $additionalColumns = array(), $trans = NUll) {
        $transactionStarted = false;
        if (is_null($trans)) {
            $trans = $this->db->beginRO();
            $transactionStarted = true;
        }

        $vals = array();
        $query = "SELECT u.id, u.name";

        $getSessionInfo = false;
        if (in_array("logged_in", $additionalColumns)) {
            unset($additionalColumns[array_search("logged_in", $additionalColumns)]);
            $getSessionInfo = true;
        }

        if (!empty($additionalColumns)) {
            $query .= ", ".implode(", ", $additionalColumns);
        }

        if ($getSessionInfo) {
            $query .= ", IF(s.id IS NOT NULL, 1, 0) AS logged_in";
        }

        $query .= " FROM users u";

        if ($getSessionInfo) {
            $query .= " LEFT JOIN sessions s ON (s.name = 'UserId' AND s.value = u.id AND s.activity > DATE_ADD(NOW(), INTERVAL -15 MINUTE))";
        }

        if (!is_null($inGroup)) {
            $query .= " JOIN user_group ug ON ug.user_id = u.id AND ug.group_id = %s";
            $vals[] = $inGroup->getId();
        }

        $query .= " WHERE u.id > 0";

        if ($getSessionInfo) {
            $query .= " GROUP BY u.id";
        }

        $query .= " ORDER BY u.name ASC";

        $res = $trans->query($query, $vals);
        $res->setClassFactory("\\models\\User");

        $out = array();
        foreach ($res as $row) {
            $out[] = $row;
        }

        if ($transactionStarted) {
            $trans->commit();
        }

        return $out;
    }

    public function listGroups(\models\User $ofUser = NULL, $additionalColumns = array(), $trans = NULL) {
        $transactionStarted = false;
        if (is_null($trans)) {
            $trans = $this->db->beginRO();
            $transactionStarted = true;
        }

        $vals = array();

        $query = "SELECT g.id, g.name";

        if (in_array("userCount", $additionalColumns)) {
            $query .= ", COUNT(ugc.user_id) AS userCount FROM groups g LEFT JOIN user_group ugc ON (ugc.group_id = g.id)";
        } else {
            $query .= " FROM groups g";
        }

        if (!is_null($ofUser)) {
            $query .= " JOIN user_group ug ON (g.id = ug.group_id AND ug.user_id = %s)";
            $vals[] = $ofUser->getId();
        }

        if (in_array("userCount", $additionalColumns)) {
            $query .= " GROUP BY ugc.group_id";
        }

        $query .= " ORDER BY g.name ASC";

        $res = $trans->query($query, $vals);
        $res->setClassFactory("\\models\\Group");

        $out = array();
        foreach ($res as $row) {
            $out[] = $row;
        }

        if ($transactionStarted) {
            $trans->commit();
        }

        return $out;
    }

    public function loadUserPrivileges(\models\User $user) {
        $trans = $this->db->beginRO();

        $res = $trans->query("SELECT
                    sp.id,
                    NULL AS user_id,
                    NULL AS group_id,
                    sp.name,
                    sp.default_value AS value
                FROM system_privileges sp
            UNION
                SELECT
                    pg.id,
                    NULL AS user_id,
                    pg.group_id,
                    sp.name,
                    pg.value
                FROM user_group ug
                JOIN system_privileges_group pg ON pg.group_id = ug.group_id
                JOIN system_privileges sp ON sp.id = pg.privilege_id
                WHERE ug.user_id = %s
            UNION
                SELECT
                    pu.id,
                    pu.user_id,
                    NULL as group_id,
                    sp.name,
                    pu.value
                FROM system_privileges_user pu
                JOIN system_privileges sp ON sp.id = pu.privilege_id
                WHERE user_id = %s", $user->getId(), $user->getId());

        $map = array();

        foreach ($res as $row) {
            if (is_null($row->user_id) && is_null($row->group_id)) {
                $priv = new \models\SystemPrivilege;
            } elseif (!is_null($row->group_id)) {
                $priv = new \models\GroupSystemPrivilege;
                $priv->group_id = $row->group_id;
            } elseif (!is_null($row->user_id)) {
                $priv = new \models\UserSystemPrivilege;
                $priv->user_id = $row->user_id;
            }

            $priv->id = $row->id;
            $priv->name = $row->name;
            $priv->value = $row->value;

            if ($priv->value == "1") $priv->value = true;
            elseif ($priv->value == "0") $priv->value = false;

            $map[$row->name] = $priv;
        }

        $trans->commit();

        return $map;
    }

    function listDefaultPrivileges() {
        $trans = $this->db->beginRO();

        $res = $trans->query("SELECT
            sp.id,
            sp.name,
            sp.default_value AS value
            FROM system_privileges sp
            ORDER BY sp.name ASC");
        $res->setClassFactory("\\models\\SystemPrivilege");

        $out = array();
        foreach ($res as $row) {
            if ($row->value == "1") $row->value = true;
            elseif ($row->value == "0") $row->value = false;

            $out[] = $row;
        }

        $trans->commit();

        return $out;
    }

    function listUserPrivileges(\models\User $user) {
        $trans = $this->db->beginRO();

        $res = $trans->query("SELECT
                pu.id,
                sp.name,
                sp.id AS privilege_id,
                pu.value
            FROM system_privileges sp
            LEFT JOIN system_privileges_user pu ON (pu.privilege_id = sp.id AND pu.user_id = %s)
            ORDER BY sp.name ASC", $user->getId());
        $res->setClassFactory("\\models\\UserSystemPrivilege");

        $out = array();
        foreach ($res as $row) {
            $row->user_id = $user->getId();

            if ($row->value == "1") $row->value = true;
            elseif ($row->value == "0") $row->value = false;

            $out[] = $row;
        }

        $trans->commit();

        return $out;
    }

    function listGroupPrivileges(\models\Group $group) {
        $trans = $this->db->beginRO();

        $res = $trans->query("SELECT
                pg.id,
                sp.name,
                sp.id AS privilege_id,
                pg.value
            FROM system_privileges sp
            LEFT JOIN system_privileges_group pg ON (pg.privilege_id = sp.id AND pg.group_id = %s)
            ORDER BY sp.name ASC", $group->getId());
        $res->setClassFactory("\\models\\GroupSystemPrivilege");

        $out = array();
        foreach ($res as $row) {
            $row->group_id = $group->getId();

            if ($row->value == "1") $row->value = true;
            elseif ($row->value == "0") $row->value = false;

            $out[] = $row;
        }

        $trans->commit();

        return $out;
    }

    function storeDefaultPrivileges($privs) {
        $trans = $this->db->beginRW();

        foreach ($privs as $priv) {
            if (!is_null($priv->id)) {
                $trans->query("UPDATE system_privileges SET default_value = %s WHERE id = %s", $priv->value, $priv->id);
            }
        }

        $trans->commit();
    }

    function storeUserPrivileges($privs) {
        $trans = $this->db->beginRW();

        foreach ($privs as $priv) {
            if (!is_null($priv->user_id) && !is_null($priv->privilege_id)) {
                if (is_null($priv->value)) {
                    $trans->query("DELETE FROM system_privileges_user
                        WHERE user_id = %s AND privilege_id = %s", $priv->user_id, $priv->privilege_id);
                } else {
                    $trans->query("INSERT INTO system_privileges_user (user_id, privilege_id, value)
                        VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE value = VALUES(value)",
                        $priv->user_id, $priv->privilege_id, $priv->value);
                }
            }
        }

        $trans->commit();
    }

    function storeGroupPrivileges($privs) {
        $trans = $this->db->beginRW();

        foreach ($privs as $priv) {
            if (!is_null($priv->group_id) && !is_null($priv->privilege_id)) {
                if (is_null($priv->value)) {
                    $trans->query("DELETE FROM system_privileges_group
                        WHERE group_id = %s AND privilege_id = %s", $priv->group_id, $priv->privilege_id);
                } else {
                    $trans->query("INSERT INTO system_privileges_group (group_id, privilege_id, value)
                        VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE value = VALUES(value)",
                        $priv->group_id, $priv->privilege_id, $priv->value);
                }
            }
        }

        $trans->commit();
    }

    function loadGroupInfo($groupId) {
        $trans = $this->db->beginRO();
        $res = $trans->query("SELECT id, name FROM groups WHERE id = %s", $groupId)->fetch("\\models\\Group");
        $trans->commit();

        return $res;
    }

    function storeGroupInfo(\models\Group $group) {
        $trans = $this->db->beginRW();

        if (is_null($group->getId())) {
            $trans->query("INSERT INTO groups (name) VALUES (%s)", $group->getName());
            $group->setId($trans->lastInsertId());
        } else {
            if ($group->isChanged("name")) {
                $trans->query("UPDATE groups SET name = %s WHERE id = %s", $group->getName(), $group->getId());
            }
        }

        $trans->commit();
    }

    function addUserToGroup(\models\User $user, \models\Group $group) {
        if (!is_null($user->getId()) && $user->getId() > 0 && !is_null($group->getId()) && $group->getId() > 0) {
            $trans = $this->db->beginRW();
            $trans->query("INSERT IGNORE INTO user_group (user_id, group_id) VALUES (%s, %s)", $user->getId(), $group->getId());
            $trans->commit();
        }
    }

    function removeUserFromGroup(\models\User $user, \models\Group $group) {
        $trans = $this->db->beginRW();
        $trans->query("DELETE FROM user_group WHERE user_id = %s AND group_id = %s", $user->getId(), $group->getId());
        $trans->commit();
    }

    function removeGroup(\models\Group $group) {
        $trans = $this->db->beginRW();
        $trans->query("DELETE FROM groups WHERE id = %s", $group->getId());
        $trans->commit();
    }

    function listSystemVariables() {
        $trans = $this->db->beginRO();
        $res = $trans->query("SELECT id, name, value FROM system_config ORDER BY name ASC");
        $res->setClassFactory("\\models\\SystemVariable");

        $out = array();
        foreach ($res as $row) {
            $out[] = $row;
        }

        $trans->commit();

        return $out;
    }

    function setSystemVariables($variables) {
        $trans = $this->db->beginRW();

        foreach ($variables as $var) {
            $trans->query("UPDATE system_config SET value = %s WHERE name = %s", $var->value, $var->name);
        }

        $trans->commit();
    }

    function listUsersOfPrivilege(\models\SystemPrivilege $privilege) {
        $trans = $this->db->beginRO();

        $res = $trans->query("SELECT
                id AS privilege_id,
                NULL AS user_id,
                NULL AS group_id,
                sp.default_value AS value
            FROM system_privileges sp WHERE id = %s

            UNION

            SELECT
                spg.privilege_id AS privilege_id,
                ug.user_id,
                ug.group_id,
                spg.value
            FROM user_group ug
            JOIN system_privileges_group spg ON (spg.group_id = ug.group_id AND spg.privilege_id = %s)

            UNION

            SELECT
                spu.privilege_id AS privilege_id,
                spu.user_id,
                NULL AS group_id,
                spu.value AS value
            FROM system_privileges_user spu WHERE spu.privilege_id = %s",
            $privilege->getId(), $privilege->getId(), $privilege->getId());

        $map = array();

        $groupsToFetch = array();

        foreach ($res as $row) {
            if (is_null($row->user_id) && is_null($row->group_id)) {
                $priv = new \models\SystemPrivilege;
                $priv->setId($row->privilege_id);
                $priv->setValue($row->value);
            } elseif (!is_null($row->group_id)) {
                $priv = new \models\GroupSystemPrivilege;
                $priv->setPrivilege_id($row->privilege_id);
                $priv->setGroup_id($row->group_id);
                $priv->setValue($row->value);

                $groupsToFetch[] = $row->group_id;
            } else {
                $priv = new \models\UserSystemPrivilege;
                $priv->setPrivilege_id($row->privilege_id);
                $priv->setUser_id($row->user_id);
                $priv->setValue($row->value);
            }

            $map[$row->user_id] = $priv;
        }

        $groupsMap = array();
        if (!empty($groupsToFetch)) {
            $strings = array();
            $values = array();

            foreach ($groupsToFetch as $gId) {
                $strings[] = "%s";
                $values[] = $gId;
            }
            
            $res = $trans->query("SELECT id, name FROM groups WHERE id IN (".implode(", ", $strings).")", $values);
            $res->setClassFactory("\\models\\Group");
            foreach ($res as $row) {
                $groupsMap[$row->id] = $row;
            }
        }

        $out = array();
        if (!empty($map)) {
            $strings = array();
            $values = array();

            foreach ($map as $key=>$val) {
                if (!is_null($key)) {
                    $strings[] = "%s";
                    $values[] = $key;
                }
            }

            $res = $trans->query("SELECT id, name FROM users WHERE id IN (".implode(", ", $strings).") ORDER BY name ASC", $values);
            $res->setClassFactory("\\models\\UserAppliedPrivilege");
            foreach ($res as $row) {
                $priv = $map[$row->id];
                $row->priv_source = $priv;

                if ($priv instanceof \models\GroupSystemPrivilege) {
                    $priv->group = $groupsMap[$priv->group_id];
                }

                $out[] = $row;
            }
        }

        $trans->commit();

        return array($map[NULL], $out);
    }

    function storeComment(\models\Comment $comment) {
        $trans = $this->db->beginRW();

        $diag = new Diagnostics();

        // Try if there is no registered user of the same name
        if (!is_null($comment->anonymous_name)) {
            $res = $trans->query("SELECT id FROM users WHERE name = %s", $comment->anonymous_name);
            try {
                $res->fetch();
                $diag->addError("anonymous_name", "anonymous_name_cannot_be_registered", "This user name is registered in the wiki. You cannot post as a registered user.");
            } catch (\drivers\EntryNotFoundException $e) {
            }
        }

        // Verify parent ID
        if (!is_null($comment->parent_id)) {
            $res = $trans->query("SELECT id FROM comments WHERE id = %s", $comment->parent_id);
            if (is_null($res->fetch())) {
                $diag->addError("parent_id", "parent_id_must_exists", "Comment parent does not exists.");
            }
        }

        // Verify text emptiness
        if ($comment->text_html == "" || $comment->text_wiki == "") {
            $diag->addError("text_wiki", "text_wiki_cannot_be_empty", "Comment text cannot be empty.");
        }

        if ($diag->getErrors()) {
            $trans->rollback();
            throw $diag;
        }

        if (!is_null($comment->getId())) {
            $trans->query("INSERT INTO comments_history (comment_id, revision, last_modified, user_id,      ip, text_wiki, text_html)
                            SELECT                       id,         revision, last_modified, edit_user_id, ip, text_wiki, text_html)
                            FROM comments WHERE id = %s", $comment->getId());

            $vals = array();
            $cols = array();

            foreach ($comment->listChanged as $col) {
                $cols[] = $col." = %s";
                $vals[] = $comment->$col;
            }

            $cols[] = "ip = %s";
            $vals[] = \lib\Session::IP();

            $cols[] = "last_modified = NOW()";

            $vals[] = $comment->getId();
            $trans->query("UPDATE comments SET ".implode(",", $cols)." WHERE id = %s", $vals);
        } else {
            $trans->query("INSERT INTO comments (page_id, revision, owner_user_id, edit_user_id,
                anonymous_name, ip, parent_id, created, last_modified, text_wiki, text_html) VALUES
                (%s, %s, %s, %s, %s, %s, %s, NOW(), NOW(), %s, %s)",
                $comment->page_id, $comment->revision, $comment->owner_user_id, $comment->edit_user_id,
                $comment->anonymous_name, \lib\Session::IP(), $comment->parent_id, $comment->text_wiki,
                $comment->text_html);
            $comment->setId($trans->lastInsertId());
        }

        // Store wiki page links
        $trans->query("DELETE FROM comments_links WHERE comment_id = %s", $comment->getId());

        $query = "INSERT INTO comments_links (comment_id, ref_page_id, ref_page_name) VALUES ";
        $ins = array();
        $vals = array();

        foreach ($comment->wiki_page_links as $link {
            $ins[] = "(%s, %s, %s)";
            $vals[] = $comment->getId();
            if (is_int($link)) {
                $vals[] = $link;
                $vals[] = NULL;
            } else {
                $vals[] = NULL;
                $vals[] = implode('/', $link);
            }
        }

        if (!empty($ins)) {
            $query .= implode(", ", $ins);
            $trans->query($query, $vals);
        }

        $trans->commit();
    }

    function loadComments(\models\WikiPage $page) {
        $trans = $this->db->beginRO();

        $res = $trans->query("SELECT c.id, c.revision, c.owner_user_id, c.edit_user_id, c.anonymous_name,
            c.ip, c.parent_id, c.created, c.last_modified, c.text_html,
            uo.name AS owner_user_name, ue.name AS edit_user_name
            FROM comments c
            LEFT JOIN users uo ON (c.owner_user_id = uo.id)
            LEFT JOIN users ue ON (c.edit_user_id = ue.id)
            WHERE page_id = %s", $page->getId());

        // Build comments hierarchy
        $comments = array();
        $comments_by_id = array();

        foreach ($res as $row) {
            if (!isset($comments[$row->parent_id])) {
                $comments[$row->parent_id] = array();
            }

            $comment = new \models\Comment;
            $comment->id = $row->id;
            $comment->revision = $row->revision;

            if ($row->owner_user_id > 0) {
                $comment->OwnerUser = new \models\User;
                $comment->OwnerUser->id = $row->owner_user_id;
                $comment->OwnerUser->name = $row->owner_user_name;
            } else {
                $comment->OwnerUser = new \models\FakeUser;
                $comment->OwnerUser->name = $row->anonymous_name;
                $comment->OwnerUser->ip = $row->ip;
            }

            $comment->created = $row->created;
            $comment->last_modified = $row->last_modified;
            $comment->text_html = $row->text_html;
            $comment->clearChanged();

            $comments[$row->parent_id][] = $comment;
            $comments_by_id[$row->id] = $comment;
        }

        foreach ($comments as $parent=>&$childs) {
            if (empty($parent)) $parent = NULL;
            if (!is_null($parent)) {
                $comments_by_id[$parent]->childs = &$childs;
            }
        }

        $trans->commit();

        if (isset($comments[NULL])) {
            return $comments[NULL];
        } else {
            return array();
        }
    }

    function loadComment($commentId) {
        $trans = $this->db->beginRO();

        $res = $trans->query("SELECT c.id, c.page_id, c.revision, c.owner_user_id, c.edit_user_id, c.anonymous_name,
            c.ip, c.created, c.last_modified, c.text_html, uo.name AS owner_user_name, ue.name AS edit_user_name
            FROM comments c
            LEFT JOIN users uo ON (c.owner_user_id = uo.id)
            LEFT JOIN users ue ON (c.edit_user_id = ue.id)
            WHERE c.id = %s", $commentId);

        $row = $res->fetch();

        $comment = new \models\Comment;
        $comment->id = $row->id;
        $comment->page_id = $row->page_id;
        $comment->revision = $row->revision;

        if ($row->owner_user_id > 0) {
            $comment->OwnerUser = new \models\User;
            $comment->OwnerUser->id = $row->owner_user_id;
            $comment->OwnerUser->name = $row->owner_user_name;
        } else {
            $comment->OwnerUser = new \models\FakeUser;
            $comment->OwnerUser->name = $row->anonymous_name;
            $comment->OwnerUser->ip = $row->ip;
        }

        $comment->created = $row->created;
        $comment->last_modified = $row->last_modified;
        $comment->text_html = $row->text_html;
        $comment->clearChanged();

        $trans->commit();

        return $comment;
    }
}

class DBSessionStorage implements \lib\SessionStorage {
    protected $lifeTime = array();
    protected $storage = array();
    protected $idLoaded = array();
    protected $idChanged = array();

    function __construct($db) {
        $this->sql = $db;

        $trans = $this->sql->beginRW();
        $trans->query("DELETE FROM sessions WHERE activity < DATE_ADD(NOW(), INTERVAL -lifetime SECOND)");
        $trans->commit();
    }

    function __destruct() {
        $trans = $this->sql->beginRW();

        foreach ($this->storage as $id=>&$data) {
            // Skip NULL IDs
            if (!$id) continue;

            $lifeTime = 3600;
            if (isset($this->lifeTime[$id])) {
                $lifeTime = $this->lifeTime[$id];
            }

            // Load all session data if not loaded.
            if (!isset($this->idLoaded[$id])) {
                $this->load($id, NULL, $trans);
            }

            $ip = $_SERVER["REMOTE_ADDR"];

            $sets = array();
            $values = array();

            foreach ($data as $key=>$tuple) {
                if ($tuple->persistent == \lib\SessionDataTuple::MARK_UNSET) {
                    unset($data[$key]);
                    $trans->query("DELETE FROM sessions WHERE sessid = %s AND name = %s", $id, $key);
                } else {
                    if ($tuple->persistent == \lib\SessionDataTuple::NON_PERSISTENT) {
                        $tuple->persistent = \lib\SessionDataTuple::MARK_UNSET;
                    }

                    $sets[] = "(%s, NOW(), %s, %s, %s, %s, %s, %s)";

                    if (!is_scalar($tuple->data)) {
                        $type = 'binary';
                        $value = base64_encode(serialize($tuple->data));
                    } else {
                        $type = 'plain';
                        $value = $tuple->data;
                    }

                    $values = array_merge($values, array($id, $lifeTime, $ip, $key, $tuple->persistent, $type, $value));
                }
            }
            
            if (!empty($values)) {
                $trans->Query("INSERT INTO sessions (sessid, activity, lifetime, ip, name, persistent, type, value) VALUES ".implode(",", $sets)."
                        ON DUPLICATE KEY UPDATE
                        activity = VALUES(activity),
                        lifetime = VALUES(lifetime),
                        type = VALUES(type), persistent = VALUES(persistent), value = VALUES(value)", $values);
            }
        }

        $trans->commit();
    }

    function setLifeTime($id, $lifeTime) {
        if (!isset($this->lifeTime[$id]) || $this->lifeTime[$id] != $lifeTime) {
            $trans = $this->sql->beginRW();
            $trans->query("UPDATE sessions SET lifetime = %d WHERE sessid = %s", $lifeTime, $id);
            $trans->commit();
            $this->lifeTime[$id] = $lifeTime;
        }
    }

    function store($id, $name, &$value, $persistent = true) {
        if (!isset($this->storage[$id])) {
            $this->storage[$id] = array();
        }

        if (is_null($value)) {
            if (isset($this->storage[$id][$name])) {
                $this->storage[$id][$name]->persistent = \lib\SessionDataTuple::MARK_UNSET;
            }
        } else {
            $this->storage[$id][$name] = new \lib\SessionDataTuple($value, $persistent);
        }
        $this->idChanged[$id] = true;
    }

    function load($id, $name, $trans = NULL) {
        if (!isset($this->idLoaded[$id]) && !is_null($id)) {
            try {
                // Here we are selecting from master because of replication delay.
                $transactionStarted = false;
                if (is_null($trans)) {
                    $trans = $this->sql->beginRW();
                    $transactionStarted = true;
                }

                $data = $trans->query("SELECT name, persistent, type, value, lifetime FROM sessions WHERE sessid = %s", $id);


                // Do not overwrite existing data
                if (!isset($this->storage[$id])) {
                    $this->storage[$id] = array();
                }

                foreach ($data as $row) {
                    $lifeTime = $row->lifetime;

                    if ($row->type != "plain") {
                        $row->value = unserialize(base64_decode($row->value));
                    }

                    if (!isset($this->storage[$id][$row->name])) {
                        $this->storage[$id][$row->name] = new \lib\SessionDataTuple($row->value, $row->persistent);
                    }
                }

                if (!isset($this->lifeTime[$id])) {
                    $this->lifeTime[$id] = $lifeTime;
                    \lib\Session::setLifeTime($lifeTime);
                }

                if ($transactionStarted) {
                    $trans->commit();
                }
            } catch (\drivers\EntryNotFoundException $e) {
            }

            $this->idLoaded[$id] = true;
        }

        if (isset($this->storage[$id][$name]) && $this->storage[$id][$name]->persistent != \lib\SessionDataTuple::MARK_UNSET) {
            return $this->storage[$id][$name]->data;
        } else {
            return NULL;
        }
    }
}
