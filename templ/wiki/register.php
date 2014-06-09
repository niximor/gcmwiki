<form action="<?php echo $this->url("/wiki:register"); ?>" method="post">
    <div>
        <label for="username">User name:</label>
        <input type="text" name="username" />
    </div>

    <div>
        <label for="password">Password:</label>
        <input type="password" name="password" />
    </div>

    <div>
        <label for="email">Email:</label>
        <input type="text" name="email" />
    </div>

    <div>
        <input type="submit" value="Register" />
    </div>
</form>