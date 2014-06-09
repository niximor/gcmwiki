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

interface Storage {
    function getSessionStorage();
	function loadPage($path);
	function loadPageAcl(\models\WikiPage $page, \models\User $user);
	function loadDefaultAcl(\models\User $user);
	function storePage(\models\WikiPage $page);
	function getHistorySummary($pageId);

	function listPageAcl(\models\WikiPage $page);
	function listPageUsersAcl(\models\WikiPage $page);
	function listPageGroupsAcl(\models\WikiPage $page);
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
    function listDefaultPrivileges();
    function listUserPrivileges(\models\User $user);
    function listGroupPrivileges(\models\Group $group);

    function storeDefaultPrivileges($privs);
    function storeUserPrivileges($privs);
    function storeGroupPrivileges($group);

    function listSystemVariables();
    function setSystemVariables($variables);
}
