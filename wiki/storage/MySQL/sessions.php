<?php

namespace storage\MySQL;

class SessionStorage implements \lib\SessionStorage {
    protected $lifeTime = array();
    protected $storage = array();
    protected $idLoaded = array();
    protected $idChanged = array();

    function __construct(StorageBase $base) {
        $this->base = $base;

        $trans = $this->base->db->beginRW();
        $trans->query("DELETE FROM sessions WHERE activity < DATE_ADD(NOW(), INTERVAL -lifetime SECOND)");
        $trans->commit();
    }

    function __destruct() {
        $trans = $this->base->db->beginRW();

        foreach ($this->storage as $id=>&$data) {
            // Skip NULL IDs
            if (!$id) continue;

            $lifeTime = 3600;
            if (isset($this->lifeTime[$id])) {
                $lifeTime = $this->lifeTime[$id];
            }

            // Load all session data if not loaded.
            if (!isset($this->idLoaded[$id])) {
                $this->load($id, NULL, $trans);
            }

            $ip = $_SERVER["REMOTE_ADDR"];

            $sets = array();
            $values = array();

            foreach ($data as $key=>$tuple) {
                if ($tuple->persistent == \lib\SessionDataTuple::MARK_UNSET) {
                    unset($data[$key]);
                    $trans->query("DELETE FROM sessions WHERE sessid = %s AND name = %s", $id, $key);
                } else {
                    if ($tuple->persistent == \lib\SessionDataTuple::NON_PERSISTENT) {
                        $tuple->persistent = \lib\SessionDataTuple::MARK_UNSET;
                    }

                    $sets[] = "(%s, NOW(), %s, %s, %s, %s, %s, %s)";

                    if (!is_scalar($tuple->data)) {
                        $type = 'binary';
                        $value = base64_encode(serialize($tuple->data));
                    } else {
                        $type = 'plain';
                        $value = $tuple->data;
                    }

                    $values = array_merge($values, array($id, $lifeTime, $ip, $key, $tuple->persistent, $type, $value));
                }
            }
            
            if (!empty($values)) {
                $trans->Query("INSERT INTO sessions (sessid, activity, lifetime, ip, name, persistent, type, value) VALUES ".implode(",", $sets)."
                        ON DUPLICATE KEY UPDATE
                        activity = VALUES(activity),
                        lifetime = VALUES(lifetime),
                        type = VALUES(type), persistent = VALUES(persistent), value = VALUES(value)", $values);
            }
        }

        $trans->commit();
    }

    function setLifeTime($id, $lifeTime) {
        if (!isset($this->lifeTime[$id]) || $this->lifeTime[$id] != $lifeTime) {
            $trans = $this->base->db->beginRW();
            $trans->query("UPDATE sessions SET lifetime = %d WHERE sessid = %s", $lifeTime, $id);
            $trans->commit();
            $this->lifeTime[$id] = $lifeTime;
        }
    }

    function store($id, $name, &$value, $persistent = true) {
        if (!isset($this->storage[$id])) {
            $this->storage[$id] = array();
        }

        if (is_null($value)) {
            if (isset($this->storage[$id][$name])) {
                $this->storage[$id][$name]->persistent = \lib\SessionDataTuple::MARK_UNSET;
            }
        } else {
            $this->storage[$id][$name] = new \lib\SessionDataTuple($value, $persistent);
        }
        $this->idChanged[$id] = true;
    }

    function load($id, $name, $trans = NULL) {
        if (!isset($this->idLoaded[$id]) && !is_null($id)) {
            try {
                // Here we are selecting from master because of replication delay.
                $transactionStarted = false;
                if (is_null($trans)) {
                    $trans = $this->base->db->beginRW();
                    $transactionStarted = true;
                }

                $data = $trans->query("SELECT name, persistent, type, value, lifetime FROM sessions WHERE sessid = %s", $id);


                // Do not overwrite existing data
                if (!isset($this->storage[$id])) {
                    $this->storage[$id] = array();
                }

                foreach ($data as $row) {
                    $lifeTime = $row->lifetime;

                    if ($row->type != "plain") {
                        $row->value = unserialize(base64_decode($row->value));
                    }

                    if (!isset($this->storage[$id][$row->name])) {
                        $this->storage[$id][$row->name] = new \lib\SessionDataTuple($row->value, $row->persistent);
                    }
                }

                if (!isset($this->lifeTime[$id])) {
                    $this->lifeTime[$id] = $lifeTime;
                    \lib\Session::setLifeTime($lifeTime);
                }

                if ($transactionStarted) {
                    $trans->commit();
                }
            } catch (\drivers\EntryNotFoundException $e) {
            }

            $this->idLoaded[$id] = true;
        }

        if (isset($this->storage[$id][$name]) && $this->storage[$id][$name]->persistent != \lib\SessionDataTuple::MARK_UNSET) {
            return $this->storage[$id][$name]->data;
        } else {
            return NULL;
        }
    }
}
