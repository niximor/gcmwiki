<?php

namespace specials;

/* This is not in fact a special controller, because it is called directly when no other controller is specified.
 * But it uses the same logic as special controllers, only without registration as a controller, so it is there.
 */

require_once "lib/diff.php";
require_once "specials/SpecialController.php";

class WikiController extends SpecialController {
	protected $pageName;
	protected $Acl;

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
				$this->relatedPage = $be->loadPage($path, array("body_html", "locked"), $_GET["revision"]);
			} else {
				$this->relatedPage = $be->loadPage($path, array("body_html", "locked"));
			}

			$this->Acl = $be->loadPageAcl($this->relatedPage, \lib\CurrentUser::i());

			$redirect = $this->relatedPage->getRedirected_from();
			if (!is_null($redirect)) {
				$aclRedirect = $be->loadPageAcl($redirect, \lib\CurrentUser::i());
			}

			if ($this->Acl->page_read && (is_null($redirect) || $aclRedirect->page_read)) {
				$comments = $be->loadComments($this->relatedPage);

				$filter = new \lib\Object();
				$attachments = $be->getAttachmentsModule()->load(
					$filter->setRelatedPageId($this->relatedPage->getId()),
					array("id", "name", "type_string")
				);

				$child = new \view\Template("page/index.php");
				$child->addVariable("Page", $this->relatedPage);
				$child->addVariable("Acl", $this->Acl);
				$child->addVariable("Comments", $comments);
				$child->addVariable("Attachments", $attachments);

				$this->template->setChild($child);
			} else {
				$child = new \view\Template("page/no_access_read.php");
				$child->addVariable("Acl", $this->Acl);

				$this->template->setChild($child);
			}
		} catch (\storage\PageNotFoundException $e) {
			$this->handlePageNotFound($e);
		}
	}

	function create($path) {
		$be = $this->getBackend();

		try {
			$this->relatedPage = $be->loadPage($path);
			$this->Acl = $be->loadPageAcl($this->relatedPage, \lib\CurrentUser::i());

			if (!$this->Acl->page_write) {
				$child = new \view\Template("page/no_access_write.php");
				$this->template->setChild($child);
				return;
			}

			if ($_SERVER["REQUEST_METHOD"] == "POST") {
				if (!empty($_REQUEST["name_child"])) {
					$path = "/".$this->relatedPage->getFullUrl()."/".\models\WikiPage::nameToUrl($_REQUEST["name_child"]);
					$this->template->redirect($path."?edit");
				} elseif (!empty($_REQUEST["name"])) {
					$path = "/".\models\WikiPage::nameToUrl($_REQUEST["name"]);
					$this->template->redirect($path."?edit");
				}
			}

			$child = new \view\Template("page/create.php");
			$child->addVariable("Page", $this->relatedPage);

			$this->template->setChild($child);
		} catch (\storage\PageNotFoundException $e) {
			$this->handlePageNotFound($e);
		}

		$this->template->addNavigation("Create", $this->template->getSelf()."?create");
	}

	function edit($path) {
		$be = $this->getBackend();

		try {
			$this->relatedPage = $be->loadPage($path, array("body_wiki", "locked", "renderer", "template"), NULL, false);
			$this->Acl = $be->loadPageAcl($this->relatedPage, \lib\CurrentUser::i());
		} catch (\storage\PageNotFoundException $e) {
			$parent = $e->getParentPage();
			if (!is_null($parent)) {
				$this->Acl = $be->loadPageAcl($parent, \lib\CurrentUser::i());
			} else {
				$this->Acl = $be->loadDefaultAcl(\lib\CurrentUser::i());
			}

			$this->relatedPage = new \models\WikiPage();
			$this->relatedPage->setUrl($e->getMessage());
			$this->relatedPage->setName($e->getMessage());
			$this->relatedPage->setParent($parent);
		}

		if ($this->Acl->page_write) {
			$child = new \view\Template("page/edit.php");
			$child->addVariable("Page", $this->relatedPage);
			$child->addVariable("Acl", $this->Acl);
			$child->addVariable("Errors", (array)\lib\Session::Get("Errors"));
			$child->addVariable("Form", (array)\lib\Session::Get("Form"));

			\lib\Session::Set("Errors", NULL);
			\lib\Session::Set("Form", NULL);

			$this->template->setChild($child);
		} else {
			$child = new \view\Template("page/no_access_write.php");
			$child->addVariable("Page", $this->relatedPage);
			$child->addVariable("Acl", $this->Acl);

			$this->template->setChild($child);
		}

		$this->template->addNavigation("Edit", $this->template->getSelf()."?edit");
	}

	function save($path) {
		$be = $this->getBackend();

		try {
			$this->relatedPage = $be->loadPage($path, NULL, NULL, false);
			$this->Acl = $be->loadPageAcl($this->relatedPage, \lib\CurrentUser::i());
		} catch (\storage\PageNotFoundException $e) {
			$parent = $e->getParentPage();
			if (!is_null($parent)) {
				$this->Acl = $be->loadPageAcl($e->getParentPage(), \lib\CurrentUser::i());
			} else {
				$this->Acl = $be->loadDefaultAcl(\lib\CurrentUser::i());
			}

			$this->relatedPage = new \models\WikiPage();
			$this->relatedPage->setUrl($e->getMessage());
			$this->relatedPage->setName($e->getMessage());
			$this->relatedPage->setParent($parent);
		}

		if (!$this->Acl->page_write && !$this->Acl->page_admin) {
			$this->template->redirect($this->template->getSelf()."?edit");
			return;
		}

		$urlChanged = false;
		if (isset($_POST["body"]) && isset($_POST["name"])) {
			if (!$this->relatedPage->locked || $this->Acl->page_admin) {
				$this->relatedPage->updateBody($_POST["body"]);

				$oldName = $this->relatedPage->getName();
				$this->relatedPage->setName($_POST["name"]);

				$oldUrl = $this->relatedPage->getUrl();
				$newUrl = \models\WikiPage::nameToUrl($_POST["name"]);

				if ($newUrl != $oldUrl) {
					$this->relatedPage->setUrl($newUrl);
					$urlChanged = strtolower($newUrl) != strtolower($oldUrl);
				}
			} else {
				\lib\Messages::Add("This page is locked. You cannot edit it.", \lib\Message::Error);
			}

			$this->relatedPage->setSmall_change((isset($_POST["small_change"]))?true:false);
			$this->relatedPage->setSummary($_POST["summary"]);

			if ($this->Acl->page_admin) {
				if (isset($_POST["locked"])) {
					$this->relatedPage->setLocked($_POST["locked"] == "1");
				}

				// TODO: Validate renderer
				if (isset($_POST["renderer"])) {
					$this->relatedPage->setRenderer($_POST["renderer"]);
				}

				// TODO: Validate template
				if (isset($_POST["template"])) {
					$this->relatedPage->setTemplate($_POST["template"]);
				}
			}
		}

		try {
			$be->storePage($this->relatedPage);

			// Generate "fake" redirect page from the old URL.
			if ($urlChanged) {
				$oldPage = new \models\WikiPage();
				$oldPage->setName($oldName);
				$oldPage->setUrl($oldUrl);
				$oldPage->setParent($this->relatedPage->getParent());
				$oldPage->updateBody("{{{redirect:".$newUrl."}}}");

				$be->storePage($oldPage);
			}

			$this->template->redirect("/".implode("/", $this->relatedPage->getPath()));
		} catch (\storage\Diagnostics $e) {
			\lib\Session::Set("Errors", $e->getErrorsForFields(), false);
			\lib\Session::Set("Form", $_POST, false);

			$this->template->redirect($this->template->getSelf()."?edit");
		}
	}

	function history($path) {
		$be = $this->getBackend();

		try {
			$this->relatedPage = $be->loadPage($path, NULL, NULL, false);
			$this->Acl = $be->loadPageAcl($this->relatedPage, \lib\CurrentUser::i());

			if ($this->Acl->page_read) {
				$summary = $be->getHistorySummary($this->relatedPage);

				$child = new \view\Template("page/history.php");

				$child->addVariable("History", $summary);
				$child->addVariable("Page", $this->relatedPage);
				$child->addVariable("Acl", $this->Acl);

				$this->template->setChild($child);
			} else {
				$child = new \view\Template("page/no_access_read.php");
				$child->addVariable("Acl", $this->Acl);

				$this->template->setChild($child);
			}
		} catch (\storage\PageNotFoundException $e) {
			$this->handlePageNotFound($e);
		}

		$this->template->addNavigation("History", $this->template->getSelf()."?history");
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
			$this->relatedPage = $be->loadPage($path, array("body_wiki", "revision"), NULL, false);
			$this->Acl = $be->loadPageAcl($this->relatedPage, \lib\CurrentUser::i());

			if ($this->Acl->page_read) {
				if ($this->relatedPage->getRevision() != $rev1) {
					try {
						$rev1 = $be->loadPage($path, array("body_wiki", "revision"), $rev1, false);
					} catch (\storage\PageNotFoundException $e) {
						$child = new \view\Template("page/diff_norev.php");
						$child->addVariable("Page", $this->relatedPage);
						$child->addVariable("Revision", $rev1);
						$this->template->setChild($child);
						return;
					}
				} else {
					$rev1 = $this->relatedPage;
				}
				if ($this->relatedPage->getRevision() != $rev2) {
					try {
						$rev2 = $be->loadPage($path, array("body_wiki", "revision"), $rev2, false);
					} catch (\storage\PageNotFoundException $e) {
						$child = new \view\Template("page/diff_norev.php");
						$child->addVariable("Page", $this->relatedPage);
						$child->addVariable("Revision", $rev2);
						$this->template->setChild($child);
						return;
					}
				} else {
					$rev2 = $this->relatedPage;
				}

				$child = new \view\Template("page/diff.php");

				$diff = new \lib\Diff();
				$computed = $diff->compute($rev2->body_wiki, $rev1->body_wiki);

				$child->addVariable("Page", $this->relatedPage);
				$child->addVariable("Revision1", $rev1);
				$child->addVariable("Revision2", $rev2);
				$child->addVariable("Diff", $computed);

				$this->template->setChild($child);
			} else {
				$child = new \view\Template("page/no_access_read.php");
				$child->addVariable("Acl", $this->Acl);

				$this->template->setChild($child);
			}
		} catch (\storage\PageNotFoundException $e) {
			$this->handlePageNotFound($e);
		}

		$this->template->addNavigation("History", $this->template->getSelf()."?history");
		$this->template->addNavigation(sprintf("Changes between revision %d and %d", $rev1->revision, $rev2->revision), $this->template->getSelf()."?diff&a=".$rev1->revision."&b=".$rev2->revision);
	}

	function references($path) {
		$be = $this->getBackend();

		try {
			$this->relatedPage = $be->loadPage($path, NULL, NULL, false);
			$this->Acl = $be->loadPageAcl($this->relatedPage, \lib\CurrentUser::i());

			$filter = new \lib\Object();
			$ref = $be->listPages($filter->setLinksTo($this->relatedPage->getId()));

			$child = new \view\Template("page/references.php");
			$child->addVariable("Page", $this->relatedPage);
			$child->addVariable("References", $ref);

			$this->template->setChild($child);
		} catch (\storage\PageNotFoundException $e) {
			$this->handlePageNotFound($e);
		}

		$this->template->addNavigation("References", $this->template->getSelf()."?references");
	}

	function acl($path) {
		$be = $this->getBackend();

		try {
			$this->relatedPage = $be->loadPage($path, NULL, NULL, false);
			$this->Acl = $be->loadPageAcl($this->relatedPage, \lib\CurrentUser::i());

			if ($this->Acl->page_admin) {
				$child = new \view\Template("page/acl.php");
				$child->addVariable("Page", $this->relatedPage);
				$child->addVariable("Acl", $this->Acl);
				$child->addVariable("Acls", \models\WikiAcl::listAcls());

				$child->addVariable("PageAcls", $be->listPageAcl($this->relatedPage));
				$child->addVariable("Users", $be->listUsers());
				$child->addVariable("Groups", $be->listGroups());

				$this->template->setChild($child);
			} else {
				$child = new \view\Template("page/no_access_admin.php");
				$child->addVariable("Acl", $this->Acl);

				$this->template->setChild($child);
			}
		} catch (\storage\PageNotFoundException $e) {
			$this->handlePageNotFound($e);
		}

		$this->template->addNavigation("ACLs", $this->template->getSelf()."?acl");
	}

	function saveAcl($path) {
		$be = $this->getBackend();

		try {
			$this->relatedPage = $be->loadPage($path, NULL, NULL, false);
			$this->Acl = $be->loadPageAcl($this->relatedPage, \lib\CurrentUser::i());

			if ($this->Acl->page_admin) {
				$set = new \models\WikiAclSet();

				$toVal = function($val) {
					if ($val == "1") return true;
					elseif ($val == "0") return false;
					elseif ($val == "-1") return NULL;
					else throw Exception("Invalid field value.");
				};

				$set->default = new \models\WikiAcl();

				$acls = \models\WikiAcl::listAcls();

				foreach ($acls as $name) {
					$set->default->$name = $toVal($_POST["default"][$name]);
				}

				if (isset($_POST["user"]) && is_array($_POST["user"])) {
					foreach ($_POST["user"] as $user => $acls) {
						$ua = new \models\WikiUserAcl();
						$ua->id = (int)$user;

						foreach ($acls as $name) {
							$ua->$name = $toVal($acls[$name]);
						}

						$set->users[] = $ua;
					}
				}

				if (isset($_POST["group"]) && is_array($_POST["group"])) {
					foreach ($_POST["group"] as $group=>$acls) {
						$ga = new \models\WikiGroupAcl();
						$ga->id = (int)$group;

						foreach ($acls as $name) {
							$ga->$name = $toVal($acls[$name]);
						}

						$set->groups[] = $ga;
					}
				}

				$be->storePageAcl($this->relatedPage, $set);
				$this->template->redirect($this->template->getSelf()."?acl");
			} else {
				$this->template->redirect($this->template->getSelf()."?acl");
			}
		} catch (\storage\PageNotFoundException $e) {
			$this->template->redirect($this->template->getSelf()."?acl");
		}
	}

	protected function handlePageNotFound($e) {
		$be = $this->getBackend();

		$this->relatedPage = new \models\WikiPage();
		$this->relatedPage->setParent($e->getParentPage());
		$this->relatedPage->setUrl($e->getMessage());
		$this->relatedPage->setName($e->getMessage());

		$child = new \view\Template("page/notfound.php");
		$child->addVariable("PageName", $e->getMessage());

		$parent = $e->getParentPage();
		if (!is_null($parent)) {
			$this->Acl = $be->loadPageAcl($parent, \lib\CurrentUser::i());
		} else {
			$this->Acl = $be->loadDefaultAcl(\lib\CurrentUser::i());
		}

		$child->addVariable("Parent", $e->getParentPage());
		$child->addVariable("Acl", $this->Acl);

		$this->template->setChild($child);
	}
}
