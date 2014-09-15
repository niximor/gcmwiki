<h1>Group <?php echo htmlspecialchars($Group->name); ?></h1>

<form action="<?php echo $this->url($this->getSelf()."?modify=".$Group->getId()); ?>" method="post">
    <div class="fullwidth nogrid">
        <label for="name">Group name:</label>
        <div><input type="text" name="name" value="<?php if (isset($Form) && isset($Form["name"])) echo htmlspecialchars($Form["name"]); else echo htmlspecialchars($Group->getName()); ?>" /></div>
        <?php if (isset($Errors["name"])) { echo "<ul>"; foreach ($Errors["name"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
	</div>

	<div class="buttons">
		<input type="submit" value="Modify" />
	</div>
</form>