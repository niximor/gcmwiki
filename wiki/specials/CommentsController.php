<?php

namespace specials;

require_once "view/exceptions.php";
require_once "view/Template.php";
require_once "models/Comment.php";

class CommentsController extends SpecialController {
	function add() {
		$this->ensurePageContext();

		$be = $this->getBackend();

		if (!$this->Acl->comment_write) {
			throw new \view\AccessDenided();
		}

		$child = new \view\Template("comments/add.php");

		// Parent comment must be for the same page.
		if (isset($_REQUEST["parent"])) {
			try {
				$comment = $be->loadComment($_REQUEST["parent"]);
				if ($comment->getPage_id() != $this->relatedPage->getId()) {
					throw new \view\NotFound();
				}
				$child->addVariable("Comment", $comment);
			} catch (\drivers\EntryNotFoundException $e) {
				throw new \view\NotFound();
			}
		}

		if ($_SERVER["REQUEST_METHOD"] == "POST") {
			$item = new \models\Comment;
			if (\lib\CurrentUser::isLoggedIn()) {
				$item->owner_user_id = \lib\CurrentUser::ID();
			} else {
				$item->owner_user_id = NULL;
				if (isset($_POST["username"]) && !empty($_POST["username"])) {
					$item->anonymous_name = $_POST["username"];
				}
			}

			$item->page_id = $this->relatedPage->id;
			$item->revision = 1;
			$item->approved = 1; // TODO: This must be user-dependant

			if (isset($comment)) {
				$item->parent_id = $comment->getId();
			}

			$item->updateText($_POST["text"]);

			try {
				$be->storeComment($item);
				\view\Messages::Add("Comment has been added.", \view\Message::Success);
				$this->template->redirect("/".$this->relatedPage->getFullUrl());
			} catch (\storage\Diagnostics $e) {
				\lib\Session::Set("Form", $_POST);
				\lib\Session::Set("Errors", $e->getErrorsForFields());
				$this->template->redirect($this->template->getSelf());
			}
		}

		$child->addVariable("Page", $this->relatedPage);
		$child->addVariable("Acl", $this->Acl);
		$child->addVariable("Form", (array)\lib\Session::Get("Form"));
		$child->addVariable("Errors", (array)\lib\Session::Get("Errors"));
		$this->template->setChild($child);

		\lib\Session::Set("Form", NULL);
		\lib\Session::Set("Errors", NULL);

		$this->template->addNavigation("Add comment", $this->template->getSelf());
	}

	function edit() {
		$this->ensurePageContext();

		$be = $this->getBackend();

		// Comment id is mandatory.
		if (!isset($_REQUEST["id"])) {
			throw new \view\NotFound();
		}

		// Load comment.
		$comment = $be->loadComment($_REQUEST["id"]);

		// The comment must be for page that is currently opened.
		if ($comment->getPage_id() != $this->relatedPage->getId()) {
			throw new \view\NotFound();
		}

		// If user does not have privilege to edit comment, refuse it.
		if (!($this->Acl->comment_admin || ($this->Acl->comment_write && \lib\CurrentUser::isLoggedIn() && $comment->OwnerUser->getId() == \lib\CurrentUser::ID()))) {
			throw new \view\AccessDenided();
		}

		if ($_SERVER["REQUEST_METHOD"] == "POST") {
			$comment->updateText($_POST["text"]);
			$comment->edit_user_id = \lib\CurrentUser::ID();
			$comment->revision++;

			try {
				$be->storeComment($comment);
				\view\Messages::Add("Comment has been edited.", \view\Message::Success);
				$this->template->redirect("/".$this->relatedPage->getFullUrl());
			} catch (\storage\Diagnostics $e) {
				\lib\Session::Set("Form", $_POST);
				\lib\Session::Set("Errors", $e->getErrorsForFields());
				$this->template->redirect($this->template->getSelf()."?id=".$comment->getId());
			}
		}

		$child = new \view\Template("comments/edit.php");
		$child->addVariable("Comment", $comment);
		$child->addVariable("Form", (array)\lib\Session::Get("Form"));
		$child->addVariable("Errors", (array)\lib\Session::Get("Errors"));

		$this->template->setChild($child);
		$this->template->addNavigation("Edit comment", $this->template->getSelf());
	}

	function hide() {
		$this->ensurePageContext();

		$be = $this->getBackend();

		if (!isset($_REQUEST["id"])) {
			throw new \view\NotFound();
		}

		$comment = $be->loadComment($_REQUEST["id"]);

		if ($comment->getPage_id() != $this->relatedPage->getId()) {
			throw new \view\NotFound();
		}

		if (!($this->Acl->comment_admin || ($this->Acl->comment_write && \lib\CurrentUser::isLoggedIn() && $comment->OwnerUser->getId() == \lib\CurrentUser::ID()))) {
			throw new \view\AccessDenided();
		}

		$comment->hidden = true;

		$be->storeComment($comment);
		\view\Messages::Add("Comment has been hidden.", \view\Message::Success);

		$this->template->redirect("/".$this->relatedPage->getFullUrl());
	}

	function history() {
		$this->ensurePageContext();

		$be = $this->getBackend();

		if (!isset($_REQUEST["id"])) {
			throw new \view\NotFound();
		}

		$comment = $be->loadComment($_REQUEST["id"], true);

		if ($comment->getPage_id() != $this->relatedPage->getId()) {
			throw new \view\NotFound();
		}

		if (!$this->Acl->comment_read) {
			throw new \view\AccessDenided();
		}

		$child = new \view\Template("comments/history.php");
		$child->addVariable("Comment", $comment);

		$this->template->setChild($child);
		$this->template->addNavigation("Comment history", $this->template->getSelf()."?id=".$comment->getId());
	}
}

\Config::registerSpecial("comment", "\\specials\\CommentsController");
