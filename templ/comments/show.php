<ul class="comments">
<?php foreach ($Comments as $Comment) { ?>
    <li class="comment">
        <div class="header">
            <?php echo $Comment->OwnerUser->profileLink($this); ?> :: <?php echo $Comment->created; ?>
        </div>

        <div class="body wiki">
            <?php if ($Comment->getHidden()) { ?>
            Comment text was hidden by administrator.
            <?php } else { echo $Comment->getText_html(); } ?>
        </div>

        <div class="footer">
            <ul>
            <?php if (!is_null($Comment->EditUser)) { ?>
                <li>Last modified <?php echo $Comment->getLast_modified(); ?> by <?php echo $Comment->EditUser->profileLink($this); ?></li>
                <li><a href="<?php echo $this->url($this->getSelf()."/comment:history?id=".$Comment->getId()); ?>">History</a></li>
            <?php } ?>
            <?php if ($Acl->comment_admin || ($Acl->comment_write && $Comment->OwnerUser->getId() == \lib\CurrentUser::ID() && \lib\CurrentUser::isLoggedIn())) { ?>
                <li><a href="<?php echo $this->url($this->getSelf()."/comment:edit?id=".$Comment->getId()); ?>">Edit</a></li>
                <li><a href="<?php echo $this->url($this->getSelf()."/comment:hide?id=".$Comment->getId()); ?>">Hide</a></li>
            <?php } ?>
            <?php if ($Acl->comment_write) { ?>
                <li><a href="<?php echo $this->url($this->getSelf()."/comment:add?parent=".$Comment->getId()); ?>">Reply</a></li>
            <?php } ?>
            </ul>
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