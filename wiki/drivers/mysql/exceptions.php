<?php

namespace drivers\mysql;

class Exception extends \drivers\Exception {
}

class ConnectException extends Exception {
    protected $errno;

    public function __construct($link) {
        parent::__construct($link->connect_error, $link->connect_errno);
    }
}

class QueryException extends Exception {
    protected $query;
    protected $errno;

    public function __construct($link, $query) {
        $this->query = $query;
        if (is_object($link)) {
            parent::__construct($link->error, $link->errno);
        } else {
            parent::__construct($link, 0);
        }
    }

    public function getQuery() {
        return $this->query;
    }
}
