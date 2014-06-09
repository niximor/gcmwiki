<h1>Users in group <?php echo htmlspecialchars($Group->name); ?></h1>

<table>
	<tbody>
<?php
	$AlreadyInGroup = array();
	foreach ($UsersInGroup as $User) {
		$AlreadyInGroup[$User->getId()] = true;
?>
		<tr>
			<td><?php echo htmlspecialchars($User->name); ?></td>
			<td><a href="<?php echo $this->url($this->getSelf()."?listUsers=".$Group->getId()."&amp;remove=".$User->id); ?>">Remove</a></td>
		</tr>
<?php
	}
?>
	</tbody>

	<tfoot>
		<tr>
			<td colspan="2">
				<form action="<?php echo $this->url($this->getSelf()); ?>" method="get">
					<input type="hidden" name="listUsers" value="<?php echo $Group->getId(); ?>" />

					<label for="add">Add user:</label>
					<select name="add">
						<?php
							foreach ($Users as $User) {
								if (isset($AlreadyInGroup[$User->getId()])) {
									continue;
								}
								echo "<option value=\"".$User->getId()."\">".htmlspecialchars($User->getName())."</option>";
							}
						?>
					</select>
					<input type="submit" value="Add" />
				</form>
			</td>
		</tr>
	</tfoot>
</table>