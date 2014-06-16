<h1>Edit page <?php echo htmlspecialchars($Page->getName()); ?></h1>

<form action="<?php echo $this->url($this->getSelf()); ?>?save" method="post" class="editPage">
	<div class="nogrid fullwidth">
		<label for="name">Name:</label>
		<div><input type="text" name="name" value="<?php if (isset($Form["name"])) echo htmlspecialchars($Form["name"]); else echo htmlspecialchars($Page->getName()); ?>" /></div>
		<?php if (isset($Errors["name"])) { echo "<ul>"; foreach ($Errors["name"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
	</div>

	<div class="textarea">
		<div><textarea name="body"><?php if (isset($Form["body"])) echo htmlspecialchars($Form["body"]); else echo htmlspecialchars($Page->getBody_wiki()); ?></textarea></div>
		<?php if (isset($Errors["body"])) { echo "<ul>"; foreach ($Errors["body"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
	</div>

	<div class="nogrid fullwidth">
		<label for="summary">Summary:</label>
		<div style="float: right" class="nofull">
			<label class="checkbox"><input type="checkbox" name="small_change"<?php if (isset($Form["small_change"])) echo " checked=\"checked\""; ?> /> Small change</label>
		</div>
		<div>
			<input type="text" name="summary" value="<?php if (isset($Form["summary"])) echo htmlspecialchars($Form["summary"]); ?>" />
		</div>
		<?php if (isset($Errors["summary"])) { echo "<ul>"; foreach ($Errors["summary"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
	</div>

	<div class="buttons">
		<input type="submit" value="Save" /> <a href="<?php echo $this->url($this->getSelf()); ?>">Cancel</a>
	</div>
</form>