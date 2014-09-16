<?php

namespace storage\MySQL;

require_once "storage/MySQL/base.php";

class Users extends Module {
    public function loadUserInfo($id, $matchColumn = "id", $columns = array(), $trans = NULL) {
        $transactionStarted = false;
        if (is_null($trans)) {
            $trans = $this->base->db->beginRO();
            $transactionStarted = true;
        }

        $columns = array_merge(
            array("id", "name", "email", "registered", "last_login", "email_verified"),
            $columns
        );

        $getSessionInfo = false;
        if (in_array("logged_in", $columns)) {
            $getSessionInfo = true;
            $key = array_search("logged_in", $columns);
            unset($columns[$key]);
        }

        $query = "SELECT ".implode(",", $columns);

        if ($getSessionInfo) {
            $query .= " IF(s.id IS NOT NULL, 1, 0) AS logged_in";
        }

        $query .= " FROM users ";

        if ($getSessionInfo) {
            $query .= "LEFT JOIN sessions s ON (s.name = 'UserId' AND s.value = u.id AND s.activity > DATE_ADD(NOW(), INTERVAL -15 MINUTE))";
        }

        $query .= "WHERE ";
        $vals = array();
        if (is_array($matchColumn)) {
            $first = true;
            foreach ($matchColumn as $col) {
                if ($first) $first = false;
                else $query .= " OR ";
                $query .= $col." = %s";
                $vals[] = $id;
            }
        } else  {
            $query .= $matchColumn." = %s";
            $vals[] = $id;
        }

        if ($getSessionInfo) {
            $query .= " GROUP BY u.id";
        }

        try {
            $res = $trans->query($query, $vals)->fetch("\\models\\User");

            if ($transactionStarted) {
                $trans->commit();
            }
        } catch (\Exception $e) {
            if ($transactionStarted) {
                $trans->commit();
            }

            throw new \storage\UserNotFoundException($id, $e);
        }

        return $res;
    }

    public function verifyUser($username, $password) {
        $trans = $this->base->db->beginRW();

        $result = false;

        try {
            $res = $trans->query("SELECT id, salt, password FROM users WHERE name = %s AND status_id = %s", $username, \models\User::STATUS_LIVE)->fetch("\\models\\User");

            if (hash("sha256", $res->salt.$password) == $res->password) {
                $result = $this->loadUserInfo($res->id, "id", array(), $trans);

                $trans->query("UPDATE users SET last_login = NOW() WHERE id = %s", $result->getId());
            }
        } catch (\drivers\EntryNotFoundException $e) {
        }

        $trans->commit();

        return $result;
    }

