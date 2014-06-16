<h1>Forgotten password recovery</h1>

<form action="<?php echo $this->url($this->getSelf()); ?>" method="post">
    <div class="fullwidth">
        <label for="name" class="fullwidth">Enter your username or email to recover your password:</label>
        <div><input type="text" name="name" /></div>
    </div>

    <div class="buttons">
        <input type="submit" value="Send new password" />
    </div>
</form>