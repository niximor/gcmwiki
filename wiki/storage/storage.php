<?php

namespace storage;

require_once("models/WikiPage.php");
require_once("models/WikiAcl.php");

class Exception extends \Exception {

}

class PageNotFoundException extends Exception {
	protected $parent;

	function __construct($pageName, $parentPage = NULL, $parentExc = NULL) {
		parent::__construct($pageName, 0, $parentExc);
		$this->parent = $parentPage;
	}

	function getParentPage() {
		return $this->parent;
	}
}

class UserNotFoundException extends Exception {
    function __construct($userName, $parentExc = NULL) {
        parent::__construct($userName, 0, $parentExc);
    }
}

class FileNotFoundException extends Exception {

}

class Diagnostics extends Exception {
	protected $errors;

	function __construct() {
		parent::__construct("Some errors were found while processing your request");
	}

	function addError($field, $errorId, $errorMessage) {
		$obj = new \stdClass;
		$obj->field = $field;
		$obj->id = $errorId;
		$obj->message = $errorMessage;

		$this->errors[] = $obj;
	}

	function getErrors() {
		return $this->errors;
	}

	function getErrorsForFields() {
		$errors = array();

		foreach ($this->errors as $err) {
			if (!isset($errors[$err->field])) {
				$errors[$err->field] = array();
			}

			$errors[$err->field][] = $err;
		}

		return $errors;
	}
}

/**
 * Interface defining storage backend of wiki.
 */
interface Storage {
    /**
     * Return instance of session storage. Session storage is used by session backend to store session data.
     * Returned class must implements \lib\SessionStorage interface.
     * @return \lib\SessionStorage Instance of session storage class.
     */
    function getSessionStorage();

    /**
     * Load one page determined by it's path.
     * @param array(string) $path List of page identifiers that forms path to requested page. Page identifier is it's URL.
     * @param array(string) $requiredColumns List of columns that should be loaded. By default, columns loaded are:
     *   id, url, name, revision, last_modified, user_id, ip. If you need anything else, you must specify it in this
     *   parameter.
     * @param bool $followRedirect Set to true to automatically follow redirect of pages.
     * @return \models\WikiPage instance of loaded page.
     * @throws \storage\PageNotFoundException if given page was not found. Exception's message contains name of page
     *   that was not found. It does not need to be the actual page requested, but anything in the path before actual
     *   page, if that parent page does not exists.
     */
	function loadPage($path, $requiredColumns = NULL, $revision = NULL, $followRedirect = true);

    /**
     * List subpages of given page. Only direct subpages are listed.
     * @param \models\WikiPage $page Parent page where to start listing. Can be NULL to list all pages on root level.
     * @param array(string) $requiredColumns List of columns that should be loaded from database. By default, only page name,
     *   url and ID are returned.
     * @return array(̈́\models\WikiPage) List of childs of specified page.
     */
    function listSubpages(\models\WikiPage $page = NULL, $requiredColumns = NULL);

    /**
     * Load page's ACLs. Loaded ACLs are actual applied ACLs inherited by user's group membership, or user's default
     * ACLs for the whole system, if none of more specific privileges is specified.
     * @param \models\WikiPage $page Wiki page for which to load ACLs.
     * @param \models\User $user User for who to load ACLs.
     * @return \models\WikiAcl filled with ACLs for specified user and page.
     */
	function loadPageAcl(\models\WikiPage $page, \models\User $user);

    /**
     * Load default ACLs for given user.
     * @param \models\User $user User for which to load default ACLs.
     */
	function loadDefaultAcl(\models\User $user);

    /**
     * Stores page to the storage. If page does not exists, it will be created, if it already exists, it will be
     * updated.
     * @param \models\WikiPage $page WikiPage to store.
     */
	function storePage(\models\WikiPage $page);

    /**
     * Changes redirects from old page to new page. All pages redirecting to $oldPage gets redirected to
     * $newPage.
     * @param \models\WikiPage $oldPage Old page
     * @param \models\WikiPage $newPage New page
     */
    function updateRedirects(\models\WikiPage $oldPage, \models\WikiPage $newPage);

