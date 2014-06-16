<h1>Log in</h1>

<form action="<?php echo $this->url("/wiki:login"); ?>" method="post">
	<div>
		<label for="username">User name:</label>
		<input type="text" name="username" />
	</div>

	<div>
		<label for="password">Password:</label>
		<input type="password" name="password" />
	</div>

	<div class="buttons">
		<input type="submit" value="Log in" /> <a href="<?php echo $this->url("/wiki:forgot_password"); ?>">Forgot password?</a>
	</div>
</form>