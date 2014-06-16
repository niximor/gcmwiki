<h1>Group <?php echo htmlspecialchars($Group->name); ?></h1>

<form action="<?php echo $this->url($this->getSelf()."?modify=".$Group->getId()); ?>" method="post">
    <div class="fullwidth nogrid">
        <label for="name">Group name:</label>
        <div>
            <input type="text" name="name" value="<?php echo htmlspecialchars($Group->getName()); ?>" />
        </div>
	</div>

	<div class="buttons">
		<input type="submit" value="Modify" />
	</div>
</form>