<?php

namespace specials;

/**
 * Base class for extended functionality of Wiki system.
 * The special controller can be used to extend wiki page action with other functionality. Anything that goes
 * beyond displaying Wiki content (which is implemented in WikiController) needs to inherit from this class.
 *
 * Each public method in this class that does not start by underscore can be called from the website by using
 * <special-name>:<method> URL pattern. If you want to hide the method from end users, make it private or protected
 * or prefix it with underscore.
 *
 * Each special controller must be registered by calling \Config::registerSpecial() method call.
 *
 * The controller can work in page context mode or outside the page context to provide system-wide functionality.
 * If you implement only functionality that is suitable for pages, don't forget to check if your method is being
 * called within page context. For that case, there is already prepared method ensurePageContext() that throws
 * \view\NotFound error if method is called outside the page context.
 *
 * In page context, you can assume that there is $relatedPage property set to the proper page. If you are outside
 * page context, the $relatedPage property is NULL.
 *
 */
class SpecialController {
	protected $template;       /**< Current rendering template */
	protected $relatedPage;    /**< Related page if controller was called in page context. */
    protected $Acl;            /**< ACL of current page. */

	function __construct(\view\Template $template, \models\WikiPage $relatedPage = NULL) {
		$this->template = $template;
		$this->relatedPage = $relatedPage;

        if (!is_null($this->relatedPage)) {
            $be = $this->getBackend();
            $this->Acl = $be->loadPageAcl($this->relatedPage, \lib\CurrentUser::i());
        }

		\view\RootTemplate::$beforeRenderObserver->registerObserver(new PageRelatedActions($this));
	}

    protected function getBackend() {
        return \Config::Get("__Backend");
    }

    /**
     * Ensures that we are working within context of a Wiki page. Throws \view\NotFound exception if
     * the method is executed outside page context.
     */
    protected function ensurePageContext() {
    	if (is_null($this->relatedPage)) {
			throw new \view\NotFound();
		}
    }

    function addPageActions() {
    	if (is_null($this->relatedPage)) {
    		return;
    	}

        if ($this->Acl->page_read && $this->relatedPage->getId()) {
            $this->template->addAction("View", $this->relatedPage->getFullUrl());
        }

		if ($this->Acl->page_write) {
			$this->template->addAction("Edit", $this->relatedPage->getFullUrl()."?edit");
		}

        if ($this->Acl->page_read && $this->relatedPage->getId()) {
		    $this->template->addAction("History", $this->relatedPage->getFullUrl()."?history");
            $this->template->addAction("References", $this->relatedPage->getFullUrl()."?references");
        }

		if ($this->Acl->page_admin && $this->relatedPage->getId()) {
			$this->template->addAction("ACLs", $this->relatedPage->getFullUrl()."?acl");
		}

        if ($this->Acl->page_write && $this->relatedPage->getId()) {
            $this->template->addAction("Create", $this->relatedPage->getFullUrl()."?create");
        }
	}

	function addPageLinks() {
		if (is_null($this->relatedPage)) {
    		return;
    	}

		$page = $this->relatedPage;

		while (!is_null($page)) {
			$this->template->prependNavigation($page->getName(), $page->getFullUrl());
			$page = $page->getParent();
		}

        //var_dump($this->relatedPage);
	}

    function addPageTitle() {
        if (is_null($this->relatedPage)) {
            return;
        }

        if (empty($this->template->getTitle())) {
            $this->template->setTitle($this->relatedPage->getName());
        }
    }
}

class PageRelatedActions implements \lib\Observer {
	public function __construct(SpecialController $controller) {
		$this->controller = $controller;
	}

	public function notify(\lib\Observable $template) {
        if ($template instanceof \view\RootTemplate) {
		    $this->controller->addPageActions();
		    $this->controller->addPageLinks();
            $this->controller->addPageTitle();
        }
	}
}
