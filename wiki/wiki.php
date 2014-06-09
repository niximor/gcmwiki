<?php

// Include path for the classes
set_include_path(implode(PATH_SEPARATOR, array(
	dirname(__FILE__),
)));

mb_internal_encoding("UTF-8");

// Configuration class
require_once "config.php";
require_once "lib/debugger.php";

// Basic classes
require_once "lib/Sessions.php";
require_once "view/Messages.php";

// Site configuration
$config_dir = dirname(__FILE__)."/../config/";
include $config_dir."config.php";
if (file_exists($config_dir."site-specific.config.php")) {
	include $config_dir."site-specific.config.php";
}

// Storage class
$backend = Config::Get("Backend");
if (is_null($backend)) {
	throw new RuntimeException("No backend configured.");
}
require_once("storage/".$backend.".php");
$backend = "\\storage\\".$backend;
Config::Set("__Backend", $be = new $backend());

\lib\Session::setStorage($be->getSessionStorage());
\lib\Session::setLifeTime(3600);

// Apply database configuration
foreach ($be->listSystemVariables() as $var) {
    $name = explode(".", $var->name);
    if (count($name) > 1) {
        $coreitem = array_shift($name);
        $origar = Config::Get($coreitem);
        if (!is_array($origar)) {
            $origar = array();
        }

        $ar = &$origar;

        foreach ($name as $item) {
            $ar[$item] = NULL;
            $ar = &$ar[$item];
        }

        $ar = $var->value;
        Config::Set($coreitem, $origar);
    } else {
        Config::Set($name[0], $var->value);
    }
}

require_once "lib/CurrentUser.php";
\lib\CurrentUser::i();

// Load special controllers
$d = dir(dirname(__FILE__)."/specials/");
while ($f = $d->read()) {
	if ($f[0] != '.' && substr($f, -4) == '.php') {
		require_once $d->path.DIRECTORY_SEPARATOR.$f;
	}
}

$d->close();

