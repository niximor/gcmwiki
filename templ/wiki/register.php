<h1>Register</h1>

<form action="<?php echo $this->url("/wiki:register"); ?>" method="post">
    <div>
        <label for="username">User name:</label>
        <input type="text" name="username" value="<?php if (isset($Form) && isset($Form["username"])) echo htmlspecialchars($Form["username"]); ?>" />
        <?php if (isset($Errors["name"])) { echo "<ul>"; foreach ($Errors["name"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
    </div>

    <div>
        <label for="password">Password:</label>
        <input type="password" name="password" />
        <?php if (isset($Errors["password"])) { echo "<ul>"; foreach ($Errors["password"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
    </div>

    <div>
        <label for="email">Email:</label>
        <input type="text" name="email" value="<?php if (isset($Form) && isset($Form["email"])) echo htmlspecialchars($Form["email"]); ?>" />
        <?php if (isset($Errors["email"])) { echo "<ul>"; foreach ($Errors["email"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
    </div>

    <div class="buttons">
        <input type="submit" value="Register" />
    </div>
</form>