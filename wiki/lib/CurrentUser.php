<?php

namespace lib;

require_once "models/User.php";

class CurrentUser {
    static protected $instance = NULL;

    static function i() {
        if (!self::$instance) {
            if (Session::Get("UserId") != NULL) {
                $be = \Config::Get("__Backend");
                self::$instance = $be->loadUserInfo(Session::Get("UserId"));
            } else {
                self::$instance = new \models\User();
            }
        }

        return self::$instance;
    }

    static function isLoggedIn() {
        return self::i()->getId() > 0;
    }

    static function Verify($username, $password) {
        $be = \Config::Get("__Backend");
        return $be->verifyUser($username, $password);
    }

    static function ID() {
        return self::i()->getId();
    }

    static function hasPriv($name) {
        return self::i()->hasPriv($name);
    }
}
