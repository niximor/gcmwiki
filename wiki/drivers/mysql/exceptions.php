<?php

namespace drivers\mysql;

class BaseException extends \drivers\Exception {
}

class Exception extends BaseException {
    public function __construct($msg, $cs) {
        parent::__construct($msg." at ".$cs, 0);
    }
}

class ConnectException extends BaseException {
    public function __construct($link, $cs) {
        parent::__construct("Error occured while trying to connect to ".$cs.": ".$link->connect_error, $link->connect_errno);
    }
}

class QueryException extends BaseException {
    protected $query;
    
    public function __construct($link, $query, $cs) {
        $this->query = $query;
        if (is_object($link)) {
            parent::__construct("While executing query on ".$cs.": ".$link->error, $link->errno);
        } else {
            parent::__construct("While executing query on ".$cs.": ".$link, 0);
        }
    }

    public function getQuery() {
        return $this->query;
    }
}
