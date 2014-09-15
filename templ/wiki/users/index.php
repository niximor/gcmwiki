<h1>Users</h1>

<table>
	<thead>
		<tr>
			<th>Username</th>
			<th>Registration date</th>
			<th>Last login date</th>
			<th></th>
		</tr>
	</thead>

	<tbody>
<?php
	foreach ($Users as $User) {
?>
		<tr>
			<td>
				<a href="<?php echo $this->url("/wiki:user/".$User->name); ?>"><?php echo htmlspecialchars($User->name); ?></a>
				<?php if ($User->logged_in) echo "*"; ?>
			</td>
			<td><?php echo $User->registered; ?></td>
			<td><?php if (!is_null($User->last_login)) echo $User->last_login; else echo "Never"; ?></td>
			<td>
				<?php if ($User->status_id == \models\User::STATUS_LIVE) { ?>
				<a href="<?php echo $this->url($this->getSelf()."?ban=".$User->getId()); ?>">Block</a>
				<?php } elseif ($User->status_id == \models\User::STATUS_BANNED) { ?>
				<a href="<?php echo $this->url($this->getSelf()."?unban=".$User->getId()); ?>">Unblock</a>
				<?php } ?>
				<?php if ($CurrentUser && $CurrentUser->hasPriv("admin_user_privileges")) { ?>
				<a href="<?php echo $this->url($this->getSelf()."?privileges=".$User->getId()); ?>">Privileges</a>
				<?php } ?>
			</td>
		</tr>
<?php
	}
?>
	</tbody>
</table>

*) User is currently logged in.