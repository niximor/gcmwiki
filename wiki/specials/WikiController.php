<?php

namespace specials;

/* This is not in fact a special controller, because it is called directly when no other controller is specified.
 * But it uses the same logic as special controllers, only without registration as a controller, so it is there.
 */

require_once "lib/diff.php";
require_once "specials/SpecialController.php";

class WikiController extends SpecialController {
	protected $pageName;

	function __construct(\view\Template $page, $path) {
		if (count($path) == 0) {
			$path = array(\Config::Get("DefaultPage"));
			$page->redirect("/".implode("/", $path));
		}

		parent::__construct($page);
	}

	function page($path) {
		try {
			$be = $this->getBackend();

			if (isset($_GET["revision"])) {
				$wikiPage = $be->loadPage($path, array("body_html"), $_GET["revision"]);
			} else {
				$wikiPage = $be->loadPage($path, array("body_html"));
			}

			$acl = $be->loadPageAcl($wikiPage, \lib\CurrentUser::i());
			
			if ($acl->page_read) {
				$child = new \view\Template("page/index.php");
				$child->addVariable("Page", $wikiPage);
				$child->addVariable("Acl", $acl);

				$this->template->setChild($child);
			} else {
				$child = new \view\Template("page/no_access_read.php");
				$child->addVariable("Acl", $acl);

				$this->template->setChild($child);
			}
		} catch (\storage\PageNotFoundException $e) {
			$child = new \view\Template("page/notfound.php");
			$child->addVariable("PageName", $e->getMessage());

			$parent = $e->getParentPage();
			if (!is_null($parent)) {
				$acl = $be->loadPageAcl($parent, \lib\CurrentUser::i());
			} else {
				$acl = $be->loadDefaultAcl(\lib\CurrentUser::i());
			}

			$child->addVariable("Parent", $e->getParentPage());
			$child->addVariable("Acl", $acl);

			$this->template->setChild($child);
		}
	}

	function edit($path) {
		$be = $this->getBackend();

		try {
			$wikiPage = $be->loadPage($path, array("body_wiki"));
			$acl = $be->loadPageAcl($wikiPage, \lib\CurrentUser::i());
		} catch (\storage\PageNotFoundException $e) {
			$parent = $e->getParentPage();
			if (!is_null($parent)) {
				$acl = $be->loadPageAcl($parent, \lib\CurrentUser::i());
			} else {
				$acl = $be->loadDefaultAcl(\lib\CurrentUser::i());
			}

			$wikiPage = new \models\WikiPage();
			$wikiPage->setUrl($e->getMessage());
			$wikiPage->setName($e->getMessage());
		}

		if ($acl->page_write) {
			$child = new \view\Template("page/edit.php");
			$child->addVariable("Page", $wikiPage);
			$child->addVariable("Errors", (array)\lib\Session::Get("Errors"));
			$child->addVariable("Form", (array)\lib\Session::Get("Form"));

			\lib\Session::Set("Errors", NULL);
			\lib\Session::Set("Form", NULL);

			$this->template->setChild($child);
		} else {
			$child = new \view\Template("page/no_access_write.php");
			$child->addVariable("Page", $wikiPage);
			$child->addVariable("Acl", $acl);

			$this->template->setChild($child);
		}
	}

