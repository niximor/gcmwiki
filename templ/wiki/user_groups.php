<h1>Groups <?php echo htmlspecialchars($User->name); ?> is member of</h1>

<form action="<?php echo $this->url($this->getSelf()); ?>" method="post">
	<div>
		<table>
			<tbody>
<?php
	$AlreadyMember = array();
	foreach ($UserGroups as $Group) {
		$AlreadyMember[$Group->id] = true;
?>
				<tr>
					<td><?php echo htmlspecialchars($Group->name); ?></td>
					<td><input type="submit" value="Remove" name="remove[<?php echo $Group->id; ?>]" /></td>
				</tr>
<?php
	}
?>
			</tbody>
		</table>
	</div>
	<div class="nogrid">
		<label for="groupId">Add user to group:</label>
		<select name="groupId" id="groupIdSelect">
			<option value="0">Create new group</option>
			<?php foreach ($Groups as $Group) { if (!isset($AlreadyMember[$Group->id])) echo "<option value=\"".$Group->id."\">".htmlspecialchars($Group->name)."</option>"; } ?>
		</select>
		<input type="text" name="groupName" />
		<input type="submit" name="add" value="Add" />
	</div>
</form>

<script type="text/javascript">
document.getElementById("groupIdSelect").onchange = function() {
	if (this.selectedOptions[0].value == "0") {
		this.form.groupName.style.display = "";
	} else {
		this.form.groupName.style.display = "none";
	}
}
</script>