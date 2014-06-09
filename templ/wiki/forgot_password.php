<h1>Forgotten password recovery</h1>

<form action="<?php echo $this->url($this->getSelf()); ?>" method="post">
    <div>
        Enter your username or email to recover your password:
    </div>
    <div>
        <input type="text" name="name" />
    </div>

    <div>
        <input type="submit" value="Send new password" />
    </div>
</form>