<h1>Administration</h1>

<ul class="admin">
<?php
    foreach ($Modules as $Module) {
?>
    <li class="<?php echo $Module->id; ?>"><a href="<?php echo htmlspecialchars($this->url($Module->url)); ?>"><span><?php echo htmlspecialchars($Module->name); ?></span></a></li>
<?php
    }
?>
</ul>