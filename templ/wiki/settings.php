<h1>User settings</h1>

<form action="<?php echo $this->url($this->getSelf()); ?>" method="post">
    <div>
        <label for="username">User name:</label>
        <input type="text" readonly="readonly" value="<?php echo htmlspecialchars($User->getName()); ?>" />
    </div>

    <div>
        <label for="email">Email:</label>
        <input type="text" name="email" value="<?php echo htmlspecialchars($User->getEmail()); ?>" />
    </div>

    <div>
        <a href="<?php echo $this->url("/wiki:change_password"); ?>">Change password</a>
    </div>

    <div>
        <input type="submit" value="Save" />
    </div>
</form>