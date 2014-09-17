<?php

$pp = function($page) use (&$pp) {
	if (is_null($page)) {
		return;
	}

	$pp($page->getParent());
	echo $page->getName()." / ";
};

?>

<?php if ($Page->getId()) { ?>
<h1>Edit page <?php $pp($Page->getParent()); echo htmlspecialchars($Page->getName()); ?></h1>
<?php } else { ?>
<h1>Create page <?php $pp($Page->getParent()); echo htmlspecialchars($Page->getName()); ?></h1>
<?php } ?>

<?php if ($Page->getLocked() && !$Acl->page_admin) { ?>
<p class="warning">This page is locked for editing. Only administrator can unlock the page.</p>
<?php } ?>

<form action="<?php echo $this->url($this->getSelf()); ?>?save" method="post" class="editPage">
	<div class="nogrid fullwidth">
		<label for="name">Name:</label>
		<div><input type="text" name="name" value="<?php if (isset($Form["name"])) echo htmlspecialchars($Form["name"]); else echo htmlspecialchars($Page->getName()); ?>"<?php if ($Page->getLocked() && !$Acl->page_admin) echo " readonly disabled"; ?> /></div>
		<?php if (isset($Errors["name"])) { echo "<ul>"; foreach ($Errors["name"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
	</div>

	<div class="textarea">
		<ul class="toolbar">
			<!-- Closing </li>s are missing intentionally because of gap that is created between elements. -->
			<li><input type="button" class="flat"<?php if ($Page->getLocked() && !$Acl->page_admin) echo " disabled"; ?> name="bold" title="Bold" value="Bold" />
			<li><input type="button" class="flat"<?php if ($Page->getLocked() && !$Acl->page_admin) echo " disabled"; ?> name="italic" title="Italic" value="Italic" />
			<li><input type="button" class="flat"<?php if ($Page->getLocked() && !$Acl->page_admin) echo " disabled"; ?> name="underline" title="Underline" value="Underline" />
			<li><input type="button" class="flat"<?php if ($Page->getLocked() && !$Acl->page_admin) echo " disabled"; ?> name="strikethrough" title="Strikethrough" value="Strikethrough" />
			<li><input type="button" class="flat"<?php if ($Page->getLocked() && !$Acl->page_admin) echo " disabled"; ?> name="code" title="Code" value="Code" />
			<li><input type="button" class="flat"<?php if ($Page->getLocked() && !$Acl->page_admin) echo " disabled"; ?> name="newline" title="New line" value="New line" />
			<li><input type="button" class="flat"<?php if ($Page->getLocked() && !$Acl->page_admin) echo " disabled"; ?> name="ulist" title="Unordered list" value="Unordered list" />
			<li><input type="button" class="flat"<?php if ($Page->getLocked() && !$Acl->page_admin) echo " disabled"; ?> name="olist" title="Ordered list" value="Ordered list" />
			<li><input type="button" class="flat"<?php if ($Page->getLocked() && !$Acl->page_admin) echo " disabled"; ?> name="quote" title="Quote" value="Quote" />
			<li><input type="button" class="flat"<?php if ($Page->getLocked() && !$Acl->page_admin) echo " disabled"; ?> name="line" title="Line" value="Line" />
			<li><input type="button" class="flat"<?php if ($Page->getLocked() && !$Acl->page_admin) echo " disabled"; ?> name="link" title="Link" value="Link" />
			<li><input type="button" class="flat"<?php if ($Page->getLocked() && !$Acl->page_admin) echo " disabled"; ?> name="image" title="Image" value="Image" />
			<li><input type="button" class="flat"<?php if ($Page->getLocked() && !$Acl->page_admin) echo " disabled"; ?> name="table" title="Table" value="Table" />
			<li><input type="button" class="flat"<?php if ($Page->getLocked() && !$Acl->page_admin) echo " disabled"; ?> name="special" title="Special" value="Special" /><ul class="menu">
					<li><input type="button" name="toc" value="Table of contents" /></li>
					<li><input type="button" name="category" value="Category listing" /></li>
					<li><input type="button" name="template" value="Template" /></li>
				</ul>
			</li>
		</ul>
		<div><textarea name="body"<?php if ($Page->getLocked() && !$Acl->page_admin) echo " readonly disabled"; ?>><?php if (isset($Form["body"])) echo htmlspecialchars($Form["body"]); else echo htmlspecialchars($Page->getBody_wiki()); ?></textarea></div>
		<?php if (isset($Errors["body"])) { echo "<ul>"; foreach ($Errors["body"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
	</div>

	<?php if ($Acl->page_admin) { ?>
	<div>
		<label for="locked">Page is locked:</label>
		<input type="hidden" name="locked" value="0" /><input type="checkbox" name="locked" value="1"<?php if ($Page->getLocked()) echo " checked"; ?> />
		<span>Cannot be edited by non-admins.</span>
	</div>
	<?php } ?>

	<!-- TODO: Renderer and template -->

	<div class="nogrid fullwidth">
		<label for="summary">Summary:</label>
		<div style="float: right" class="nofull">
			<label class="checkbox"><input type="checkbox" name="small_change"<?php if (isset($Form["small_change"])) echo " checked=\"checked\""; ?> /> Small change</label>
		</div>
		<div>
			<input type="text" name="summary" value="<?php if (isset($Form["summary"])) echo htmlspecialchars($Form["summary"]); ?>" />
		</div>
		<?php if (isset($Errors["summary"])) { echo "<ul>"; foreach ($Errors["summary"] as $err) { echo "<li>".$err->message."</li>"; } echo "</ul>"; } ?>
	</div>

	<div class="buttons">
		<input type="submit" value="Save" /> <a href="<?php echo $this->url($this->getSelf()); ?>" class="button">Cancel</a>
	</div>
</form>