    public function storeUserInfo(\models\User $user) {
        $trans = $this->base->db->beginRW();

        $diag = new Diagnostics();

        // Test for user already exists.
        if ($user->getId()) {
            if ($user->isChanged("name")) {
                $res = $trans->query("SELECT id FROM users WHERE name = %s AND id <> %s", $user->getName(), $user->getId());
            }
        } else {
            // When creating new user, it must exists.
            $name = $user->getName();
            if (empty($name)) {
                $diag->addError("name", "name_must_be_present", "User name must be present.");
            }

            $res = $trans->query("SELECT id FROM users WHERE name = %s", $user->getName());
        }

        if (isset($res) && $res->valid()) {
            $diag->addError("name", "user_already_exists", "User with selected name already exists.");
        }

        if ($diag->getErrors()) {
            throw $diag;
        }

        if ($user->getId()) {
            $columns = array();
            $values = array();

            $destroySession = false;

            foreach ($user->listChanged() as $column) {
                if ($column == "password") {
                    $columns[] = $column." = SHA2(CONCAT(salt, %s), 256)";
                    $values[] = $user->$column;
                } else {
                    $columns[] = $column." = %s";
                    $values[] = $user->$column;

                    if ($column == "status_id" && $user->$column == \models\User::STATUS_BANNED) {
                        $destroySession = true;
                    }
                }
            }

            $values[] = $user->getId();

            if (count($columns) > 0) {
                $trans->query("UPDATE users SET ".implode(", ", $columns)." WHERE id = %s", $values);
            }

            if ($destroySession) {
                $trans->query("DELETE FROM sessions WHERE name = 'UserId' AND value = %s", $user->getId());
            }
        } else {
            $columns = array();
            $values = array();
            $strings = array();

            if (function_exists("mcrypt_create_iv")) {
                $salt = \mcrypt_create_iv(64, MCRYPT_DEV_URANDOM);
            } else {
                $salt = "";
                for ($i = 0; $i < 64; ++$i) {
                    $salt += chr(mt_rand(0, 255));
                }
            }

            foreach ($user->listChanged() as $column) {
                $columns[] = $column;
                $strings[] = "%s";

                if ($column == "password") {
                    $values[] = \hash("sha256", $salt.$user->$column);
                } else {
                    $values[] = $user->$column;
                }
            }

            $columns[] = "salt";
            $strings[] = "%s";
            $values[] = $salt;

            $columns[] = "registered";
            $strings[] = "NOW()";

            $trans->query("INSERT INTO users (".implode(", ", $columns).") VALUES (".implode(", ", $strings).")", $values);
        }

        $trans->commit();
    }

    public function listUsers(\models\Group $inGroup = NULL, $additionalColumns = array(), $trans = NULL) {
        $transactionStarted = false;
        if (is_null($trans)) {
            $trans = $this->base->db->beginRO();
            $transactionStarted = true;
        }

        $vals = array();
        $query = "SELECT u.id, u.name";

        $getSessionInfo = false;
        if (in_array("logged_in", $additionalColumns)) {
            unset($additionalColumns[array_search("logged_in", $additionalColumns)]);
            $getSessionInfo = true;
        }

        if (!empty($additionalColumns)) {
            $query .= ", ".implode(", ", $additionalColumns);
        }

        if ($getSessionInfo) {
            $query .= ", IF(s.id IS NOT NULL, 1, 0) AS logged_in";
        }

        $query .= " FROM users u";

        if ($getSessionInfo) {
            $query .= " LEFT JOIN sessions s ON (s.name = 'UserId' AND s.value = u.id AND s.activity > DATE_ADD(NOW(), INTERVAL -15 MINUTE))";
        }

        if (!is_null($inGroup)) {
            $query .= " JOIN user_group ug ON ug.user_id = u.id AND ug.group_id = %s";
            $vals[] = $inGroup->getId();
        }

        $query .= " WHERE u.id > 0";

        if ($getSessionInfo) {
            $query .= " GROUP BY u.id";
        }

        $query .= " ORDER BY u.name ASC";

        $res = $trans->query($query, $vals);
        $res->setClassFactory("\\models\\User");

        $out = array();
        foreach ($res as $row) {
            $out[] = $row;
        }

        if ($transactionStarted) {
            $trans->commit();
        }

        return $out;
    }

    public function listGroups(\models\User $ofUser = NULL, $additionalColumns = array(), $trans = NULL) {
        $transactionStarted = false;
        if (is_null($trans)) {
            $trans = $this->base->db->beginRO();
            $transactionStarted = true;
        }

        $vals = array();

        $query = "SELECT g.id, g.name";

        if (in_array("userCount", $additionalColumns)) {
            $query .= ", COUNT(ugc.user_id) AS userCount FROM groups g LEFT JOIN user_group ugc ON (ugc.group_id = g.id)";
        } else {
            $query .= " FROM groups g";
        }

        if (!is_null($ofUser)) {
            $query .= " JOIN user_group ug ON (g.id = ug.group_id AND ug.user_id = %s)";
            $vals[] = $ofUser->getId();
        }

        if (in_array("userCount", $additionalColumns)) {
            $query .= " GROUP BY ugc.group_id";
        }

        $query .= " ORDER BY g.name ASC";

        $res = $trans->query($query, $vals);
        $res->setClassFactory("\\models\\Group");

        $out = array();
        foreach ($res as $row) {
            $out[] = $row;
        }

        if ($transactionStarted) {
            $trans->commit();
        }

        return $out;
    }