	function save($path) {
		$be = $this->getBackend();

		try {
			$wikiPage = $be->loadPage($path);
			$acl = $be->loadPageAcl($wikiPage, \lib\CurrentUser::i());
		} catch (\storage\PageNotFoundException $e) {
			$parent = $e->getParentPage();
			if (!is_null($parent)) {
				$acl = $be->loadPageAcl($e->getParentPage(), \lib\CurrentUser::i());
			} else {
				$acl = $be->loadDefaultAcl();
			}

			$wikiPage = new \models\WikiPage();
			$wikiPage->setUrl($e->getMessage());
			$wikiPage->setName($e->getMessage());
		}

		if (!$acl->page_write) {
			$this->template->redirect($this->template->getSelf()."?edit");
			return;
		}

		if (isset($_POST["body"])) {
			$wikiPage->updateBody($_POST["body"]);
			$wikiPage->setName($_POST["name"]);

			$wikiPage->setSmall_change((isset($_POST["small_change"]))?true:false);
			$wikiPage->setSummary($_POST["summary"]);
		}

		try {
			$be->storePage($wikiPage);
			$this->template->redirect($this->template->getSelf());
		} catch (\storage\Diagnostics $e) {
			\lib\Session::Set("Errors", $e->getErrorsForFields(), false);
			\lib\Session::Set("Form", $_POST);
			$this->template->redirect($this->template->getSelf()."?edit");
		}
	}

	function history($path) {
		$be = $this->getBackend();

		try {
			$wikiPage = $be->loadPage($path);
			$acl = $be->loadPageAcl($wikiPage, \lib\CurrentUser::i());

			if ($acl->page_read) {
				$summary = $be->getHistorySummary($wikiPage->getId());

				$child = new \view\Template("page/history.php");

				$child->addVariable("History", $summary);
				$child->addVariable("Page", $wikiPage);

				$this->template->setChild($child);
			} else {
				$child = new \view\Template("page/no_access_read.php");
				$child->addVariable("Acl", $acl);

				$this->template->setChild($child);
			}
		} catch (\storage\PageNotFoundException $e) {
			$child = new \view\Template("page/notfound.php");
			$child->addVariable("PageName", $e->getMessage());

			$parent = $e->getParentPage();
			if (!is_null($parent)) {
				$acl = $be->loadPageAcl($parent, \lib\CurrentUser::i());
			} else {
				$acl = $be->loadDefaultAcl(\lib\CurrentUser::i());
			}

			$child->addVariable("Parent", $e->getParentPage());
			$child->addVariable("Acl", $acl);

			$this->template->setChild($child);
		}
	}

	function diff($path) {
		$be = $this->getBackend();

		$rev1 = $_GET["a"];
		$rev2 = $_GET["b"];

		// Rev2 should be always older.
		if ($rev2 > $rev1) {
			$x = $rev1;
			$rev1 = $rev2;
			$rev2 = $x;
		}

		try {
			// Load revisions from DB
			$wikiPage = $be->loadPage($path, array("body_wiki", "revision"));
			$acl = $be->loadPageAcl($wikiPage, \lib\CurrentUser::i());

			if ($acl->page_read) {
				if ($wikiPage->getRevision() != $rev1) {
					$rev1 = $be->loadPage($path, array("body_wiki", "revision"), $rev1);
				} else {
					$rev1 = $wikiPage;
				}
				if ($wikiPage->getRevision() != $rev2) {
					$rev2 = $be->loadPage($path, array("body_wiki", "revision"), $rev2);
				} else {
					$rev2 = $wikiPage;
				}

				$child = new \view\Template("page/diff.php");

				$diff = new \lib\Diff();
				$computed = $diff->compute($rev2->body_wiki, $rev1->body_wiki);

				$child->addVariable("Page", $wikiPage);
				$child->addVariable("Revision1", $rev1);
				$child->addVariable("Revision2", $rev2);
				$child->addVariable("Diff", $computed);

				$this->template->setChild($child);
			} else {
				$child = new \view\Template("page/no_access_read.php");
				$child->addVariable("Acl", $acl);

				$this->template->setChild($child);
			}
		} catch (\storage\PageNotFoundException $e) {
			$child = new \view\Template("page/notfound.php");
			$child->addVariable("PageName", $e->getMessage());

			$parent = $e->getParentPage();
			if (!is_null($parent)) {
				$acl = $be->loadPageAcl($parent, \lib\CurrentUser::i());
			} else {
				$acl = $be->loadDefaultAcl(\lib\CurrentUser::i());
			}

			$child->addVariable("Parent", $e->getParentPage());
			$child->addVariable("Acl", $acl);

			$this->template->setChild($child);
		}
	}

