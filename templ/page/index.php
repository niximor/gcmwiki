<h1><?php echo $Page->getName(); ?></h1>

<div>
	<?php echo $Page->getBody_html(); ?>
</div>

<?php
	if ($Acl->comment_read) {
?>
<h2>Comments</h2>
<?php
    $t = new \view\Template("comments/show.php", $this);
    $t->addVariable("Comments", $Comments);
    $t->render();
?>
<a href="<?php echo $this->url($this->getSelf()."/comment:add"); ?>">Add comment</a>
<?php
	}
?>

<div>
Last modified <?php echo $Page->getLast_modified(); ?> by <?php echo $Page->User->profileLink($this); ?>.
</div>

<?php if ($Acl->page_write) { ?>[<a href="<?php echo $this->url($this->getSelf()."?edit"); ?>">Edit</a>]<?php } ?>
[<a href="<?php echo $this->url($this->getSelf()."?history"); ?>">History</a>]
<?php if ($Acl->page_admin) { ?>[<a href="<?php echo $this->url($this->getSelf()."?acl"); ?>">ACLs</a>]<?php } ?>