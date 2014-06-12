<ul>
<?php foreach ($Comments as $Comment) { ?>
    <li>
        <?php echo $Comment->OwnerUser->profileLink($this); ?> :: <?php echo $Comment->created; ?>

        <div>
            <?php echo $Comment->getText_html(); ?>
        </div>

        <a href="<?php echo $this->url($this->getSelf()."/comment:add?parent=".$Comment->getId()); ?>">Reply</a>

<?php
    if (!empty($Comment->childs)) {
        $t = new \view\Template("comments/show.php", $this);
        $t->addVariable("Comments", $Comment->childs);
        $t->render();
    }
?>
    </li>
<?php } ?>
</ul>