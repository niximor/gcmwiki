<?php

namespace drivers\mysql;

require_once "drivers/exceptions.php";
require_once "drivers/mysql/exceptions.php";

class Config {
	protected $host;
	protected $user;
	protected $password;
	protected $database;
	protected $collate;

	protected $connected;
	protected $link;
	protected $inTransaction = false;

	public function __construct($host, $user, $password, $database, $collate="utf8_general_ci") {
		$this->host = $host;
		$this->user = $user;
		$this->password = $password;
		$this->database = $database;
		$this->collate = $collate;
	}

	public function link() {
		if (!$this->connected) {
			$this->link = new \mysqli($this->host, $this->user, $this->password, $this->database);
			if ($this->link->error) {
				throw new ConnectException($this->link);
			}

			$this->link->autocommit = false;

			$this->link->query("SET NAMES utf8");
			$this->link->query("SET collation_connection='".$this->link->escape_string($this->collate)."'");
			$this->link->query("SET CHARACTER SET 'utf8'");

			$this->connected = true;
		}

		return $this->link;
	}

	public function isInTransaction() {
		return $this->inTransaction;
	}

	public function begin() {
		if ($this->isInTransaction()) {
			throw new Exception("Transaction already opened.");
		}

		$link = $this->link();
		if (method_exists($link, "begin_transaction")) {
			$link->begin_transaction();
		} else {
			$link->query("BEGIN TRANSACTION");
		}

		$this->inTransaction = true;
	}

	public function commit() {
		if (!$this->isInTransaction()) {
			throw new Exception("Unable to commit. No transaction opened.");
		}

		$this->link()->commit();
		$this->inTransaction = false;
	}

	public function rollback() {
		if (!$this->isInTransaction()) {
			throw new Exception("Unable to rollback. No transaction opened.");
		}

		$this->link()->rollback();
		$this->inTransaction = false;
	}
}


class Result implements \Iterator {
	protected $result;
	protected $currentRow = NULL;
	protected $currentRowIndex = -1;
	protected $classFactory = "\\stdClass";

	function __construct(\mysqli_result $result) {
		$this->result = $result;
		$this->rewind();
	}

	function setClassFactory($class) {
		$this->classFactory = $class;
	}

	function fetch($class="\\stdClass") {
		$this->classFactory = $class;

		if (is_null($this->currentRow)) {
			$this->next();
		}

		if (!is_null($this->currentRow)) {
			$row = $this->currentRow;
			$this->next();
			return $row;
		} else {
			throw new \drivers\EntryNotFoundException();
		}
	}

	function rewind() {
		$this->result->data_seek(0);
		$this->currentRowIndex = -1;
	}

	function next() {
		$this->currentRow = $this->result->fetch_object($this->classFactory);
		++$this->currentRowIndex;
	}

	function key() {
		return $this->currentRowIndex;
	}

	function valid() {
		if ($this->currentRowIndex < 0) $this->next();
		return !is_null($this->currentRow);
	}

	function current() {
		if (is_null($this->currentRow)) {
			$this->next();
		}

		return $this->currentRow;
	}
}


class Transaction {
	protected $config;

	function __construct(Config $config) {
		$this->config = $config;
		$this->config->begin();
	}

	protected function prepareValue(&$item, $key, $link) {
		// First key is query itself, skip it.
		if ($key > 0) {
			if (is_object($item)) {
				$item = "'".$link->escape_string((string)$item)."'";
			} elseif (is_null($item)) {
				$item = "NULL";
			} elseif (is_string($item)) {
				$item = "'".$link->escape_string($item)."'";
			} elseif (is_bool($item)) {
				$item = ($item)?"1":"0";
			} elseif (!is_int($item) && !is_bool($item) && !is_float($item)) {
				throw new Exception("Unable to use variable of type ".gettype($item)." in SQL.");
			}
		}
	}

	function query($query) {
		if (!$this->config->isInTransaction()) {
			throw new Exception("No transaction open.");
		}

		$link = $this->config->link();

		$args = func_get_args();
		$numargs = count($args);
		if ($numargs > 1) {
			if ($numargs == 2 && is_array($args[1])) {
				$args = array_merge(array($args[0]), $args[1]);
			}

			array_walk($args, array($this, "prepareValue"), $link);

			$queryBefore = $query;
			$query = @call_user_func_array("sprintf", $args);
			if (!$query) {
				throw new QueryException("Not all arguments in format string got converted.", $queryBefore);
			}
		}

		//echo $query."<br />";
		$result = $link->query($query);
		if ($link->errno != 0) {
			throw new QueryException($link, $query);
		}

		if (is_object($result) && $result instanceof \mysqli_result) {
			return new Result($result);
		} else {
			return $result;
		}
	}

	function lastInsertId() {
		$link = $this->config->link();
		return $link->insert_id;
	}

	function commit() {
		$this->config->commit();
	}

	function rollback() {
		$this->config->rollback();
	}

	function __destruct() {
		if ($this->config->isInTransaction()) {
			trigger_error("Rolling back uncommited transaction.", E_USER_NOTICE);
			$this->rollback();
		}
	}
}

class MySQL {
	protected $master;
	protected $slave;

	public function __construct(Config $master, Config $slave=NULL) {
		$this->master = $master;
		$this->slave = $slave;
	}

	public function beginRW() {
		return new Transaction($this->master);
	}

	public function beginRO() {
		if (!is_null($this->slave)) {
			return new Transaction($this->slave);
		} else {
			return new Transaction($this->master);
		}
	}
}

