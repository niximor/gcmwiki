<h1><?php echo $Page->getName(); ?></h1>

<form action="<?php echo $this->url($this->getSelf()); ?>?save" method="post">
	<div>
		<label for="name">Name:</label>
		<input type="text" name="name" value="<?php if (isset($Form["name"])) echo htmlspecialchars($Form["name"]); else echo htmlspecialchars($Page->getName()); ?>" />
		<?php if (isset($Errors["name"])) { echo "<ul>"; foreach ($Errors["name"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
	</div>

	<div>
		<textarea name="body"><?php if (isset($Form["body"])) echo htmlspecialchars($Form["body"]); else echo htmlspecialchars($Page->getBody_wiki()); ?></textarea>
		<?php if (isset($Errors["body"])) { echo "<ul>"; foreach ($Errors["body"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
	</div>

	<div>
		<label><input type="checkbox" name="small_change"<?php if (isset($Form["small_change"])) echo " checked=\"checked\""; ?> /> Small change</label>
	</div>

	<div>
		<label for="summary">Summary:</label>
		<input type="text" name="summary" value="<?php if (isset($Form["summary"])) echo htmlspecialchars($Form["summary"]); ?>" />
		<?php if (isset($Errors["summary"])) { echo "<ul>"; foreach ($Errors["summary"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
	</div>

	<div>
		<input type="submit" value="Save" />
	</div>
</form>