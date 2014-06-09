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
}