    /**
     * Gets overview of page history - list of revisions with revision comments.
     * @param \models\WikiPage $page WikiPage to get history.
     */
	function getHistorySummary(\models\WikiPage $page);

    /**
     * List pages referenced by given page.
     * @param \models\WikiPage $page Page to use when listing references.
     */
    function getReferencedPages(\models\WikiPage $page);

	function listPageAcl(\models\WikiPage $page);
	function listPageUsersAcl(\models\WikiPage $page);
	function listPageGroupsAcl(\models\WikiPage $page);

    /**
     * List begining letters of pages with number of pages belonging to given letter.
     * @return array $struct
     *     - string $letter Begining letter of page name.
     *     - int $numOfPages Number of pages belonging to given letter.
     */
    function listPagesLetters();

    /**
     * List pages with given filter conditions.
     * @param $filter Filter.
     *     - string $letter First letter (A-Z or # for anything that is not A-Z).
     *     - int $limit Number of pages.
     *     - int $offset Offset of paging.
     */
    function listPages(\lib\XObject $filter);

	function storePageAcl(\models\WikiPage $page, \models\WikiAclSet $set);

    function verifyUser($username, $password);
    function loadUserInfo($id, $matchColumn = "id", $columns = array());
    function storeUserInfo(\models\User $user);
    function loadUserPrivileges(\models\User $user);

    function loadGroupInfo($groupId);
    function storeGroupInfo(\models\Group $group);
    function addUserToGroup(\models\User $user, \models\Group $group);
    function removeUserFromGroup(\models\User $user, \models\Group $group);
    function removeGroup(\models\Group $group);

    function listUsers(\models\Group $inGroup = NULL, $additionalColumns = array());
    function listGroups(\models\User $ofUser = NULL, $additionalColumns = array());
    function listUsersOfPrivilege(\models\SystemPrivilege $privilege);

    function listDefaultPrivileges();
    function listUserPrivileges(\models\User $user);
    function listGroupPrivileges(\models\Group $group);

    function storeDefaultPrivileges($privs);
    function storeUserPrivileges($privs);
    function storeGroupPrivileges($group);

    function listSystemVariables();
    function setSystemVariables($variables);

    function storeComment(\models\Comment $comment);
    function loadComments(\models\WikiPage $page);
    function loadComment($commentId, $withHistory = false);
    function getReferencedComments(\models\WikiPage $page);

    function invalidateWikiCache($key);
    function storeWikiCache($key, $text);

    /**
     * Returns implementation of Attachments module.
     * @return Instance of Attachments interface.
     */
    function getAttachmentsModule();
}

/**
 * Module implementing work with attachments.
 */
interface Attachments {
    /**
     * Load given attachment information (not the data itself).
     * @param name Attachment name
     * @param features List of features to load
     * @param revision Load older revision. If NULL, loads most recent revision.
     */
    function load(\lib\XObject $filter, $features = NULL);

    /**
     * Store attachment meta information (not the data itself).
     */
    function store(\models\Attachment $attachment);
}

/**
 * Module implementing access to filesystem for storing attachment data.
 */
interface DataStore {
    const ORIGINAL_FILE = "original";

    /**
     * Returns handle to file representing given $attachment with given $subId.
     * @param \models\Attachment $attachment Attachment to be loaded.
     * @param string $subId Required Sub ID of given attachment.
     */
    function load(\models\Attachment $attachment, $subId);

    /**
     * Store data, pointed by $f, as $attachment with given $subId. If entry in storage for given combination
     * of attachment and subId already exists, it is overwritten without asking or even notifying in any way.
     * @param resource $fileHandle Handle to file opened by fopen() or similar, that can be read.
     * @param \models\Attachment $attachment Attachment for which the content should be saved.
     * @param string $subId Sub ID which should be used to store this file.
     */
    function store($uploadedFileName, \models\Attachment $attachment, $subId);
}

