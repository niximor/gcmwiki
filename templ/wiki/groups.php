<h1>Groups</h1>

<table>
	<thead>
		<tr>
			<th>Group name</th>
			<th>User count</th>
			<th></th>
		</tr>
	</thead>

	<tbody>
<?php
	foreach ($Groups as $Group) {
?>
		<tr>
			<td><?php echo htmlspecialchars($Group->getName()); ?></td>
			<td><a href="<?php echo $this->url("/wiki:groups?listUsers=".$Group->getId()); ?>" title="Show users in this group"><?php echo $Group->userCount; ?></a></td>
			<td>
				<a href="<?php echo $this->url("/wiki:groups?modify=".$Group->getId()); ?>">Modify</a>
				<a href="<?php echo $this->url("/wiki:groups?remove=".$Group->getId()); ?>">Remove</a>
				<a href="<?php echo $this->url("/wiki:groups?privileges=".$Group->getId()); ?>">Privileges</a>
			</td>
		</tr>
<?php
	}
?>
	</tbody>
</table>

<h2>Create group</h2>
<form action="<?php echo $this->url("/wiki:groups?add"); ?>" method="post">
	<div class="fullwidth nogrid">
		<label for="name">Group name:</label>
		<div><input type="text" name="name" value="<?php if (isset($Form) && isset($Form["name"])) echo htmlspecialchars($Form["name"]); ?>" /></div>
		<?php if (isset($Errors["name"])) { echo "<ul>"; foreach ($Errors["name"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
	</div>

	<div class="buttons">
		<input type="submit" value="Create" />
	</div>
</form>