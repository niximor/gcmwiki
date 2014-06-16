<ul class="comments">
<?php foreach ($Comments as $Comment) { ?>
    <li class="comment">
        <div class="header">
            <?php echo $Comment->OwnerUser->profileLink($this); ?> :: <?php echo $Comment->created; ?>
        </div>

        <div class="body wiki">
            <?php echo $Comment->getText_html(); ?>
        </div>

        <div class="footer">
            <a href="<?php echo $this->url($this->getSelf()."/comment:add?parent=".$Comment->getId()); ?>">Reply</a>
        </div>
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