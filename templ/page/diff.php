<h1><?php echo $Page->getName(); ?></h1>

Showing differencies between revision <?php echo $Revision2->revision; ?> and <?php echo $Revision1->revision; ?>.

<?php
	foreach ($Diff as $d) {
		if ($d->mode == \lib\Diff::EQUAL) {
			$prefix = "<div>&nbsp;";
		} elseif ($d->mode == \lib\Diff::REM) {
			$prefix = "<div>-";
		} else {
			$prefix = "<div>+";
		}

		foreach ($d->lines as $line) {
			echo $prefix.$line[0]." ".$line[1]."</div>\n";
		}
	}
?>
