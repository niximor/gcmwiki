<h1><?php echo htmlspecialchars($Page->getName()); ?></h1>

<form action="<?php echo $this->url($this->getSelf()); ?>" method="post">
<?php
	if (isset($Comment)) {
?>
	<input type="hidden" name="parent" value="<?php echo $Comment->id; ?>" />
	In reply to:

	<?php echo $Comment->OwnerUser->profileLink($this); ?> :: <?php echo $Comment->created; ?>
    <div>
        <?php echo $Comment->getText_html(); ?>
    </div>
<?php
	}

	if (!\lib\CurrentUser::isLoggedIn()) {
?>
	<div>
		<label for="username">User name:</label>
		<input type="text" name="username" value="<?php if (isset($Form["username"]) && !empty($Form["username"])) echo htmlspecialchars($Form["username"]); ?>" />
		Note: Your IP address will be logged together with username.
		<?php if (isset($Errors["anonymous_name"])) { echo "<ul>"; foreach ($Errors["anonymous_name"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
	</div>
<?php
	}
?>
	<div>
		<label for="text">Text:</label>
		<textarea name="text"><?php if (isset($Form["text"]) && !empty($Form["text"])) echo htmlspecialchars($Form["text"]); ?></textarea>
		<?php if (isset($Errors["text_wiki"])) { echo "<ul>"; foreach ($Errors["text_wiki"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
	</div>

	<div>
		<input type="submit" value="Post" />
	</div>
</form>