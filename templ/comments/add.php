<h1>Add comment</h1>

<form action="<?php echo $this->url($this->getSelf()); ?>" method="post">
<?php
	if (isset($Comment)) {
?>
	<input type="hidden" name="parent" value="<?php echo $Comment->id; ?>" />
	<div class="comment">
		<div class="header">
			<strong>In reply to:</strong>
			<?php echo $Comment->OwnerUser->profileLink($this); ?> :: <?php echo $Comment->created; ?>
		</div>
    	<div class="body wiki">
	        <?php echo $Comment->getText_html(); ?>
	    </div>
	</div>
<?php
	}

	if (!\lib\CurrentUser::isLoggedIn()) {
?>
	<div class="nogrid">
		<label for="username">User name:</label>
		<input type="text" name="username" value="<?php if (isset($Form["username"]) && !empty($Form["username"])) echo htmlspecialchars($Form["username"]); ?>" />
		Note: Your IP address will be logged together with username.
		<?php if (isset($Errors["anonymous_name"])) { echo "<ul>"; foreach ($Errors["anonymous_name"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
	</div>
<?php
	}
?>
	<div class="textarea">
		<label for="text">Text:</label>
		<div><textarea name="text"><?php if (isset($Form["text"]) && !empty($Form["text"])) echo htmlspecialchars($Form["text"]); ?></textarea></div>
		<?php if (isset($Errors["text_wiki"])) { echo "<ul>"; foreach ($Errors["text_wiki"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
	</div>

	<div class="buttons">
		<input type="submit" value="Post" />
		<a href="<?php echo $this->url($this->getSelf(), -1); ?>">Cancel</a>
	</div>
</form>