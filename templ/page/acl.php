<?php

$pp = function($page) use (&$pp) {
    if (is_null($page)) return;

    $parent = $pp($page->getParent());
    if (!empty($parent)) {
        return $parent." / ".htmlspecialchars($page->getName());
    } else {
        return htmlspecialchars($page->getName());
    }
};

?>
<h1>ACLs of page <?php echo $pp($Page); ?></h1>

<form action="<?php echo $this->url($this->getSelf()); ?>?saveAcl" method="post">

<?php

function select($name, $value) {
	echo "<select name=\"".$name."\" class=\"checkbox\">";
	echo "<option value=\"1\"".(($value === true)?" selected=\"selected\"":"").">Yes</option>";
	echo "<option value=\"0\"".(($value === false)?" selected=\"selected\"":"").">No</option>";
	echo "<option value=\"-1\"".((is_null($value))?" selected=\"selected\"":"").">Default</option>";
	echo "</select>";
}

$names = array(
	"page_read" => "Read",
	"page_write" => "Write",
	"page_admin" => "Admin",
	"comment_read" => "Comment read",
	"comment_write" => "Comment write",
	"comment_admin" => "Comment admin",
	"attachment_write" => "Attach files",
);

?>
<div>
	<table class="nofull">
		<thead>
			<tr>
				<th class="acl_name">User / Group</th>
				<?php foreach ($Acls as $name) { ?>
					<th class="acl <?php echo $name; ?>"><?php echo isset($names[$name])?$names[$name]:$name; ?></th>
				<?php } ?>
			</tr>
		</thead>

		<tbody>
			<tr>
				<td class="acl_name">Default ACL</td>
				<?php foreach ($Acls as $name) { ?>
				<td class="acl <?php echo $name; ?>"><?php select("default[".$name."]", $PageAcls->default->$name); ?></td>
				<?php } ?>
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
	$usedGroups = array();
	foreach ($PageAcls->groups as $acl) {
		$usedGroups[$acl->id] = true;
?>
			<tr>
				<td class="acl_name"><?php echo $acl->name; ?></td>
				<?php foreach ($Acls as $name) { ?>
				<td class="acl <?php echo $name; ?>"><?php select("group[".$acl->id."][".$name."]", $acl->$name); ?></td>
				<?php } ?>
			</tr>
<?php
	}
?>
			<tr class="noborder">
				<th>Add group:</th>
				<td colspan="5">
					<select name="groupId" class="acl_select">
<?php
	foreach ($Groups as $group) {
		if (!isset($usedGroups[$group->id])) {
			echo "<option value=\"".$group->id."\">".htmlspecialchars($group->name)."</option>";
		}
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
	$usedUsers = array();
	foreach ($PageAcls->users as $acl) {
		$usedUsers[$acl->id] = true;
?>
			<tr>
				<td class="acl_name"><?php echo $acl->name; ?></td>
				<?php foreach ($Acls as $name) { ?>
				<td class="acl <?php echo $name; ?>"><?php select("user[".$acl->id."][".$name."]", $acl->$name); ?></td>
				<?php } ?>
			</tr>
<?php
	}
?>
			<tr class="noborder">
				<th>Add user:</th>
				<td colspan="5">
					<select name="userId" class="acl_select">
<?php
	foreach ($Users as $user) {
		if (!isset($usedUsers[$user->id])) {
			echo "<option value=\"".$user->id."\">".htmlspecialchars($user->name)."</option>";
		}
	}
?>
					</select>
					<input type="button" value="Add" id="btnAddUser" />
				</td>
			</tr>
		</tbody>
	</table>
</div>

	<div class="buttons">
		<input type="submit" value="Save" /> <a href="<?php echo $this->url($this->getSelf()); ?>">Cancel</a>
	</div>
</form>

<script type="text/javascript">
var btnUser = document.getElementById("btnAddUser");
var btnGroup = document.getElementById("btnAddGroup");

function createTd(name, acl) {
	var td = document.createElement("td");
	td.className = "acl " + acl;

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
	installCheckbox(select);

	return td;
}

function addRow(parent, name, label) {
	var tds = [];
	<?php foreach ($Acls as $name) { ?>
	tds.push(createTd(name, "<?php echo addcslashes($name, "\r\n\t\""); ?>"));
	<?php } ?>

	var trs = parent.getElementsByTagName("tr");

	var tdLabel = document.createElement("td");
	tdLabel.className = "acl_name";
	tdLabel.innerHTML = label;

	var tr = document.createElement("tr");
	tr.appendChild(tdLabel);

	for (var i in tds) {
		tr.appendChild(tds[i]);
	}

	parent.insertBefore(tr, trs[trs.length - 1]);
}

btnUser.onclick = function() {
    option = this.form.userId.options[this.form.userId.selectedIndex];
	addRow(document.getElementById("users"), "user[" + option.value.toString() + "]", option.text);
}

btnGroup.onclick = function() {
    option = this.form.groupId.options[this.form.groupId.selectedIndex];
	addRow(document.getElementById("groups"), "group[" + option.value.toString() + "]", option.text);
}

</script>
