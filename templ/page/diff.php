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

<p>Showing differencies between revision <a href="<?php echo $this->url($this->getSelf()."?revision=".$Revision1->revision); ?>"><?php echo $Revision1->revision; ?></a> from <a href="<?php echo $this->url($this->getSelf()."?revision=".$Revision1->revision); ?>"><?php echo $Revision1->last_modified; ?></a>, authored by <?php echo $Revision1->User->profileLink($this); ?> and revision <a href="<?php echo $this->url($this->getSelf()."?revision=".$Revision2->revision); ?>"><?php echo $Revision2->revision; ?></a> from <a href="<?php echo $this->url($this->getSelf()."?revision=".$Revision2->revision); ?>"><?php echo $Revision2->last_modified; ?></a>, authored by <?php echo $Revision2->User->profileLink($this); ?>.</p>

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