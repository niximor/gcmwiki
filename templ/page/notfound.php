<?php

$pp = function($page) use (&$pp) {
    if (is_null($page)) {
        return;
    }

    $pp($page->getParent());
    echo htmlspecialchars($page->getName())." / ";
};

?>

Page <?php $pp($Parent); echo htmlspecialchars($PageName); ?> was not found. <a href="<?php echo $this->url($this->getSelf()."?edit"); ?>">Create?</a>