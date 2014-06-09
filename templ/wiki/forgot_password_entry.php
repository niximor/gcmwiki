<form action="<?php echo $this->url("/wiki:forgot_password"); ?>" method="post">
    <input type="hidden" name="key" value="<?php echo htmlspecialchars($User->password_token); ?>" />

    <div>
        <label for="password">Enter new password:</label>
        <input type="password" name="password" />
    </div>

    <div>
        <label for="password2">Retype new password:</label>
        <input type="password" name="password2" />
    </div>

    <div>
        <input type="submit" value="Change password" />
    </div>
</form>