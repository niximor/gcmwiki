<?php

namespace storage\MySQL;

require_once "storage/MySQL/base.php";
require_once "lib/wikiformatter.php";

class Pages extends Module {
    protected function formatPageText(\models\WikiPage $page) {
        $f = new \lib\formatter\WikiFormatterFull();

        $page->body_html = $f->format($page->body_wiki, $page);
        $this->base->cache->storeWikiCache("wiki-page-".$page->getId()."-".$page->getRevision(), $page->body_html);

        // Store page links
        $root = $f->getRootContext();
        if (isset($root->WIKI_LINKS) && is_array($root->WIKI_LINKS)) {
            $links = array_unique($root->WIKI_LINKS, SORT_REGULAR);

            $query = "INSERT INTO wiki_page_references (wiki_page_id, wiki_page_revision, ref_page_id, ref_page_name) VALUES ";
            $ins = array();
            $vals = array();

            foreach ($links as $link) {
                $ins[] = "(%s, %s, %s, %s)";
                $vals[] = $page->getId();
                $vals[] = $page->getRevision();

                if (is_int($link)) {
                    $vals[] = $link;
                    $vals[] = NULL;
                } else {
                    $vals[] = NULL;
                    $vals[] = implode("/", $link);
                }
            }

            $query .= implode(", ", $ins);

            if (!empty($ins)) {
                $transactionStarted = false;
                if ($this->base->currentTransaction) {
                    $trans = $this->base->currentTransaction;
                } else {
                    $trans = $this->base->db->beginRW();
                    $transactionStarted = true;
                }

                $trans->query("DELETE FROM wiki_page_references WHERE wiki_page_id = %s AND wiki_page_revision = %s", $page->getId(), $page->getRevision());
                $trans->query($query, $vals);

                if ($transactionStarted) {
                    $trans->commit();
                }
            }
        }
    }

    protected function constructPageWhere($parent, $urlName, $followRedirect, $idColumn) {
        if ($followRedirect) {
            if (is_null($parent)) {
                return array($idColumn." = GET_PAGE_ID(%s, NULL)", array($urlName));
            } else {
                return array($idColumn." = GET_PAGE_ID(%s, %s)", array($urlName, $parent->getId()));
            }
        } else {
            if (is_null($parent)) {
                return array("p.url = %s AND p.parent_id IS NULL", array($urlName));
            } else {
                return array("p.url = %s AND p.parent_id = %s", array($urlName, $parent->getId()));
            }
        }
    }

