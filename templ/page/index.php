<h1><?php echo htmlspecialchars($Page->getName()); ?></h1>

<div class="wiki">
	<?php echo $Page->getBody_html(); ?>
</div>

<?php
	if ($Acl->comment_read) {
?>
<div class="comments">
	<h2>Comments</h2>
<?php
    $t = new \view\Template("comments/show.php", $this);
    $t->addVariable("Comments", $Comments);
    $t->render();
?>
	<div class="footer">
		<a href="<?php echo $this->url($this->getSelf()."/comment:add"); ?>">Add comment</a>
	</div>
</div>
<?php
	}
?>

<div>
Last modified <?php echo $Page->getLast_modified(); ?> by <?php echo $Page->User->profileLink($this); ?>.
</div>
