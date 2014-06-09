<h1>Privileges of user <?php echo htmlspecialchars($User->name); ?></h1>

<form action="<?php echo $this->url($this->getSelf()); ?>" method="post">
<table>
	<thead>
		<tr>
			<th>Privilege</th>
			<th>Value</th>
		</tr>
	</thead>

	<tbody>
<?php
	foreach ($UserPrivileges as $priv) {
?>
		<tr>
			<td><?php echo htmlspecialchars($priv->name); ?></td>
			<td>
				<select name="privilege[<?php echo $priv->privilege_id; ?>]">
					<option value="1"<?php if ($priv->value === true) echo " selected=\"selected\""; ?>>Yes</option>
					<option value="0"<?php if ($priv->value === false) echo " selected=\"selected\""; ?>>No</option>
					<option value="-1"<?php if (is_null($priv->value)) echo " selected=\"selected\""; ?>>Default</option>
				</select>
			</td>
		</tr>
<?php
	}
?>
	</tbody>
	<tfoot>
		<tr>
			<td colspan="2"><input type="submit" value="Save" /></td>
		</tr>
	</tfoot>
</table>