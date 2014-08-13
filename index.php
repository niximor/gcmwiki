<?php

require_once "wiki/wiki.php";
require_once "wiki/view/Template.php";

// Parse URL and load apropriate wiki pages
$path = preg_split("#/+#", preg_replace('#^/|/$#', '', $_SERVER["PATH_INFO"]));
if (!empty($path) && empty($path[0])) $path = array();

$be = Config::Get("__Backend");

$page = new \view\RootTemplate("design.php");
$page->setSelf("/".implode("/", $path));

try {
	// MVC special pages
	foreach ($path as $key=>$part) {
		if (($pos = strpos($part, ':')) !== false) {
			$special = substr($part, 0, $pos);
			$method = substr($part, $pos + 1);

			$params = array_slice($path, $key + 1);

			if ($key > 0) {
				$path = array_slice($path, 0, $key);
			} else {
				$path = array();
			}
			
			break;
		}
	}

	function tryDisplayPage($page, $path) {
		$method = "page";
		$keys = array_keys($_GET);
		if (count($keys) > 0 && empty($_GET[$keys[0]])) {
			$method = $keys[0];
		}

		$controller = new \specials\WikiController($page, $path);
		if (method_exists($controller, $method)) {
			call_user_func(array($controller, $method), $path);
		} else {
			throw new \view\UnknownSpecialPage();
		}
	}

	try {
		if (isset($special)) {
			if (count($path) > 0) {
				$wikiPage = $be->loadPage($path);
			} else {
				$wikiPage = NULL;
			}

			$sp = Config::getSpecial($special);
		}
	} catch (\view\UnknownSpecialPage $e) {
		// Join back special path to page path to allow creation of special pages
		// that does not have MVC backend.
		$path[] = sprintf("%s:%s", $special, $method);
	}

	if (isset($sp)) {
		$controller = new $sp($page, $wikiPage);

		if (method_exists($controller, $method)) {
			call_user_func_array(array($controller, $method), $params);
		} else {
			throw new \view\UnknownSpecialPage();
		}
	} else {
		tryDisplayPage($page, $path);
	}
} catch (\view\UnknownSpecialPage $e) {
	$child = new \view\Template("no_special.php");
	$child->addVariable("PageName", $special.":".$method);
	$page->setChild($child);
} catch (\view\NotFound $e) {
	$child = new \view\Template("wiki/not_found.php");
	$child->addVariable("Exception", $e);
	$page->setChild($child);
} catch (\view\AccessDenided $e) {
	$child = new \view\Template("need_privileges.php");
	$child->addVariable("Exception", $e);
	$page->setChild($child);
}

$page->render();

\lib\Session::Free();

