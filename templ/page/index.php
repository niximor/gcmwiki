<h1><?php echo $Page->getName(); ?></h1>

<div>
	<?php echo $Page->getBody_html(); ?>
</div>

Last modified <?php echo $Page->getLast_modified(); ?> by <?php echo $Page->getUser_id(); ?>.

<?php if ($Acl->page_write) { ?>[<a href="<?php echo $this->url($this->getSelf()."?edit"); ?>">Edit</a>]<?php } ?>
[<a href="<?php echo $this->url($this->getSelf()."?history"); ?>">History</a>]
<?php if ($Acl->page_admin) { ?>[<a href="<?php echo $this->url($this->getSelf()."?acl"); ?>">ACLs</a>]<?php } ?>