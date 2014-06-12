<?php

namespace models;

require_once "models/Model.php";

class User extends Model {
    protected $id = 0;
    protected $name;
    protected $email;
    protected $password;
    protected $salt;
    protected $registered;
    protected $last_login;

    protected $status_id;
    protected $logged_in;

    protected $email_token;
    protected $password_token;
    protected $email_verified;

    protected $privileges;

    const STATUS_LIVE = 1;
    const STATUS_BANNED = 2;

    static function validatePassword(&$password) {
        $password = trim($password); // trim whitespace

        $sec = array_merge(
            array(
                "minLength" => 6,
                "digits" => 1,
                "capital" => 1,
                "special" => 1,
            ),
            (array)\Config::Get("PasswordSecurity")
        );

        if (strlen($password) < $sec["minLength"]) {
            \view\Messages::Add("Password is too short. Minimum password length is 6 chars.", \view\Message::Error);
            return false;
        }

        if ($sec["digits"] > 0) {
            preg_match("/[0-9]/", $password, $matches);
            if (count($matches) < $sec["digits"]) {
                \view\Messages::Add("Password must contain at least ".$sec["digits"]." digits.");
                return false;
            }
        }

        if ($sec["capital"] > 0) {
            preg_match("/[A-Z]/", $password, $matches);
            if (count($matches) < $sec["capital"]) {
                \view\Messages::Add("Password must contain at least ".$sec["capital"]." capital letters.");
                return false;
            }
        }

        if ($sec["special"] > 0) {
            preg_match("/[^a-zA-Z0-9]/", $password, $matches);
            if (count($matches) < $sec["special"]) {
                \view\Messages::Add("Password must contain at least ".$sec["special"]." special letters.");
                return false;
            }
        }

        return true;
    }

    function hasPriv($name) {
        if (is_null($this->privileges)) {
            $be = \Config::Get("__Backend");
            $this->privileges = $be->loadUserPrivileges($this);
        }

        if (!isset($this->privileges[$name])) {
            return false;
        } else {
            return $this->privileges[$name]->value;
        }
    }

    function listAppliedPrivileges() {
        if (is_null($this->privileges)) {
            $be = \Config::Get("__Backend");
            $this->privileges = $be->loadUserPrivileges($this);
        }

        usort($this->privileges, function($a, $b) { return strcmp($a->name, $b->name); });

        return $this->privileges;
    }

    function profileLink(\view\Template $template) {
        return "<a href=\"".$template->url("/wiki:user/".$this->getName())."\">".htmlspecialchars($this->getName())."</a>";
    }
}

class UserAppliedPrivilege extends User {
    protected $priv_source;
}

class FakeUser extends User {
    protected $ip;

    function profileLink(\view\Template $template) {
        if (!is_null($this->name)) {
            return sprintf("%s (%s)", htmlspecialchars($this->getName()), $this->getIp());
        } else {
            return htmlspecialchars($this->getIp());
        }
    }
}

