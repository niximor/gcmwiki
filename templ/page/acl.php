<h1><?php echo $Page->getName(); ?></h1>

<form action="<?php echo $this->url($this->getSelf()); ?>?saveAcl" method="post">

<?php

function select($name, $value) {
	echo "<select name=\"".$name."\">";
	echo "<option value=\"1\"".(($value === true)?" selected=\"selected\"":"").">Yes</option>";
	echo "<option value=\"0\"".(($value === false)?" selected=\"selected\"":"").">No</option>";
	echo "<option value=\"-1\"".((is_null($value))?" selected=\"selected\"":"").">Default</option>";
	echo "</select>";
}

?>

<table>
	<thead>
		<tr>
			<th>User / Group</th>
			<th>Read</th>
			<th>Write</th>
			<th>Admin</th>
			<th>Comment read</th>
			<th>Comment write</th>
		</tr>
	</thead>

	<tbody>
		<tr>
			<td>Default ACL</td>
			<td><?php select("default[read]", $PageAcls->default->page_read); ?></td>
			<td><?php select("default[write]", $PageAcls->default->page_write); ?></td>
			<td><?php select("default[admin]", $PageAcls->default->page_admin); ?></td>
			<td><?php select("default[comment_read]", $PageAcls->default->comment_read); ?></td>
			<td><?php select("default[comment_write]", $PageAcls->default->comment_write); ?></td>
		</tr>
	</tbody>

<?php
	if (!empty($Groups)) {
?>
	<tbody id="groups">
		<tr>
			<th colspan="6"><h2>Groups</h2></th>
		</tr>
<?php
	foreach ($PageAcls->groups as $acl) {
?>
		<tr>
			<td><?php echo $acl->name; ?></td>
			<td><?php select("group[".$acl->id."][read]", $acl->page_read); ?></td>
			<td><?php select("group[".$acl->id."][write]", $acl->page_write); ?></td>
			<td><?php select("group[".$acl->id."][admin]", $acl->page_admin); ?></td>
			<td><?php select("group[".$acl->id."][comment_read]", $acl->comment_read); ?></td>
			<td><?php select("group[".$acl->id."][comment_write]", $acl->comment_write); ?></td>
		</tr>
<?php
	}
?>
		<tr>
			<td colspan="6">
				Add group:
				<select name="groupId">
<?php
	foreach ($Groups as $group) {
		echo "<option value=\"".$group->id."\">".htmlspecialchars($group->name)."</option>";
	}
?>
				</select>
				<input type="button" value="Add" id="btnAddGroup" />
			</td>
		</tr>
	</tbody>
<?php
	}
?>

	<tbody id="users">
		<tr>
			<th colspan="6"><h2>Users</h2></th>
		</tr>
<?php
	foreach ($PageAcls->users as $acl) {
?>
		<tr>
			<td><?php echo $acl->name; ?></td>
			<td><?php select("user[".$acl->id."][read]", $acl->page_read); ?></td>
			<td><?php select("user[".$acl->id."][write]", $acl->page_write); ?></td>
			<td><?php select("user[".$acl->id."][admin]", $acl->page_admin); ?></td>
			<td><?php select("user[".$acl->id."][comment_read]", $acl->comment_read); ?></td>
			<td><?php select("user[".$acl->id."][comment_write]", $acl->comment_write); ?></td>
		</tr>
<?php
	}
?>
		<tr>
			<td colspan="6">
				Add user:
				<select name="userId">
<?php
	foreach ($Users as $user) {
		echo "<option value=\"".$user->id."\">".htmlspecialchars($user->name)."</option>";
	}
?>
				</select>
				<input type="button" value="Add" id="btnAddUser" />
			</td>
		</tr>
	</tbody>
</table>
<div>
	<input type="submit" value="Save" />
</div>
</form>

<script type="text/javascript">
var btnUser = document.getElementById("btnAddUser");
var btnGroup = document.getElementById("btnAddGroup");

function createTd(name, acl) {
	var td = document.createElement("td");

	var select = document.createElement("select");
	select.name = name + "[" + acl + "]";

	yes = document.createElement("option");
	yes.value = "1";
	yes.text = "Yes";

	no = document.createElement("option");
	no.value = "0";
	no.text = "No";

	na = document.createElement("option");
	na.value = "-1";
	na.text = "N/A";

	select.appendChild(yes);
	select.appendChild(no);
	select.appendChild(na);

	select.selectedIndex = 2;

	td.appendChild(select);

	return td;
}

function addRow(parent, name, label) {
	var tds = [];
	tds.push(createTd(name, "read"));
	tds.push(createTd(name, "write"));
	tds.push(createTd(name, "admin"));
	tds.push(createTd(name, "comment_read"));
	tds.push(createTd(name, "comment_write"));

	var trs = parent.getElementsByTagName("tr");

	var tdLabel = document.createElement("td");
	tdLabel.innerHTML = label;
	
	var tr = document.createElement("tr");
	tr.appendChild(tdLabel);

	for (var i in tds) {
		tr.appendChild(tds[i]);
	}

	parent.insertBefore(tr, trs[trs.length - 1]);
}

btnUser.onclick = function() {
	option = this.form.userId.selectedOptions;

	if (option.length > 0) {
		addRow(document.getElementById("users"), "user[" + option[0].value.toString() + "]", option[0].text);
	}
}

btnGroup.onclick = function() {
	option = this.form.groupId.selectedOptions;

	if (option.length > 0) {	
		addRow(document.getElementById("groups"), "group[" + option[0].value.toString() + "]", option[0].text);
	}
}

</script>