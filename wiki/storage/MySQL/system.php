<?php

namespace storage\MySQL;

require_once "storage/MySQL/base.php";

class System extends Module {
    function listSystemVariables() {
        $trans = $this->base->db->beginRO();
        $res = $trans->query("SELECT id, name, value FROM system_config ORDER BY name ASC");
        $res->setClassFactory("\\models\\SystemVariable");

        $out = array();
        foreach ($res as $row) {
            $out[] = $row;
        }

        $trans->commit();

        return $out;
    }

    function setSystemVariables($variables) {
        $trans = $this->base->db->beginRW();

        foreach ($variables as $var) {
            $trans->query("UPDATE system_config SET value = %s WHERE name = %s", $var->value, $var->name);
        }

        $trans->commit();
    }
}
