<h1><?php echo $Page->getName(); ?></h1>

Showing differencies between revision <?php echo $Revision1->revision; ?> and <?php echo $Revision2->revision; ?>.

<table class="diff">
	<tbody>
<?php
	foreach ($Diff as $d) {
		if ($d->mode == \lib\Diff::EQUAL) {
			$prefix = "<tr class=\"equal\"><td class=\"sign\">&nbsp;</td>";
		} elseif ($d->mode == \lib\Diff::REM) {
			$prefix = "<tr class=\"rem\"><td class=\"sign\">-</td>";
		} else {
			$prefix = "<tr class=\"add\"><td class=\"sign\">+</td>";
		}

		foreach ($d->lines as $line) {
			echo $prefix."<td class=\"ln\">".$line[0]."</td><td class=\"content\">".htmlspecialchars($line[1])."</td></tr>\n";
		}
	}
?>
	</tbody>
</table>