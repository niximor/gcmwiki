<h1><?php printf("Users who has privilege %s", htmlspecialchars($Privilege->getName())); ?></h1>

<table>
    <thead>
        <tr>
            <th>User name</th>
            <th>Source of privilege</th>
        </tr>
    </thead>

    <tbody>
<?php
    foreach ($Users as $u) {
?>
        <tr>
            <td>
                <?php if ($u->getId() > 0) { ?>
                <a href="<?php echo $this->url("/wiki:user/".$u->getName()); ?>"><?php echo htmlspecialchars($u->getName()); ?></a>
                <?php } else { echo htmlspecialchars($u->getName()); } /* anonymous user */ ?>
            </td>
            <td><?php
                $source = $u->priv_source;
                if ($source instanceof \models\UserSystemPrivilege) {
                    echo "<a href=\"".$this->url("/wiki:users?privileges=".$source->user_id)."\">User specific</a>";
                } elseif ($source instanceof \models\GroupSystemPrivilege) {
                    echo "<a href=\"".$this->url("/wiki:groups?privileges=".$source->group_id)."\">From group ".$source->group->getName()."</a>";
                } else {
                    echo "<a href=\"".$this->url("/wiki:config")."\">System default</a>";
                } ?>
            </td>
        </tr>
<?php
    }
?>
    </tbody>
</table>