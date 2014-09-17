<?php

$pp = function($page) use (&$pp) {
    if (is_null($page)) return;

    $parent = $pp($page->getParent());
    if (!empty($parent)) {
        return $parent." / ".htmlspecialchars($page->getName());
    } else {
        return htmlspecialchars($page->getName());
    }
};

?>
<h1>History of page <?php echo $pp($Page); ?></h1>

Revision <?php echo htmlspecialchars($Revision); ?> of this page does not exists.