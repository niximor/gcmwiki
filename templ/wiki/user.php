<h1><?php echo htmlspecialchars($User->name); ?></h1>

<dl>
    <dt>Registration date:</dt>
    <dd><?php echo $User->registered; ?></dd>

    <dt>Last login:</dt>
    <dd><?php if (!is_null($User->last_login)) echo $User->last_login; else echo "Never logged in"; ?></dd>

<?php if ($CurrentUser && $CurrentUser->hasPriv("admin_user_privileges")) { ?>
    <dt>Group membership:</dt>
    <dd>
        <ul>
            <?php foreach ($UserGroups as $Group) { ?>
            <li><?php echo htmlspecialchars($Group->name); ?></li>
            <?php } ?>
        </ul>

        <?php if (empty($UserGroups)) echo "<p>User does not belong to any group.</p>"; ?>

        <a href="<?php echo $this->url("/wiki:user_groups/".$User->name); ?>">Manage</a>
    </dd>

    <dt>Privileges:</dt>
    <dd>
        <table>
            <thead>
                <tr>
                    <th>Privilege</th>
                    <th>Value</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($AppliedPrivileges as $priv) { ?>
                <tr>
                    <td><?php echo $priv->name; ?></td>
                    <td><?php echo ($priv->value)?"Yes":"No"; ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <a href="<?php echo $this->url("/wiki:users/?privileges=".$User->getId()); ?>">Manage</a>
    </dd>
<?php } ?>
</dl>