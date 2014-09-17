<h1>Edit comment</h1>

<form action="<?php echo $this->url($this->getSelf()); ?>?id=<?php echo $Comment->getId(); ?>" method="post">
    <div class="textarea">
        <label for="text">Text:</label>
        <div><textarea name="text"><?php if (isset($Form["text"]) && !empty($Form["text"])) echo htmlspecialchars($Form["text"]); else echo htmlspecialchars($Comment->getText_wiki()); ?></textarea></div>
        <?php if (isset($Errors["text_wiki"])) { echo "<ul>"; foreach ($Errors["text_wiki"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
    </div>

    <div class="buttons">
        <input type="submit" value="Edit" />
        <a href="<?php echo $this->url($this->getSelf(), -1); ?>">Cancel</a>
    </div>
</form>