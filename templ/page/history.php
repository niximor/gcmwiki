<h1><?php echo $Page->getName(); ?></h1>
<form action="<?php echo $this->url($this->getSelf()); ?>" method="get">
<input type="hidden" name="diff" value="" />
<table>
	<thead>
		<tr>
			<th>Rev.</th>
			<th colspan="2">Diff</th>
			<th></th>
			<th>Date</th>
			<th>Author</th>
			<th>Comment (* - small change)</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><?php echo $Page->revision; ?></td>
			<td><input type="radio" name="a" value="<?php echo $Page->revision; ?>" /></td>
			<td>&nbsp;</td>
			<td><a href="<?php echo $this->url($this->getSelf()); ?>">Show</a></td>
			<td><?php echo $Page->last_modified; ?></td>
			<td><?php echo $Page->User->profileLink($this); ?></td>
			<td>(current) <?php if ($Page->small_change) echo "* "; echo htmlspecialchars($Page->summary); ?></td>
		</tr>
		<?php
			$len = count($History);
			for ($i = 0; $i < $len; ++$i) {
				$Page = $History[$i];
		?>
		<tr>
			<td><?php echo $Page->revision; ?></td>
			<td><?php if ($i < $len - 1) { ?><input type="radio" name="a" value="<?php echo $Page->revision; ?>" /><?php } ?></td>
			<td><input type="radio" name="b" value="<?php echo $Page->revision; ?>" /></td>
			<td><a href="<?php echo $this->url($this->getSelf()); ?>?revision=<?php echo $Page->revision; ?>">Show</a></td>
			<td><?php echo $Page->last_modified; ?></td>
			<td><?php echo $Page->User->profileLink($this); ?></td>
			<td><?php if ($Page->small_change) echo "* "; echo htmlspecialchars($Page->summary); ?></td>
		</tr>
		<?php
			}
		?>
	</tbody>
</table>

<div>
	<input type="submit" value="Show" />
</div>

</form>