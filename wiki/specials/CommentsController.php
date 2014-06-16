<?php

namespace specials;

require_once "view/exceptions.php";
require_once "view/Template.php";
require_once "models/Comment.php";

class CommentsController extends SpecialController {
	function add() {
		if (is_null($this->relatedPage)) {
			throw new \view\NotFound();
		}

		$be = $this->getBackend();
		$this->Acl = $be->loadPageAcl($this->relatedPage, \lib\CurrentUser::i());

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
				$item->owner_user_id = $item->edit_user_id = \lib\CurrentUser::ID();
			} else {
				$item->owner_user_id = $item->edit_user_id = NULL;
				if (isset($_POST["username"]) && !empty($_POST["username"])) {
					$item->anonymous_name = $_POST["username"];
				}
			}

			$item->page_id = $this->relatedPage->id;
			$item->revision = $this->relatedPage->revision;

			if (isset($comment)) {
				$item->parent_id = $comment->getId();
			}

			$item->updateText($_POST["text"]);

			try {
				$be->storeComment($item);
			} catch (\storage\Diagnostics $e) {
				\lib\Session::Set("Form", $_POST);
				\lib\Session::Set("Errors", $e->getErrorsForFields());
			}

			$this->template->redirect($this->template->getSelf());
		}
	
		$child->addVariable("Page", $this->relatedPage);
		$child->addVariable("Acl", $acl);
		$child->addVariable("Form", (array)\lib\Session::Get("Form"));
		$child->addVariable("Errors", (array)\lib\Session::Get("Errors"));
		$this->template->setChild($child);

		\lib\Session::Set("Form", NULL);
		\lib\Session::Set("Errors", NULL);

		$this->addPageLinks();
		$this->template->addNavigation("Add comment", $this->template->getSelf());
		$this->addPageActions();
	}
}

\Config::registerSpecial("comment", "\\specials\\CommentsController");
