<?php

namespace storage;

require_once "storage/storage.php";

require_once "models/WikiPage.php";
require_once "models/WikiHistoryEntry.php";
require_once "models/User.php";
require_once "models/Group.php";
require_once "models/SystemPrivilege.php";
require_once "models/SystemVariable.php";

require_once "storage/MySQL/base.php";

class MySQL implements Storage {
    protected $base;
    protected $page;

    public function __construct(MySQL\StorageBase $base = NULL) {
        if (!is_null($base)) {
            $this->base = $base;
        } else {
            $this->base = new MySQL\StorageBase();
        }
    }

    public function getSessionStorage() {
        return $this->base->getSessions();
    }

    public function getAttachmentsModule() {
        return $this->base->attachments;
    }

    public function loadPage($path, $requiredColumns = NULL, $revision = NULL, $followRedirect = true) {
        return $this->base->pages->loadPage($path, $requiredColumns, $revision, $followRedirect);
    }

    public function listSubpages(\models\WikiPage $page = NULL, $requiredColumns = NULL) {
        return $this->base->pages->listSubpages($page, $requiredColumns);
    }

    public function storePage(\models\WikiPage $page) {
        return $this->base->pages->storePage($page);
    }

    public function updateRedirects(\models\WikiPage $oldPage, \models\WikiPage $newPage) {
        return $this->base->pages->updateRedirects($oldPage, $newPage);
    }

    public function getReferencedPages(\models\WikiPage $page) {
        return $this->base->pages->getReferencedPages($page);
    }

    public function getHistorySummary(\models\WikiPage $page) {
        return $this->base->pages->getHistorySummary($page);
    }

    public function loadPageAcl(\models\WikiPage $wikiPage, \models\User $user, $trans = NULL) {
        return $this->base->pages->loadPageAcl($wikiPage, $user);
    }

    public function loadDefaultAcl(\models\User $user) {
        return $this->base->pages->loadDefaultAcl($user);
    }

    public function listPageAcl(\models\WikiPage $page) {
        return $this->base->pages->listPageAcl($page);
    }

    public function listPageUsersAcl(\models\WikiPage $page) {
        return $this->base->pages->listPageUsersAcl($page);
    }

    public function listPageGroupsAcl(\models\WikiPage $page) {
        return $this->base->pages->listPageGroupsAcl($page);
    }

    public function storePageAcl(\models\WikiPage $page, \models\WikiAclSet $set) {
        return $this->base->pages->storePageAcl($page, $set);
    }

    public function loadUserInfo($id, $matchColumn = "id", $columns = array()) {
        return $this->base->users->loadUserInfo($id, $matchColumn, $columns);
    }

    public function verifyUser($username, $password) {
        return $this->base->users->verifyUser($username, $password);
    }

    public function storeUserInfo(\models\User $user) {
        return $this->base->users->storeUserInfo($user);
    }

    public function listUsers(\models\Group $inGroup = NULL, $additionalColumns = array()) {
        return $this->base->users->listUsers($inGroup, $additionalColumns);
    }

    public function listGroups(\models\User $ofUser = NULL, $additionalColumns = array()) {
        return $this->base->users->listGroups($ofUser, $additionalColumns);
    }

    public function loadUserPrivileges(\models\User $user) {
        return $this->base->users->loadUserPrivileges($user);
    }

    public function listDefaultPrivileges() {
        return $this->base->users->listDefaultPrivileges();
    }

    public function listUserPrivileges(\models\User $user) {
        return $this->base->users->listUserPrivileges($user);
    }

    public function listGroupPrivileges(\models\Group $group) {
        return $this->base->users->listGroupPrivileges($group);
    }

    public function storeDefaultPrivileges($privs) {
        return $this->base->users->storeDefaultPrivileges($privs);
    }

    public function storeUserPrivileges($privs) {
        return $this->base->users->storeUserPrivileges($privs);
    }

    public function storeGroupPrivileges($privs) {
        return $this->base->users->storeGroupPrivileges($privs);
    }

    public function loadGroupInfo($groupId) {
        return $this->base->users->loadGroupInfo($groupId);
    }

    public function storeGroupInfo(\models\Group $group) {
        return $this->base->users->storeGroupInfo($group);
    }

    public function addUserToGroup(\models\User $user, \models\Group $group) {
        return $this->base->users->addUserToGroup($user, $group);
    }

    public function removeUserFromGroup(\models\User $user, \models\Group $group) {
        return $this->base->users->removeUserFromGroup($user, $group);
    }

    public function removeGroup(\models\Group $group) {
        return $this->base->users->removeGroup($group);
    }

    public function listUsersOfPrivilege(\models\SystemPrivilege $privilege) {
        return $this->base->users->listUsersOfPrivilege($privilege);
    }

    public function listSystemVariables() {
        return $this->base->system->listSystemVariables();
    }

    public function setSystemVariables($variables) {
        return $this->base->system->setSystemVariables($variables);
    }

    public function storeComment(\models\Comment $comment) {
        return $this->base->comments->storeComment($comment);
    }

    public function loadComments(\models\WikiPage $page) {
        return $this->base->comments->loadComments($page);
    }

    public function loadComment($commentId) {
        return $this->base->comments->loadComment($commentId);
    }

    public function getReferencedComments(\models\WikiPage $page) {
        return $this->base->comments->getReferencedComments($page);
    }

    public function invalidateWikiCache($key) {
        return $this->base->cache->invalidateWikiCache($key);
    }

    public function storeWikiCache($key, $text) {
        return $this->base->cache->storeWikiCache($key, $text);
    }
}

