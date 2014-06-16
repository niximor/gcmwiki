<?php

namespace specials;

class SpecialController {
	protected $template;
	protected $relatedPage;

	function __construct(\view\Template $template, \models\WikiPage $relatedPage = NULL) {
		$this->template = $template;
		$this->relatedPage = $relatedPage;
	}

    protected function getBackend() {
        return \Config::Get("__Backend");
    }

    function addPageActions() {
		if ($this->Acl->page_write) {
			$this->template->addAction("Edit", $this->relatedPage->getUrl()."?edit");
		}

		$this->template->addAction("History", $this->relatedPage->getUrl()."?history");

		if ($this->Acl->page_admin) {
			$this->template->addAction("ACLs", $this->relatedPage->getUrl()."?acl");
		}
	}

	function addPageLinks() {
		$parents = array();
		$parent = $this->relatedPage;
		while (!is_null($parent)) {
			$parents[] = $parent;
			$parent = $parent->getParent();
		}

		$path = "";
		foreach (array_reverse($parents) as $parent) {
			$path .= "/".$parent->getUrl();
			$this->template->addNavigation($parent->getName(), $path);
		}
	}
}
