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
            $query = "INSERT INTO wiki_page_references (wiki_page_id, ref_page_id, ref_page_name) VALUES ";
            $ins = array();
            $vals = array();

            foreach ($root->WIKI_LINKS as $link) {
                $ins[] = "(%s, %s, %s)";
                $vals[] = $page->getId();
                if (is_int($link)) {
                    $vals[] = $link;
                    $vals[] = NULL;
                } else {
                    $vals[] = NULL;
                    $vals[] = implode("/", $link);
                }
            }

            if (!empty($ins)) {
                $transactionStarted = false;
                if ($this->base->currentTransaction) {
                    $trans = $this->base->currentTransaction;
                } else {
                    $trans = $this->base->db->beginRW();
                    $transactionStarted = true;
                }
                $query .= implode(", ", $ins);
                $trans->query($query, $vals);

                if ($transactionStarted) {
                    $trans->commit();
                }
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
        for ($i = 0; $i < $part_len; ++$i) {
            $part = $path[$i];

            $join = array();

            if ($i == $part_len - 1) {
                $columns = array_merge(array("id", "url", "name", "revision", "last_modified", "user_id", "ip"), $requiredColumns);
                $lastPart = true;
            } else {
                $columns = array("id", "name", "url", "user_id", "ip");
            }
            $columns[] = "redirect_to";

            $loadRenderedBody = false;
            if (($key = array_search('body_html', $columns)) !== false) {
                unset($columns[$key]);
                $loadRenderedBody = true;
                $columns[] = "body_wiki";
            }

            array_walk($columns, function(&$a) { $a = "p.".$a; });

            $columns[] = "u.name AS user_name";

            if (!is_null($revision) && $lastPart) {
                // TODO: Display that we are showing old version of page.
                // TODO: Support for current revision as parameter (permalink).
                if (($key = array_search("p.id", $columns)) !== false) {
                    $columns[$key] = "p.page_id AS id";
                }
                $table = "wiki_pages_history";
                $where = " AND revision = ".$revision;
                $idColumn = "p.page_id";
            } else {
                $table = "wiki_pages";
                $where = "";
                $idColumn = "p.id";
            }

            $join[] = "LEFT JOIN users u ON (p.user_id = u.id)";

            if ($loadRenderedBody) {
                $columns[] = "cache.wiki_text AS body_html";
                $join[] = "LEFT JOIN wiki_text_cache cache ON (cache.key = CONCAT('wiki-page-', p.id, '-', p.revision) AND cache.valid = 1)";
            }

            if ($followRedirect) {
                if (is_null($parent)) {
                    $query = "SELECT ".implode(",", $columns)." FROM ".$table." p
                        ".implode(" ", $join)."
                        WHERE ".$idColumn." = GET_PAGE_ID(%s, NULL)".$where;
                    $res = $trans->query($query, $part);
                } else {
                    $query = "SELECT ".implode(",", $columns)." FROM ".$table." p
                        ".implode(" ", $join)."
                        WHERE ".$idColumn." = GET_PAGE_ID(%s, %s)".$where;
                    $res = $trans->query($query, $part, $parent->id);
                }
            } else {
                if (is_null($parent)) {
                    $query = "SELECT ".implode(",", $columns)." FROM ".$table." p
                        ".implode(" ", $join)."
                        WHERE p.url = %s AND p.parent_id IS NULL".$where;
                    $res = $trans->query($query, $part);
                } else {
                    $query = "SELECT ".implode(",", $columns)." FROM ".$table." p
                        ".implode(" ", $join)."
                        WHERE p.url = %s AND p.parent_id = %s".$where;
                    $res = $trans->query($query, $part, $parent->id);
                }
            }

            try {
                $row = $res->fetch();

                $page = new \models\WikiPage;
                $page->setParent($parent);

                if (isset($row->id)) $page->id = $row->id;
                if (isset($row->name)) $page->name = $row->name;
                if (isset($row->url)) $page->url = $row->url;
                if (isset($row->created)) $page->created = $row->created;
                if (isset($row->last_modified)) $page->last_modified = $row->last_modified;
                if (isset($row->user_id)) $page->user_id = $row->user_id;
                if (isset($row->revision)) $page->revision = $row->revision;
                if (isset($row->body_wiki)) $page->body_wiki = $row->body_wiki;
                if ($loadRenderedBody) {
                    if (is_null($row->body_html)) {
                        $this->base->currentTransaction = $trans;
                        $this->formatPageText($page);
                        $this->base->currentTransaction = NULL;
                    } else {
                        $page->body_html = $row->body_html;
                    }
                }
                if (isset($row->summary)) $page->summary = $row->summary;
                if (isset($row->small_change)) $page->small_change = $row->small_change;
                if (isset($row->redirect_to)) $page->redirect_to = $row->redirect_to;

                if (strtolower($row->url) != strtolower($part)) {
                    $this->base->currentTransaction = $trans;
                    $page->redirected_from = $this->loadPage($path, NULL, NULL, false);
                    $this->base->currentTransaction = NULL;
                }

                if ($page->user_id > 0) {
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
                throw new PageNotFoundException($part, $parent, $e);
            }
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
            if (in_array($column, array("name", "url", "body_wiki", "user_id", "small_change", "summary", "redirect_to"))) {
                $columns[] = $column." = %s";
                $values[] = $page->$column;

                if ($column == "body_wiki" || $column == "url" || $column == "name") {
                    $incRevision = true;
                }

                if ($column == "url") $urlChanged = true;
            }
        }

        if (!empty($columns)) {
            if ($incRevision) {
                $trans->query("INSERT INTO wiki_pages_history (page_id, name, url, body_wiki, user_id, small_change, summary, ip, last_modified, revision, redirect_to)
                                SELECT                         id,      name, url, body_wiki, user_id, small_change, summary, ip, last_modified, revision, redirect_to
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

        $trans->query("INSERT INTO wiki_pages (name, parent_id, url, created, last_modified, user_id, body_wiki, small_change, summary, ip)
                              VALUES          (%s,   %s,        %s,  NOW(),   NOW(),         %s,      %s,        %s,           %s,      %s)",
                $page->getName(), $parentId, $page->getUrl(), \lib\CurrentUser::ID(), $page->getBody_wiki(),
                $page->getSmall_change(), $page->getSummary(), \lib\Session::IP());
        $page->setId($trans->lastInsertId());
        $page->setRevision(1);

        if ($transactionStarted) {
            $trans->commit();
        }

        $page->clearChanged();
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

        // Admin has anything, do not need to bother the database.
        if ($user->hasPriv("admin_pages")) {
            $acl->page_read = true;
            $acl->page_write = true;
            $acl->page_admin = true;
            $acl->comment_read = true;
            $acl->comment_write = true;

            return $acl;
        }

        $transactionStarted = false;
        if (is_null($trans)) {
            $trans = $this->base->db->beginRO();
            $transactionStarted = true;
        }

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
                $to_merge = $this->loadPageAcl($wikiPage->getParent(), $user, $trans);
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

        if ($transactionStarted) {
            $trans->commit();
        }

        return $acl;
    }

    public function loadDefaultAcl(\models\User $user, $trans = NULL) {
        $acl = new \models\WikiAcl;
        $acl->page_read = $user->hasPriv("acl_page_read");
        $acl->page_write = $user->hasPriv("acl_page_write");
        $acl->page_admin = $user->hasPriv("acl_page_admin");
        $acl->comment_read = $user->hasPriv("acl_comment_read");
        $acl->comment_write = $user->hasPriv("acl_comment_write");
        return $acl;
    }

    public function listPageAcl(\models\WikiPage $page) {
        $trans = $this->base->db->beginRO();

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
            $trans = $this->base->db->beginRO();
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
            $trans = $this->base->db->beginRO();
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
        $trans = $this->base->db->beginRO();

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

            if (!empty($sets)) {
                $trans->query("INSERT INTO page_acl_user (page_id, user_id, page_read, page_write, page_admin, comment_read, comment_write)
                    VALUES ".implode(",", $sets)."
                    ON DUPLICATE KEY UPDATE
                        page_read = VALUES(page_read),
                        page_write = VALUES(page_write),
                        page_admin = VALUES(page_admin),
                        comment_read = VALUES(comment_read),
                        comment_write = VALUES(comment_write)", $values);
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

            if (!empty($sets)) {
                $trans->query("INSERT INTO page_acl_group (page_id, group_id, page_read, page_write, page_admin, comment_read, comment_write)
                    VALUES ".implode(",", $sets)."
                    ON DUPLICATE KEY UPDATE
                        page_read = VALUES(page_read),
                        page_write = VALUES(page_write),
                        page_admin = VALUES(page_admin),
                        comment_read = VALUES(comment_read),
                        comment_write = VALUES(comment_write)", $values);
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
}