    public function loadPage($path, $requiredColumns = NULL, $revision = NULL, $followRedirect = true) {
        if (is_null($this->base->currentTransaction)) {
            $trans = $this->base->db->beginRO();
            $transactionStarted = true;
        } else {
            $trans = $this->base->currentTransaction;
            $transactionStarted = false;
        }

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
        $lastPart = false;
        $currentRev = NULL;

        for ($i = 0; $i < $part_len; ++$i) {
            $part = $path[$i];

            list($pgWhere, $params) = $this->constructPageWhere($parent, $part, $followRedirect, "p.id");
            $res = $trans->query("SELECT p.id, p.name, p.url, p.user_id, p.ip, p.revision, u.name AS user_name
                FROM wiki_pages p
                JOIN users u ON (p.user_id = u.id)
                WHERE ".$pgWhere, $params);

            try {
                $row = $res->fetch();

                $page = new \models\WikiPage();
                $page->setParent($parent);

                $page->setId($row->id);
                $page->setName($row->name);
                $page->setUrl($row->url);
                $page->setUserId($row->user_id);
                $page->setRevision($row->revision);

                if ($row->user_id > 0) {
                    $page->User = new \models\User;
                    $page->User->id = $row->user_id;
                    $page->User->name = $row->user_name;
                } else {
                    $page->User = new \models\FakeUser;
                    $page->User->ip = $row->ip;
                }

                $parent = $page;
            } catch (\drivers\EntryNotFoundException $e) {
                if ($transactionStarted) {
                    $trans->commit();
                }
                throw new \storage\PageNotFoundException($part, $parent, $e);
            }
        }

        // Here we have current page loaded in $parent.
        $join = array();

        $columns = array_merge(array("id", "url", "name", "revision", "last_modified", "user_id", "ip"), $requiredColumns);
        $columns[] = "redirect_to";

        $loadRenderedBody = false;
        if (($key = array_search('body_html', $columns)) !== false) {
            unset($columns[$key]);
            $loadRenderedBody = true;
            $columns[] = "body_wiki";
        }

        array_walk($columns, function(&$a) { $a = "p.".$a; });

        $columns[] = "u.name AS user_name";

        $params = array();

        // Decide whether user requested current or historical revision of pages.
        $currentRev = true;
        if (!is_null($revision) && $parent->getRevision() != $revision) {
            $curretRev = false;
            if (($key = array_search("p.id", $columns)) !== false) {
                $columns[$key] = "p.page_id AS id";
            }

            $table = "wiki_pages_history";
            $where = "p.page_id = %s AND revision = %s";
            $params[] = $parent->getId();
            $params[] = $revision;
        } else {
            $table = "wiki_pages";
            $where = "p.id = %s";
            $params[] = $parent->getId();
        }

        $join[] = "JOIN users u ON (p.user_id = u.id)";

        if ($loadRenderedBody) {
            $columns[] = "cache.wiki_text AS body_html";
            $join[] = "LEFT JOIN wiki_text_cache cache ON (cache.key = CONCAT('wiki-page-', p.id, '-', p.revision) AND cache.valid = 1)";
        }

        $query = "SELECT ".implode(",", $columns)." FROM ".$table." p
            ".implode(" ", $join)." WHERE ".$where;

        $res = $trans->query($query, $params);

        try {
            $row = $res->fetch();

            if (isset($row->id)) $parent->id = $row->id;
            if (isset($row->name)) $parent->name = $row->name;
            if (isset($row->url)) $parent->url = $row->url;
            if (isset($row->created)) $parent->created = $row->created;
            if (isset($row->last_modified)) $parent->last_modified = $row->last_modified;
            if (isset($row->user_id)) $parent->user_id = $row->user_id;
            if (isset($row->revision)) $parent->revision = $row->revision;
            if (isset($row->body_wiki)) $parent->body_wiki = $row->body_wiki;
            if ($loadRenderedBody) {
                if (is_null($row->body_html) || \Config::Get("DisablePageCache", false)) {
                    $this->base->currentTransaction = $trans;
                    $this->formatPageText($parent);
                    $this->base->currentTransaction = NULL;
                } else {
                    $page->body_html = $row->body_html;
                    $page->setWasCached(true);
                }
            }
            if (isset($row->summary)) $parent->summary = $row->summary;
            if (isset($row->small_change)) $parent->small_change = $row->small_change;
            if (isset($row->redirect_to)) $parent->redirect_to = $row->redirect_to;
            if (isset($row->locked)) $parent->locked = $row->locked;
            if (isset($row->renderer)) $parent->renderer = $row->renderer;

            if (strtolower($row->url) != strtolower($part)) {
                $this->base->currentTransaction = $trans;
                $parent->redirected_from = $this->loadPage($path, NULL, NULL, false);
                $this->base->currentTransaction = NULL;
            }

            if ($row->user_id > 0) {
                $page->User = new \models\User;
                $page->User->id = $row->user_id;
                $page->User->name = $row->user_name;
            } else {
                $page->User = new \models\FakeUser;
                $page->User->ip = $row->ip;
            }

            $parent->setIsCurrentRevision($currentRev);
        } catch (\drivers\EntryNotFoundException $e) {
            if ($transactionStarted) {
                $trans->commit();
            }
            throw new \storage\PageNotFoundException($part, $parent->getParent(), $e);
        }

        if ($transactionStarted) {
            $trans->commit();
        }

        return $parent;
    }

    function listSubpages(\models\WikiPage $page = NULL, $requiredColumns = NULL) {
        if (is_null($requiredColumns)) $requiredColumns = array();
        $columns = array_merge($requiredColumns, array("id", "url", "name"));

        $transactionStarted = false;
        if ($this->base->currentTransaction) {
            $trans = $this->base->currentTransaction;
        } else {
            $trans = $this->base->db->beginRO();
            $transactionStarted = true;
        }

        if (!is_null($page)) {
            $res = $trans->query("SELECT ".implode(",", $columns)." FROM wiki_pages WHERE parent_id = %s ORDER BY name ASC", $page->getId());
        } else {
            $res = $trans->query("SELECT ".implode(",", $columns)." FROM wiki_pages WHERE parent_id IS NULL ORDER BY name ASC");
        }
        $res->setClassFactory("\\models\\WikiPage");

        $out = array();
        foreach ($res as $row) {
            $out[] = $row;
        }

        if ($transactionStarted) {
            $trans->commit();
        }

        return $out;
    }

    protected function updatePage(\models\WikiPage $page, $trans = NULL) {
        $transactionStarted = false;
        if (is_null($trans)) {
            $trans = $this->base->db->beginRW();
            $transactionStarted = true;
        }

        $columns = array();
        $values = array();

        $urlChanged = false;
        $incRevision = false;

        foreach ($page->listChanged() as $column) {
            if (in_array($column, array("name", "url", "body_wiki", "user_id", "small_change", "summary", "redirect_to", "locked", "renderer", "template"))) {
                $columns[] = $column." = %s";
                $values[] = $page->$column;

                if (in_array($column, array("body_wiki", "url", "name", "locked", "renderer"))) {
                    $incRevision = true;
                }

                if ($column == "url") $urlChanged = true;
            }
        }

        if (!empty($columns)) {
            if ($incRevision) {
                $trans->query("INSERT INTO wiki_pages_history (page_id, name, parent_id, url, body_wiki, user_id, small_change, summary, ip, last_modified, revision, redirect_to, locked, renderer)
                                SELECT                         id,      name, parent_id, url, body_wiki, user_id, small_change, summary, ip, last_modified, revision, redirect_to, locked, renderer
                                FROM wiki_pages WHERE id = %s", $page->getId());

                $columns[] = "last_modified = NOW()";

                $columns[] = "user_id = %s";
                $values[] = \lib\CurrentUser::ID();

                $columns[] = "ip = %s";
                $values[] = \lib\Session::IP();

                $columns[] = "revision = revision + 1";
            }

            $values[] = $page->getId();

            $trans->query("UPDATE wiki_pages SET ".implode(",", $columns)." WHERE id = %s", $values);

            if ($incRevision) {
                $page->setRevision($page->getRevision() + 1);
            }

            // Invalidate all referencing pages when template was changed.
            if (substr($page->getName(), 0, strlen("template:")) == "template:") {
                $trans->query("DELETE c FROM wiki_text_cache c
                JOIN wiki_page_references r ON c.`key` = CONCAT('wiki-page-', r.wiki_page_id, '-', r.wiki_page_revision)
                WHERE r.ref_page_id = %s", $page->getId());
            }
        }

        if ($transactionStarted) {
            $trans->commit();
        }

        $page->clearChanged();
    }

    protected function createPage(\models\WikiPage $page, $trans = NULL) {
        $transactionStarted = false;
        if (is_null($trans)) {
            $trans = $this->base->db->beginRW();
            $transactionStarted = true;
        }

        $parentId = NULL;

        if ($page->getParent()) $parentId = $page->getParent()->getId();

        $trans->query("INSERT INTO wiki_pages (name, parent_id, url, created, last_modified, user_id, body_wiki, small_change, summary, ip, locked, renderer, template)
                              VALUES          (%s,   %s,        %s,  NOW(),   NOW(),         %s,      %s,        %s,           %s,      %s, %s,     %s,       %s)",
                $page->getName(), $parentId, $page->getUrl(), \lib\CurrentUser::ID(), $page->getBody_wiki(),
                $page->getSmall_change(), $page->getSummary(), \lib\Session::IP(), $page->getLocked(),
                $page->getRenderer(), $page->getTemplate());
        $page->setId($trans->lastInsertId());
        $page->setRevision(1);

        // Delete rendered text which points to this newly created page to allow changing nonexisting links
        // to existing ones.
        $trans->query("DELETE c FROM wiki_text_cache c
            JOIN wiki_page_references r ON c.`key` = CONCAT('wiki-page-', r.wiki_page_id, '-', r.wiki_page_revision)
            WHERE r.ref_page_name = %s", $page->getFullUrl());

        if ($transactionStarted) {
            $trans->commit();
        }

        $page->clearChanged();
    }

    public function storePage(\models\WikiPage $page) {
        $diag = new \storage\Diagnostics();

        $name = $page->getName();
        if (empty($name)) {
            $diag->addError("name", "name_must_be_present", "Page name must be filled in.");
        }

        $url = $page->getUrl();
        if (empty($url)) {
            $diag->addError("url", "url_must_be_present", "Page url must be filled in.");
        }

        if ($page->getId()) {
            $summary = $page->getSummary();
            if (!$page->getSmall_change() && empty($summary)) {
                $diag->addError("summary", "summary_must_be_present", "Summary field is required if not small change.");
            }
        } else {
            $page->setSmall_change(false);
            if (is_null($page->getSummary())) {
                $page->setSummary("");
            }
        }

        $trans = $this->base->db->beginRW();

        // Test for name duplicity.
        if ($page->getId()) {
            if (is_null($page->getParent())) {
                $res = $trans->query("SELECT id FROM wiki_pages WHERE url = %s AND parent_id IS NULL AND id <> %s", $page->getUrl(), $page->getId());
            } else {
                $res = $trans->query("SELECT id FROM wiki_pages WHERE url = %s AND parent_id = %s AND id <> %s", $page->getUrl(), $page->getParent()->getId(), $page->getId());
            }
        } else {
            if (is_null($page->getParent())) {
                $res = $trans->query("SELECT id FROM wiki_pages WHERE url = %s AND parent_id IS NULL", $page->getUrl());
            } else {
                $res = $trans->query("SELECT id FROM wiki_pages WHERE url = %s AND parent_id = %s", $page->getUrl(), $page->getParent()->getId());
            }
        }

        if ($res->valid()) {
            $diag->addError("name", "name_already_exists", "Page with same or simillar name already exists.");
        }

        if ($diag->getErrors()) {
            $trans->rollback();
            throw $diag;
        }

        $nameChanged = $page->isChanged("name");

        if ($page->getId()) {
            $this->updatePage($page, $trans);
        } else {
            $this->createPage($page, $trans);
        }

        // Clear redirect_to column. It is set again by formatPageText(), if {{{redirect}}} directive
        // is present in the wiki text. Otherwise, it should get cancelled.
        $page->setRedirect_to(NULL);
        $this->base->currentTransaction = $trans;
        $this->formatPageText($page);
        $this->base->currentTransaction = NULL;

        if ($page->isChanged()) {
            // If formatter updates the page, store it again.
            $this->updatePage($page, $trans);
        }

        $trans->commit();

        if ($nameChanged) {
            \models\WikiPage::$nameChangeObserver->notifyObservers($page);
        }

        \models\WikiPage::$pageChangeObserver->notifyObservers($page);
    }

    public function updateRedirects(\models\WikiPage $oldPage, \models\WikiPage $newPage) {
        $trans = $this->base->db->beginRW();
        $trans->query("UPDATE wiki_pages SET redirect_to = %s WHERE redirect_to = %s", $newPage->getId(), $oldPage->getId());
        $trans->commit();
    }

    public function getReferencedPages(\models\WikiPage $page) {
        $trans = $this->base->db->beginRW();
        $q = $trans->query("SELECT wiki_page_id FROM wiki_page_references WHERE ref_page_id = %s OR ref_page_name = %s",
            $page->getId(), $page->getUrl());
        $out = array();
        foreach ($q as $row) {
            $out[] = $row;
        }
        $trans->commit();

        return $out;
    }

    public function getHistorySummary(\models\WikiPage $page) {
        $trans = $this->base->db->beginRO();

        $res = $trans->query("SELECT h.id, h.revision, h.last_modified, h.user_id, h.small_change, h.summary,
            u.name AS user_name, h.ip
            FROM wiki_pages_history h
            LEFT JOIN users u ON (h.user_id = u.id)
            WHERE page_id = %s
            ORDER BY revision DESC", $page->getId());

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

    public function loadPageAcl(\models\WikiPage $wikiPage, \models\User $user, $trans = NULL) {
        $acl = new \models\WikiAcl;

        $acls = \models\WikiAcl::listAcls();

        // Admin has anything, do not need to bother the database.
        if ($user->hasPriv("admin_pages")) {
            foreach ($acls as $name) {
                $acl->$name = true;
            }

            return $acl;
        }

        $transactionStarted = false;
        if (is_null($trans)) {
            $trans = $this->base->db->beginRO();
            $transactionStarted = true;
        }

        $query_acls_1 = array_map(function($acl) { return "acl_".$acl." AS ".$acl; }, $acls);
        $query_acls_2 = array_map(function($acl) { return "acl.".$acl; }, $acls);
        $query_acls_3 = array_map(function($acl) { return $acl; }, $acls);

        $res = $trans->query("SELECT
                NULL AS user_id,
                NULL AS group_id,
                ".implode(",", $query_acls_1)."
                FROM wiki_pages WHERE id = %s
            UNION
                SELECT
                    NULL AS user_id,
                    acl.group_id,
                    ".implode(",", $query_acls_2)."
                FROM user_group AS ug JOIN page_acl_group AS acl ON (acl.group_id = ug.group_id)
                WHERE acl.page_id = %s AND ug.user_id = %s
            UNION
                SELECT
                    user_id,
                    NULL AS group_id,
                    ".implode(",", $query_acls_3)."
                FROM page_acl_user
                WHERE page_id = %s AND user_id = %s",
                $wikiPage->getId(),
                $wikiPage->getId(), $user->getId(),
                $wikiPage->getId(), $user->getId());

        $boolean = function($value) {
            if (is_null($value)) {
                return $value;
            } else {
                return (bool)$value;
            }
        };

        // Merge ACLs.
        foreach ($res as $row) {
            foreach ($acls as $name) {
                if (!is_null($row->$name)) $acl->$name = $boolean($row->$name);
            }
        }

        // If we have any uncertain privilege, ask parent.
        if (!empty(array_filter($acls, function($name) use (&$acl) { return is_null($acl->$name); }))) {
            if (!is_null($wikiPage->getParent())) {
                $to_merge = $this->loadPageAcl($wikiPage->getParent(), $user, $trans);
            } else {
                // Default ACLs
                $to_merge = $this->loadDefaultAcl($user, $trans);
            }

            foreach ($acls as $name) {
                if (is_null($acl->$name)) $acl->$name = $boolean($to_merge->$name);
            }
        }

        if ($transactionStarted) {
            $trans->commit();
        }

        return $acl;
    }

    public function loadDefaultAcl(\models\User $user, $trans = NULL) {
        $acl = new \models\WikiAcl;

        $acls = \models\WikiAcl::listAcls();
        foreach ($acls as $name) {
            $acl->$name = $user->hasPriv("acl_".$name);
        }

        return $acl;
    }

    public function listPageAcl(\models\WikiPage $page) {
        $trans = $this->base->db->beginRO();

        $acls = \models\WikiAcl::listAcls();

        $query_acls = array_map(function($acl) { return "acl_".$acl." AS ".$acl; }, $acls);
        $row = $trans->query("SELECT
            ".implode(",", $query_acls)."
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
            $trans = $this->base->db->beginRO();
            $transactionStarted = true;
        }

        $acls = \models\WikiAcl::listAcls();

        $query_acls = array_map(function($acl) { return "acl.".$acl; }, $acls);
        $res = $trans->query("SELECT
                u.id,
                u.name,
                ".implode(",", $query_acls)."
            FROM page_acl_user AS acl
            JOIN users AS u ON (acl.user_id = u.id)
            WHERE page_id = %s", $page->getId());
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
            $trans = $this->base->db->beginRO();
            $transactionStarted = true;
        }

        $acls = \models\WikiAcl::listAcls();

        $query_acls = array_map(function($acl) { return "acl.".$acl; }, $acls);
        $res = $trans->query("SELECT
                g.id,
                g.name,
                ".implode(",", $query_acls)."
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
        $trans = $this->base->db->beginRO();

        $acls = \models\WikiAcl::listAcls();

        $query_acls = array_map(function($acl) use (&$params, &$set) {
            $params[] = $set->default->$acl;
            return "acl_".$acl." = %s";
        }, $acls);
        $query = "UPDATE wiki_pages SET ".implode(",", $query_acls)." WHERE id = %s";

        $params[] = $page->getId();

        $trans->query($query, $params);

        $users = array();
        $res = $trans->query("SELECT user_id FROM page_acl_user WHERE page_id = %s", $page->getId());
        foreach ($res as $u) {
            $users[$u->user_id] = true;
        }

        if (!empty($set->users)) {
            $sets = array();
            $values = array();
            foreach ($set->users as $uacl) {
                if (count(array_filter($acls, function($name) use (&$uacl) { return is_null($uacl->$name); })) == count($acls)) {
                    continue;
                }

                $sets[] = "(%s, %s".str_repeat(", %s", count($acls)).")";
                $values[] = $page->getId();
                $values[] = $uacl->id;

                foreach ($acls as $name) {
                    $values[] = $uacl->$name;
                }

                if (isset($users[$uacl->id])) unset($users[$uacl->id]);
            }

            if (!empty($sets)) {
                $trans->query("INSERT INTO page_acl_user
                    (page_id, user_id, ".implode(",", $acls).")
                    VALUES ".implode(",", $sets)."
                    ON DUPLICATE KEY UPDATE
                        ".implode(",", array_map(function($name) {
                            return $name." = VALUES(".$name.")";
                        }, $acls)), $values);
            }
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

        if (!empty($set->groups)) {
            $sets = array();
            $values = array();
            foreach ($set->groups as $gacl) {
                if (count(array_filter($acls, function($name) use (&$gacl) { return is_null($gacl->$name); })) == count($acls)) {
                    continue;
                }

                $sets[] = "(%s, %s".str_repeat(", %s", count($acls)).")";
                $values[] = $page->getId();
                $values[] = $gacl->id;

                foreach ($acls as $name) {
                    $values[] = $gacl->$name;
                }

                if (isset($groups[$gacl->id])) unset($groups[$gacl->id]);
            }

            if (!empty($sets)) {
                $trans->query("INSERT INTO page_acl_group
                    (page_id, group_id, ".implode(",", $acls).")
                    VALUES ".implode(",", $sets)."
                    ON DUPLICATE KEY UPDATE
                        ".implode(",", array_map(function($name) {
                            return $name." = VALUES(".$name.")";
                        }, $acls)), $values);
            }
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

    /**
     * List begining letters of pages with number of pages belonging to given letter.
     * @return array $struct
     *     - string $letter Begining letter of page name.
     *     - int $numOfPages Number of pages belonging to given letter.
     */
    function listPagesLetters() {
        $trans = $this->base->db->beginRO();

        $res = $trans->query("SELECT
                IF(UPPER(LEFT(`name`, 1)) BETWEEN 'A' AND 'Z', UPPER(LEFT(`name`, 1)), '#') AS `letter`,
                COUNT(id) AS `numOfPages`
            FROM `wiki_pages`
            GROUP BY `letter`
            ORDER BY `letter` ASC");

        $out = array();
        foreach ($res as $row) {
            $out[] = $row;
        }

        $trans->commit();

        return $out;
    }

    /**
     * Helper method to load all parents of pages, effective way.
     */
    protected function loadAllParents($trans, $parent_ids) {
        $out = array();
        $new_parents = array();

        if (count($parent_ids) > 0) {
            $res = $trans->query("SELECT `id`, `name`, `url`, `parent_id` FROM `wiki_pages` WHERE `id` IN (".implode(",", array_fill(0, count($parent_ids), "%s")).")", array_keys($parent_ids));
            $res->setClassFactory("\\models\\WikiPage");

            foreach ($res as $row) {
                $out[$row->getId()] = $row;

                if (!is_null($row->getParentId())) {
                    $new_parents[$row->getParentId()] = true;
                }
            }

            if (!empty($new_parents)) {
                $new_parents = $this->loadAllParents($trans, $new_parents);
                foreach ($out as $page) {
                    if (!is_null($page->getParentId())) {
                        $page->setParent($new_parents[$page->getParentId()]);
                    }
                }
            }
        }

        return $out;
    }

    /**
     * List pages with given filter conditions.
     * @param $filter Filter.
     *     - array $columns List of columns to load.
     *         id, name, url, created, last_modified, revision, parent_id, links, references
     *
     *     - string $letter First letter (A-Z or # for anything that is not A-Z).
     *     - int $linksTo Load pages that links to specified page ID.
     *
     *     - int $limit Number of pages.
     *     - int $offset Offset of paging.
     *
     *     - array $sort List of columns to use for sorting. Number of items in sort and direction must be equal.
     *     - array $direction Direction of sorting (ASC/DESC). Number of items in sort and direction must be equal.
     * @return struct $response
     *     - int $totalCount Total number of pages not affecting the limit and offset.
     *     - array $pages WikiPages matching current filter criteria.
     */
    function listPages(\lib\Object $filter) {
        $columns = array();
        $values = array();
        $joins = array();
        $where = array();
        $group = array();

        foreach ($filter->getOrDefault("columns", array("id", "name", "url", "created", "last_modified", "revision", "parent_id")) as $column) {
            switch ($column) {
                case "id":
                case "name":
                case "url":
                case "created":
                case "last_modified":
                case "revision":
                case "parent_id":
                    $columns[] = "`p`.`".$column."`";
                    break;

                case "links":
                    $columns[] = "COUNT(`rl`.`id`) AS `links`";
                    $joins[] = "LEFT JOIN `wiki_page_references` `rl` ON (`rl`.`wiki_page_id` = `p`.`id` AND `rl`.`wiki_page_revision` = `p`.`revision`)";
                    break;

                case "references":
                    $columns[] = "COUNT(DISTINCT `rr`.`wiki_page_id`) AS `references`";
                    $joins[] = "LEFT JOIN `wiki_page_references` `rr` ON (`rr`.`ref_page_id` = `p`.`id`)";
                    break;
            }
        }

        if (!is_null($letter = $filter->getOrDefault("letter", NULL))) {
            if ($letter == "#") {
                $where[] = "UPPER(LEFT(`name`, 1)) NOT BETWEEN 'A' AND 'Z'";
            } else {
                $where[] = "UPPER(LEFT(`name`, 1)) = %s";
                $values[] = $letter;
            }
        }

        if (!is_null($linksTo = $filter->getOrDefault("linksTo", NULL))) {
            $joins[] = "JOIN `wiki_page_references` `rl_w` ON (`rl_w`.`wiki_page_id` = `p`.`id` AND `rl_w`.`wiki_page_revision` = `p`.`revision`)";
            $where[] = "`rl_w`.`ref_page_id` = %s";
            $values[] = $linksTo;
        }

        $query = "SELECT SQL_CALC_FOUND_ROWS ".implode(",", array_unique($columns))." FROM `wiki_pages` `p` ".implode(" ", array_unique($joins));

        if (!empty($where)) {
            $query .= " WHERE ".implode(" AND ", $where);
        }

        $query .= " GROUP BY `p`.`id`";

        $sort = $filter->getOrDefault("sort", array());
        $direction = $filter->getOrDefault("direction", array());

        $map = function($column) {
            switch($column) {
                case "id":
                case "name":
                case "url":
                case "created":
                case "last_modified":
                case "revision":
                case "parent_id":
                    return "`p`.`".$column."`";

                case "links":
                case "references":
                    return "`".$column."`";

                default:
                    return $column;
            }
        };

        for ($i = 0; $i < min(count($sort), count($direction)); ++$i) {
            if ($i == 0) {
                $query .= " ORDER BY ";
            }

            $query .= $map($sort[$i])." ".$direction[$i];
        }

        if (!is_null($limit = $filter->getOrDefault("limit", NULL))) {
            $offset = $filter->getOrDefault("offset", "0");
            $query .= " LIMIT %s, %s";

            $values[] = $offset;
            $values[] = $limit;
        }

        $trans = $this->base->db->beginRO();

        $res = $trans->query($query, $values);
        $res->setClassFactory("\\models\\WikiPage");

        $parents = array();

        $out = array();
        foreach ($res as $row) {
            $out[] = $row;

            if (!is_null($row->parent_id)) {
                $parents[$row->parent_id] = true;
            }
        }

        $totalCount = $trans->query("SELECT FOUND_ROWS() AS `totalCount`")->fetch()->totalCount;

        // Load all parent pages for current result set.
        $parents = $this->loadAllParents($trans, $parents);
        foreach ($out as $page) {
            if (!is_null($page->getParentId())) {
                $page->setParent($parents[$page->getParentId()]);
            }
        }

        $trans->commit();

        $ret = new \stdClass;

        $ret->totalCount = $totalCount;
        $ret->pages = $out;

        return $ret;
    }
}
