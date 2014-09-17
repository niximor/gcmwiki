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
<h1>List of pages that links to <?php echo $pp($Page); ?></h1>

<ul>
<?php foreach ($References->pages as $ref) { ?>
    <li><a href="<?php echo $this->url($ref->getFullUrl()); ?>"><?php echo $pp($ref); ?></a></li>
<?php } ?>
</ul>