	function acl($path) {
		$be = $this->getBackend();

		try {
			$wikiPage = $be->loadPage($path);
			$acl = $be->loadPageAcl($wikiPage, \lib\CurrentUser::i());

			if ($acl->page_admin) {
				$child = new \view\Template("page/acl.php");
				$child->addVariable("Page", $wikiPage);
				$child->addVariable("Acl", $acl);

				$child->addVariable("PageAcls", $be->listPageAcl($wikiPage));
				$child->addVariable("Users", $be->listUsers());
				$child->addVariable("Groups", $be->listGroups());

				$this->template->setChild($child);
			} else {
				$child = new \view\Template("page/no_access_admin.php");
				$child->addVariable("Acl", $acl);

				$this->template->setChild($child);
			}
		} catch (\storage\PageNotFoundException $e) {
			$child = new \view\Template("page/notfound.php");
			$child->addVariable("PageName", $e->getMessage());

			$parent = $e->getParentPage();
			if (!is_null($parent)) {
				$acl = $be->loadPageAcl($parent, \lib\CurrentUser::i());
			} else {
				$acl = $be->loadDefaultAcl(\lib\CurrentUser::i());
			}

			$child->addVariable("Parent", $e->getParentPage());
			$child->addVariable("Acl", $acl);

			$this->template->setChild($child);
		}
	}

	function saveAcl($path) {
		$be = $this->getBackend();

		try {
			$wikiPage = $be->loadPage($path);
			$acl = $be->loadPageAcl($wikiPage, \lib\CurrentUser::i());

			if ($acl->page_admin) {
				$set = new \models\WikiAclSet();

				$toVal = function($val) {
					if ($val == "1") return true;
					elseif ($val == "0") return false;
					elseif ($val == "-1") return NULL;
					else throw Exception("Invalid field value.");
				};

				$set->default = new \models\WikiAcl();
				$set->default->page_read = $toVal($_POST["default"]["read"]);
				$set->default->page_write = $toVal($_POST["default"]["write"]);
				$set->default->page_admin = $toVal($_POST["default"]["admin"]);
				$set->default->comment_read = $toVal($_POST["default"]["comment_read"]);
				$set->default->comment_write = $toVal($_POST["default"]["comment_write"]);

				if (isset($_POST["user"]) && is_array($_POST["user"])) {
					foreach ($_POST["user"] as $user => $acls) {
						$ua = new \models\WikiUserAcl();
						$ua->id = (int)$user;

						$ua->page_read = $toVal($acls["read"]);
						$ua->page_write = $toVal($acls["write"]);
						$ua->page_admin = $toVal($acls["admin"]);
						$ua->comment_read = $toVal($acls["comment_read"]);
						$ua->comment_write = $toVal($acls["comment_write"]);

						$set->users[] = $ua;
					}
				}

				if (isset($_POST["group"]) && is_array($_POST["group"])) {
					foreach ($_POST["group"] as $group=>$acls) {
						$ga = new \models\WikiGroupAcl();
						$ga->id = (int)$group;

						$ga->page_read = $toVal($acls["read"]);
						$ga->page_write = $toVal($acls["write"]);
						$ga->page_admin = $toVal($acls["admin"]);
						$ga->comment_read = $toVal($acls["comment_read"]);
						$ga->comment_write = $toVal($acls["comment_write"]);

						$set->groups[] = $ga;
					}
				}

				$be->storePageAcl($wikiPage, $set);
				$this->template->redirect($this->template->getSelf()."?acl");
			} else {
				$this->template->redirect($this->template->getSelf()."?acl");
			}
		} catch (\storage\PageNotFoundException $e) {
			$this->template->redirect($this->template->getSelf()."?acl");
		}
	}
}
