<h1>Privileges of user <?php echo htmlspecialchars($User->name); ?></h1>

<form action="<?php echo $this->url($this->getSelf()); ?>" method="post">
	<input type="hidden" name="privileges" value="<?php echo $User->getId(); ?>" />
	<div>
		<table class="nofull">
			<thead>
				<tr>
					<th class="privilege_name">Privilege</th>
					<th class="privilege_value">Value</th>
				</tr>
			</thead>

			<tbody>
<?php
	foreach ($UserPrivileges as $priv) {
?>
				<tr>
					<td class="privilege_name"><?php echo htmlspecialchars($priv->name); ?></td>
					<td class="privilege_value">
						<select name="privilege[<?php echo $priv->privilege_id; ?>]" class="checkbox">
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
		</table>
	</div>
	<div class="buttons">
		<input type="submit" value="Save" />
	</div>
</form>