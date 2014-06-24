<?php

namespace lib;

interface SessionIdSource {
    function getId($create = true);
    function setLifeTime($lifeTime);
}

interface SessionStorage {
    function setLifeTime($id, $lifeTime);
    function store($id, $name, &$value);
    function load($id, $name);
}

class SessionDataTuple {
    const PERSISTENT = 1;
    const NON_PERSISTENT = 2;
    const MARK_UNSET = 3;

    public $data;
    public $persistent = self::PERSISTENT;

    function __construct(&$data, $persistent = true) {
        $this->data = &$data;
        $this->persistent = ($persistent)?self::PERSISTENT:self::NON_PERSISTENT;
    }
}

class CookieIdSource implements SessionIdSource {
    protected $cookieName = "sessid";
    protected $cookieSet = false;
    protected $lifeTime = 0;

    function getId($create = true) {
        if (isset($_COOKIE[$this->cookieName])) {
            $id = $_COOKIE[$this->cookieName];
        } elseif ($create) {
            $id = Session::str_rand(32);
        } else {
            $id = NULL;
        }

        if (!$this->cookieSet && !headers_sent() && $create) {
            if (!headers_sent()) {
                setcookie(
                    $this->cookieName,
                    $id,
                    time() + 365 * 86400,
                    dirname($_SERVER["SCRIPT_NAME"]),
                    ".".$_SERVER["HTTP_HOST"]
                );
                $this->cookieSet = true;
            } else {
                // Cookie was not set, because headers were already sent.
            }
            $_COOKIE[$this->cookieName] = $id;
        }

        return $id;
    }

    function setLifeTime($lifeTime) {
        $this->lifeTime = $lifeTime;
        $this->cookieSet = false;
        $this->getId();
    }
}

class NullIdSource implements SessionIdSource {
    function getId($create = true) {
        return NULL;
    }

    function setLifeTime($lifeTime) {
    }
}

class CustomIdSource implements SessionIdSource {
    protected $id;

    function __construct($id = NULL) {
        if (is_null($id)) {
            $this->id = Session::str_rand(32);
        } else {
            $this->id = $id;
        }
    }

    function getId($create = true) {
        return $this->id;
    }

    function setLifeTime($lifeTime) {
    }
}

class NullSessionStorage implements SessionStorage {
    function setLifeTime($id, $lifeTime) {
    }

    function store($id, $name, &$value) {
    }

    function load($id, $name) {
        return NULL;
    }
}

class SessionImpl {
    protected $storage;
    protected $source;

    function setStorage(SessionStorage $storage) {
        $this->storage = $storage;
    }

    function setSource(SessionIdSource $source) {
        $this->source = $source;
    }

    function initDefaults() {
        if (is_null($this->storage)) $this->storage = new NullSessionStorage();
        if (is_null($this->source)) $this->source = new CookieIdSource();
    }

    function setLifeTime($lifeTime) {
        $this->initDefaults();
        $this->source->setLifeTime($lifeTime);
        $this->storage->setLifeTime(
            $this->source->getId(true),
            $lifeTime
        );
    }

    function get($name) {
        $this->initDefaults();

        return $this->storage->load(
            $this->source->getId(false),
            $name
        );
    }

    function set($name, &$value) {
        $this->initDefaults();

        $this->storage->store(
            $this->source->getId(true),
            $name,
            $value
        );
    }

    function getId() {
        $this->initDefaults();

        return $this->source->getId(false);
    }
}

class Session {
    private static $instance = NULL;

    protected static function getInstance() {
        if (is_null(self::$instance)) {
            self::$instance = new SessionImpl;
        }
        return self::$instance;
    }

    static function Free() {
        self::$instance = NULL;
    }

    static function isAvailable() {
        return !is_null(self::getInstance()->getId());
    }

    static function setStorage(SessionStorage $storage) {
        self::getInstance()->setStorage($storage);
    }

    static function setSource(SessionIdSource $source) {
        self::getInstance()->setSource($source);
    }

    static function setLifeTime($lifeTime) {
        self::getInstance()->setLifeTime($lifeTime);
    }

    static function Get($name) {
        return self::getInstance()->get($name);
    }

    static function Set($name, $value) {
        return self::getInstance()->set($name, $value);
    }

    static function ID() {
        return self::getInstance()->getId();
    }

    static function IP() {
        return $_SERVER["REMOTE_ADDR"];
    }

    /**
     * Generate random string with specified length and containing only
     * specified letters.
     * @param length Length of the string
     * @param bits Letters that string can contain
     */
    static function str_rand(
        $length = 8,
        $bits = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"
    ) {
        $bl = strlen($bits);
        $res = "";
        for ($x = 0; $x < $length; $x++) {
            $rand = rand(0, $bl - 1);
            $res .= $bits[$rand];
        }

        return $res;
    }

    static function ClearNonPersistent() {}
}

?>
