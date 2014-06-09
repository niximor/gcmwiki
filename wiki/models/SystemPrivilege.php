<?php

namespace models;

require_once "models/Model.php";

class SystemPrivilege extends Model {
    protected $id;
    protected $name;
    protected $value;
}

class UserSystemPrivilege extends SystemPrivilege {
    protected $user_id;
    protected $privilege_id;
}

class GroupSystemPrivilege extends SystemPrivilege {
    protected $group_id;
    protected $privilege_id;

    protected $group;
}

