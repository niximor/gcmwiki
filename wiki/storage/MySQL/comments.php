<?php

namespace storage\MySQL;

require_once "storage/MySQL/base.php";

class Comments extends Module {
    public function storeComment(\models\Comment $comment) {
        $trans = $this->base->db->beginRW();

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
        if ($comment->text_wiki == "") {
            $diag->addError("text_wiki", "text_wiki_cannot_be_empty", "Comment text cannot be empty.");
        }

        if ($diag->getErrors()) {
            $trans->rollback();
            throw $diag;
        }

        if (!is_null($comment->getId())) {
            $trans->query("INSERT INTO comments_history (comment_id, revision, last_modified, user_id,      ip, text_wiki)
                            SELECT                       id,         revision, last_modified, edit_user_id, ip, text_wiki)
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
                anonymous_name, ip, parent_id, created, last_modified, text_wiki) VALUES
                (%s, %s, %s, %s, %s, %s, %s, NOW(), NOW(), %s)",
                $comment->page_id, $comment->revision, $comment->owner_user_id, $comment->edit_user_id,
                $comment->anonymous_name, \lib\Session::IP(), $comment->parent_id, $comment->text_wiki);
            $comment->setId($trans->lastInsertId());
        }

        $trans->commit();
    }

    protected function formatCommentText(\models\Comment $comment) {
        $f = new \lib\formatter\WikiFormatterSimple();
        $comment->text_html = $f->format($comment->text_wiki, $comment->getPage());
        $this->base->cache->storeWikiCache("comment-".$comment->id."-".$comment->revision, $comment->text_html);

        // Store links
        $root = $f->getRootContext();
        if (isset($root->WIKI_LINKS) && is_array($root->WIKI_LINKS)) {
            $query = "INSERT INTO comments_references (comment_id, ref_page_id, ref_page_name) VALUES ";
            $ins = array();
            $vals = array();

            foreach ($root->WIKI_LINKS as $link) {
                $ins[] = "(%s, %s, %s)";
                $vals[] = $comment->getId();
                if (is_int($link)) {
                    $vals[] = $link;
                    $vals[] = NULL;
                } else {
                    $vals[] = NULL;
                    $vals[] = implode("/", $link);
                }
            }

            if (!empty($ins)) {
                $trans = $this->base->db->beginRW();
                $query .= implode(", ", $ins);
                $trans->query($query, $vals);
                $trans->commit();
            }
        }
    }

    public function loadComments(\models\WikiPage $page) {
        $trans = $this->base->db->beginRO();

        $res = $trans->query("SELECT c.id, c.revision, c.owner_user_id, c.edit_user_id, c.anonymous_name,
            c.ip, c.parent_id, c.created, c.last_modified, c.text_wiki, cache.wiki_text AS text_html,
            uo.name AS owner_user_name, ue.name AS edit_user_name
            FROM comments c
            LEFT JOIN users uo ON (c.owner_user_id = uo.id)
            LEFT JOIN users ue ON (c.edit_user_id = ue.id)
            LEFT JOIN wiki_text_cache cache ON (cache.key = CONCAT('comment-', c.id, '-', c.revision) AND cache.valid = 1)
            WHERE page_id = %s", $page->getId());

        // Build comments hierarchy
        $comments = array();
        $comments_by_id = array();

        foreach ($res as $row) {
            if (!isset($comments[$row->parent_id])) {
                $comments[$row->parent_id] = array();
            }

            $comment = new \models\Comment;
            $comment->setPage($page);
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
            if (is_null($row->text_html)) {
                $comment->text_wiki = $row->text_wiki;
                $this->formatCommentText($comment);
            } else {
                $comment->text_html = $row->text_html;
            }
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

    public function loadComment($commentId) {
        $trans = $this->base->db->beginRO();

        $res = $trans->query("SELECT c.id, c.page_id, c.revision, c.owner_user_id, c.edit_user_id, c.anonymous_name,
            c.ip, c.created, c.last_modified, c.text_wiki, cache.wiki_text AS text_html,
            uo.name AS owner_user_name, ue.name AS edit_user_name
            FROM comments c
            LEFT JOIN users uo ON (c.owner_user_id = uo.id)
            LEFT JOIN users ue ON (c.edit_user_id = ue.id)
            LEFT JOIN wiki_text_cache cache ON (cache.key = CONCAT('comment-', c.id, '-', c.revision) AND cache.valid = 1)
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
        if (is_null($row->text_html)) {
            $comment->text_wiki = $row->text_wiki;
            $this->formatCommentText($comment);
        } else {
            $comment->text_html = $row->text_html;
        }

        $comment->clearChanged();

        $trans->commit();

        return $comment;
    }

    public function getReferencedComments(\models\WikiPage $page) {
        $trans = $this->base->db->beginRW();
        $q = $trans->query("SELECT comment_id FROM comments_references WHERE ref_page_id = %s OR ref_page_name = %s",
            $page->getId(), $page->getUrl());
        $out = array();
        foreach ($q as $row) {
            $out[] = $row;
        }
        $trans->commit();

        return $out;
    }
}
