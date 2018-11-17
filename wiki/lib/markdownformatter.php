<?php

namespace lib;

require_once __DIR__.DIRECTORY_SEPARATOR."formatter.php";

spl_autoload_register(function($className) {
	if (substr($className, 0, strlen("cebe\\markdown")) == "cebe\\markdown") {
		$filename = __DIR__.DIRECTORY_SEPARATOR."markdown".DIRECTORY_SEPARATOR.
			str_replace("\\", DIRECTORY_SEPARATOR, substr($className, strlen("cebe\\markdown"))).".php";

		if (file_exists($filename)) {
			require_once $filename;
		}
	}
});

use \cebe\markdown\GithubMarkdown;

class MarkdownFormatter implements Formatter {
	public function format($text, \models\WikiPage $pageContext=NULL) {
		$parser = new \cebe\markdown\GithubMarkdown();
		$parser->html5 = true;
		$parser->keepListStartNumber = true;
		return $parser->parse($text);
	}

	public function getRootContext() {
		return NULL;
	}
}
