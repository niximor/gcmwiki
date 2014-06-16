<h1>Change password</h1>

<form action="<?php echo $this->url($this->getSelf()); ?>" method="post">
    <div>
        <label for="oldpassword">Old password:</label>
        <input type="password" name="oldpassword" />
    </div>

    <div>
        <label for="password">New password:</label>
        <input type="password" name="password" />
    </div>

    <div>
        <label for="password2">Retype new password:</label>
        <input type="password" name="password2" />
    </div>

    <div class="buttons">
        <input type="submit" value="Change password" />
    </div>
</form>