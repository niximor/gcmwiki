<h1><?php echo htmlspecialchars($Page->getName()); ?></h1>

<?php
    if ($Page->redirected_from) {
        echo "<em>Redirected from <a href=\"".htmlspecialchars($this->url(implode("/", $Page->redirected_from->getPath())))."?edit\">".htmlspecialchars($Page->redirected_from->getName())."</a>.</em>";
    }

    if (!$Page->getIsCurrentRevision()) {
        echo "<p class=\"warning\">You are viewing historical revision ".$Page->getRevision()." from ".$Page->getLastModified()." authored by ".$Page->User->profileLink($this).". <a href=\"".$this->url($this->getSelf())."\">View current revision</a></p>";
    }
?>

<div class="wiki">
	<?php echo $Page->getBody_html(); ?>
</div>

<?php if (!empty($Attachments)) { ?>
<div class="attachments">
    <h2 class="collapsible" data-for=".attachments>ul"<?php if (!\lib\CurrentUser::i()->getShowAttachments()) echo " data-state=\"collapsed\""; ?>>Attachments</h2>

    <ul>
<?php foreach ($Attachments as $Attachment) { ?>
        <li><a href="<?php echo $this->url($this->getSelf()."/attachments:index/".htmlspecialchars($Attachment->getName())); ?>" class="mime-<?php echo $Attachment->getTypeString(); ?>"><?php echo htmlspecialchars($Attachment->getName()); ?></a></li>
<?php } ?>
    </ul>
</div>
<?php } ?>

<?php
	if ($Acl->comment_read) {
?>
<div class="comments">
	<h2 class="collapsible" data-for=".comments>ul, .comments>.footer"<?php if (!\lib\CurrentUser::i()->getShowComments()) echo " data-state=\"collapsed\""; ?>>Comments</h2>
<?php
    $t = new \view\Template("comments/show.php", $this);
    $t->addVariable("Comments", $Comments);
    $t->render();
?>
	<div class="footer">
        <ul>
            <?php if ($Acl->comment_write) { ?><li><a href="<?php echo $this->url($this->getSelf()."/comment:add"); ?>">Add comment</a></li><?php } ?>
            <?php if ($Acl->attachment_write) { ?><li><a href="<?php echo $this->url($this->getSelf()."/attachments:attach"); ?>">Attach file</a></li><?php } ?>
        </ul>
	</div>
</div>
<?php
	}
?>

<div>
Last modified <?php echo $Page->getLast_modified(); ?> by <?php echo $Page->User->profileLink($this); ?>. Rendered: <?php if ($Page->getWasCached()) echo "from cache"; else echo "from database"; ?>
</div>