    public function loadUserPrivileges(\models\User $user) {
        $transactionStarted = false;
        if (is_null($this->base->currentTransaction)) {
            $trans = $this->base->db->beginRO();
            $transactionStarted = true;
        } else {
            $trans = $this->base->currentTransaction;
        }

        $res = $trans->query("SELECT
                    sp.id,
                    NULL AS user_id,
                    NULL AS group_id,
                    sp.name,
                    sp.default_value AS value
                FROM system_privileges sp
            UNION
                SELECT
                    pg.id,
                    NULL AS user_id,
                    pg.group_id,
                    sp.name,
                    pg.value
                FROM user_group ug
                JOIN system_privileges_group pg ON pg.group_id = ug.group_id
                JOIN system_privileges sp ON sp.id = pg.privilege_id
                WHERE ug.user_id = %s
            UNION
                SELECT
                    pu.id,
                    pu.user_id,
                    NULL as group_id,
                    sp.name,
                    pu.value
                FROM system_privileges_user pu
                JOIN system_privileges sp ON sp.id = pu.privilege_id
                WHERE user_id = %s", $user->getId(), $user->getId());

        $map = array();

        foreach ($res as $row) {
            if (is_null($row->user_id) && is_null($row->group_id)) {
                $priv = new \models\SystemPrivilege;
            } elseif (!is_null($row->group_id)) {
                $priv = new \models\GroupSystemPrivilege;
                $priv->group_id = $row->group_id;
            } elseif (!is_null($row->user_id)) {
                $priv = new \models\UserSystemPrivilege;
                $priv->user_id = $row->user_id;
            }

            $priv->id = $row->id;
            $priv->name = $row->name;
            $priv->value = $row->value;

            if ($priv->value == "1") $priv->value = true;
            elseif ($priv->value == "0") $priv->value = false;

            $map[$row->name] = $priv;
        }

        if ($transactionStarted) {
            $trans->commit();
        }

        return $map;
    }

    public function listDefaultPrivileges() {
        $trans = $this->base->db->beginRO();

        $res = $trans->query("SELECT
            sp.id,
            sp.name,
            sp.default_value AS value
            FROM system_privileges sp
            ORDER BY sp.name ASC");
        $res->setClassFactory("\\models\\SystemPrivilege");

        $out = array();
        foreach ($res as $row) {
            if ($row->value == "1") $row->value = true;
            elseif ($row->value == "0") $row->value = false;

            $out[] = $row;
        }

        $trans->commit();

        return $out;
    }

    public function listUserPrivileges(\models\User $user) {
        $trans = $this->base->db->beginRO();

        $res = $trans->query("SELECT
                pu.id,
                sp.name,
                sp.id AS privilege_id,
                pu.value
            FROM system_privileges sp
            LEFT JOIN system_privileges_user pu ON (pu.privilege_id = sp.id AND pu.user_id = %s)
            ORDER BY sp.name ASC", $user->getId());
        $res->setClassFactory("\\models\\UserSystemPrivilege");

        $out = array();
        foreach ($res as $row) {
            $row->user_id = $user->getId();

            if ($row->value == "1") $row->value = true;
            elseif ($row->value == "0") $row->value = false;

            $out[] = $row;
        }

        $trans->commit();

        return $out;
    }

    public function listGroupPrivileges(\models\Group $group) {
        $trans = $this->base->db->beginRO();

        $res = $trans->query("SELECT
                pg.id,
                sp.name,
                sp.id AS privilege_id,
                pg.value
            FROM system_privileges sp
            LEFT JOIN system_privileges_group pg ON (pg.privilege_id = sp.id AND pg.group_id = %s)
            ORDER BY sp.name ASC", $group->getId());
        $res->setClassFactory("\\models\\GroupSystemPrivilege");

        $out = array();
        foreach ($res as $row) {
            $row->group_id = $group->getId();

            if ($row->value == "1") $row->value = true;
            elseif ($row->value == "0") $row->value = false;

            $out[] = $row;
        }

        $trans->commit();

        return $out;
    }

    public function storeDefaultPrivileges($privs) {
        $trans = $this->base->db->beginRW();

        foreach ($privs as $priv) {
            if (!is_null($priv->id)) {
                $trans->query("UPDATE system_privileges SET default_value = %s WHERE id = %s", $priv->value, $priv->id);
            }
        }

        $trans->commit();
    }

    public function storeUserPrivileges($privs) {
        $trans = $this->base->db->beginRW();

        foreach ($privs as $priv) {
            if (!is_null($priv->user_id) && !is_null($priv->privilege_id)) {
                if (is_null($priv->value)) {
                    $trans->query("DELETE FROM system_privileges_user
                        WHERE user_id = %s AND privilege_id = %s", $priv->user_id, $priv->privilege_id);
                } else {
                    $trans->query("INSERT INTO system_privileges_user (user_id, privilege_id, value)
                        VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE value = VALUES(value)",
                        $priv->user_id, $priv->privilege_id, $priv->value);
                }
            }
        }

        $trans->commit();
    }

    public function storeGroupPrivileges($privs) {
        $trans = $this->base->db->beginRW();

        foreach ($privs as $priv) {
            if (!is_null($priv->group_id) && !is_null($priv->privilege_id)) {
                if (is_null($priv->value)) {
                    $trans->query("DELETE FROM system_privileges_group
                        WHERE group_id = %s AND privilege_id = %s", $priv->group_id, $priv->privilege_id);
                } else {
                    $trans->query("INSERT INTO system_privileges_group (group_id, privilege_id, value)
                        VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE value = VALUES(value)",
                        $priv->group_id, $priv->privilege_id, $priv->value);
                }
            }
        }

        $trans->commit();
    }

    public function loadGroupInfo($groupId) {
        $trans = $this->base->db->beginRO();
        $res = $trans->query("SELECT id, name FROM groups WHERE id = %s", $groupId)->fetch("\\models\\Group");
        $trans->commit();

        return $res;
    }

    public function storeGroupInfo(\models\Group $group) {
        $trans = $this->base->db->beginRW();

        $diag = new Diagnostics();

        if ($group->getId()) {
            if ($group->isChanged("name")) {
                $res = $trans->query("SELECT id FROM groups WHERE name = %s AND id <> %s", $group->getName(), $group->getId());
            }
        } else {
            if (empty($group->getName())) {
                $diag->addError("name", "name_must_be_present", "Group name must be present.");
            } else {
                $res = $trans->query("SELECT id FROM groups WHERE name = %s", $group->getName());
            }
        }

        if ($res && $res->valid()) {
            $diag->addError("name", "name_already_exists", "Group with given name already exists.");
        }

        if ($diag->getErrors()) {
            throw $diag;
        }

        if (is_null($group->getId())) {
            $trans->query("INSERT INTO groups (name) VALUES (%s)", $group->getName());
            $group->setId($trans->lastInsertId());
        } else {
            if ($group->isChanged("name")) {
                $trans->query("UPDATE groups SET name = %s WHERE id = %s", $group->getName(), $group->getId());
            }
        }

        $trans->commit();
    }

    public function addUserToGroup(\models\User $user, \models\Group $group) {
        if (!is_null($user->getId()) && $user->getId() > 0 && !is_null($group->getId()) && $group->getId() > 0) {
            $trans = $this->base->db->beginRW();
            $trans->query("INSERT IGNORE INTO user_group (user_id, group_id) VALUES (%s, %s)", $user->getId(), $group->getId());
            $trans->commit();
        }
    }

    public function removeUserFromGroup(\models\User $user, \models\Group $group) {
        $trans = $this->base->db->beginRW();
        $trans->query("DELETE FROM user_group WHERE user_id = %s AND group_id = %s", $user->getId(), $group->getId());
        $trans->commit();
    }

    public function removeGroup(\models\Group $group) {
        $trans = $this->base->db->beginRW();
        $trans->query("DELETE FROM groups WHERE id = %s", $group->getId());
        $trans->commit();
    }

    public function listUsersOfPrivilege(\models\SystemPrivilege $privilege) {
        $trans = $this->base->db->beginRO();

        $res = $trans->query("SELECT
                id AS privilege_id,
                NULL AS user_id,
                NULL AS group_id,
                sp.default_value AS value
            FROM system_privileges sp WHERE id = %s

            UNION

            SELECT
                spg.privilege_id AS privilege_id,
                ug.user_id,
                ug.group_id,
                spg.value
            FROM user_group ug
            JOIN system_privileges_group spg ON (spg.group_id = ug.group_id AND spg.privilege_id = %s)

            UNION

            SELECT
                spu.privilege_id AS privilege_id,
                spu.user_id,
                NULL AS group_id,
                spu.value AS value
            FROM system_privileges_user spu WHERE spu.privilege_id = %s",
            $privilege->getId(), $privilege->getId(), $privilege->getId());

        $map = array();

        $groupsToFetch = array();

        foreach ($res as $row) {
            if (is_null($row->user_id) && is_null($row->group_id)) {
                $priv = new \models\SystemPrivilege;
                $priv->setId($row->privilege_id);
                $priv->setValue($row->value);
            } elseif (!is_null($row->group_id)) {
                $priv = new \models\GroupSystemPrivilege;
                $priv->setPrivilege_id($row->privilege_id);
                $priv->setGroup_id($row->group_id);
                $priv->setValue($row->value);

                $groupsToFetch[] = $row->group_id;
            } else {
                $priv = new \models\UserSystemPrivilege;
                $priv->setPrivilege_id($row->privilege_id);
                $priv->setUser_id($row->user_id);
                $priv->setValue($row->value);
            }

            if (is_null($row->user_id)) {
                $map[NULL] = $priv;
            } else {
                $map[$row->user_id] = $priv;
            }
        }

        $groupsMap = array();
        if (!empty($groupsToFetch)) {
            $strings = array();
            $values = array();

            foreach ($groupsToFetch as $gId) {
                $strings[] = "%s";
                $values[] = $gId;
            }
            
            $res = $trans->query("SELECT id, name FROM groups WHERE id IN (".implode(", ", $strings).")", $values);
            $res->setClassFactory("\\models\\Group");
            foreach ($res as $row) {
                $groupsMap[$row->id] = $row;
            }
        }

        $out = array();
        if (!empty($map)) {
            $strings = array();
            $values = array();

            foreach ($map as $key=>$val) {
                if (!is_null($key)) {
                    $strings[] = "%s";
                    $values[] = $key;
                }
            }

            $res = $trans->query("SELECT id, name FROM users WHERE id IN (".implode(", ", $strings).") ORDER BY name ASC", $values);
            $res->setClassFactory("\\models\\UserAppliedPrivilege");
            foreach ($res as $row) {
                if ($row->id == 0) {
                    $priv = $map[NULL];
                } else {
                    $priv = $map[$row->id];
                }

                $row->priv_source = $priv;

                if ($priv instanceof \models\GroupSystemPrivilege) {
                    $priv->group = $groupsMap[$priv->group_id];
                }

                $out[] = $row;
            }
        }

        $trans->commit();

        return array($map[NULL], $out);
    }
}