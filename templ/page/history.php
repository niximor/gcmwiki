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
<form action="<?php echo $this->url($this->getSelf()); ?>" method="get">

<div>
	<input type="hidden" name="diff" value="" />
	<table>
		<thead>
			<tr>
				<th class="history_revision">#</th>
				<th class="history_diff" colspan="2">Diff</th>
				<th class="history_show"></th>
				<th class="history_date">Date</th>
				<th class="history_author">Author</th>
				<th class="history_comment">Comment (* - small change)</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td class="history_revision"><?php echo $Page->revision; ?></td>
				<td class="history_diff"><input type="radio" name="a" value="<?php echo $Page->revision; ?>" checked="checked" /></td>
				<td class="history_diff">&nbsp;</td>
				<td class="history_show"><a href="<?php echo $this->url($this->getSelf()); ?>">Show</a></td>
				<td class="history_date"><?php echo $Page->last_modified; ?></td>
				<td class="history_author"><?php echo $Page->User->profileLink($this); ?></td>
				<td class="history_comment">(current) <?php if ($Page->small_change) echo "* "; echo htmlspecialchars($Page->summary); ?></td>
			</tr>
			<?php
				$len = count($History);
				for ($i = 0; $i < $len; ++$i) {
					$Page = $History[$i];
			?>
			<tr>
				<td class="history_revision"><?php echo $Page->revision; ?></td>
				<td><?php if ($i < $len - 1) { ?><input type="radio" name="a" value="<?php echo $Page->revision; ?>" /><?php } ?></td>
				<td><input type="radio" name="b" value="<?php echo $Page->revision; ?>"<?php if ($i == 0) echo " checked=\"checked\""; ?> /></td>
				<td class="history_show"><a href="<?php echo $this->url($this->getSelf()); ?>?revision=<?php echo $Page->revision; ?>">Show</a></td>
				<td class="history_date"><?php echo $Page->last_modified; ?></td>
				<td class="history_author"><?php echo $Page->User->profileLink($this); ?></td>
				<td class="history_comment"><?php if ($Page->small_change) echo "* "; echo htmlspecialchars($Page->summary); ?></td>
			</tr>
			<?php
				}
			?>
		</tbody>
	</table>
</div>

<div class="buttons">
	<input type="submit" value="Show" />
</div>

</form>