<h1>Group <?php echo htmlspecialchars($Group->name); ?></h1>

<form action="<?php echo $this->url($this->getSelf()."?modify=".$Group->getId()); ?>" method="post">
	<div>
		<label for="name">Group name:</label>
		<input type="text" name="name" value="<?php echo htmlspecialchars($Group->getName()); ?>" />
	</div>

	<div>
		<input type="submit" value="Modify" />
	</div>
</form>