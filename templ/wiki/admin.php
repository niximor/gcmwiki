<h1>Administration</h1>

<ul>
<?php
    foreach ($Modules as $Module) {
?>
    <li><a href="<?php echo htmlspecialchars($this->url($Module->url)); ?>"><?php echo htmlspecialchars($Module->name); ?></a></li>
<?php
    }
?>
</ul>