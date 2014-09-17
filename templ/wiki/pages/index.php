<h1>Pages</h1>

<ul class="tabs">
<?php
    foreach ($Letters as $letter => $num) {
        echo "<li".(($SelectedLetter == $letter)?" class=\"selected\"":"").">";
        if ($num > 0) {
            echo "<a href=\"".$this->url($this->getSelf()."?l=".htmlspecialchars($letter)."&amp;p=1&amp;s=".$CurrentSort."&amp;d=".$CurrentDirection)."\">".htmlspecialchars($letter)."</a>";
        } else {
            echo "<span>".htmlspecialchars($letter)."</span>";
        }
        echo "</li>";
    }
?>
</ul>

<table class="pages">
    <thead>
        <tr>
<?php
    $hdr = function($name, $column) use (&$SelectedLetter, &$CurrentPage, &$CurrentSort, &$CurrentDirection) {
        if ($CurrentSort == $column) {
            $dir = ($CurrentDirection == "ASC")?"DESC":"ASC";
            $triangle = ($CurrentDirection == "ASC")?" &#9652;":" &#9662;";
        } else {
            $dir = "ASC";
            $triangle = "";
        }

        return "<a href=\"".$this->url($this->getSelf()."?l=".$SelectedLetter."&amp;p=".$CurrentPage."&amp;s=".$column."&amp;d=".$dir)."\">".htmlspecialchars($name)."</a>".$triangle;
    };
?>
            <th class="name"><?php echo $hdr("Name", "name"); ?></th>
            <th class="revision"><?php echo $hdr("Revision", "revision"); ?></th>
            <th class="links"><?php echo $hdr("Links", "links"); ?></th>
            <th class="references"><?php echo $hdr("References", "references"); ?></th>
            <th class="created"><?php echo $hdr("Created", "created"); ?></th>
            <th class="last_modified"><?php echo $hdr("Last modified", "last_modified"); ?></th>
        </tr>
    </thead>

    <tbody>
<?php foreach ($Pages->pages as $Page) { ?>
        <tr>
            <td class="name"><?php
                $pp = function($page) use (&$pp) {
                    if (is_null($page)) {
                        return;
                    }

                    $pp($page->getParent());
                    echo "<span>".htmlspecialchars($page->getName())."</span>/";
                };

                $pp($Page->getParent());
                echo "<a href=\"".$this->url($Page->getFullUrl())."\">".htmlspecialchars($Page->getName())."</a>";
            ?></td>
            <td class="revision"><?php echo $Page->getRevision(); ?></td>
            <td class="links"><?php echo $Page->getLinks(); ?></td>
            <td class="references"><?php echo $Page->getReferences(); ?></td>
            <td class="created"><?php echo $Page->getCreated(); ?></td>
            <td class="last_modified"><?php echo $Page->getLastModified(); ?></td>
        </tr>
<?php } ?>
    </tbody>

    <tfoot>
        <tr>
            <td colspan="6" class="paging">
                <ul>
                <?php for ($i = 1; $i <= ceil($Pages->totalCount / $Limit); ++$i) {
                    echo "<li".(($i == $CurrentPage)?" class=\"selected\"":"")."><a href=\"".$this->url($this->getSelf()."?l=".$SelectedLetter."&amp;p=".$i."&amp;s=".$CurrentSort."&amp;d=".$CurrentDirection)."\">".$i."</a></li>";
                } ?>
                </ul>

                <form action="<?php echo $this->url($this->getSelf()); ?>" method="get">
                    <input type="hidden" name="l" value="<?php echo $SelectedLetter; ?>" />
                    <input type="hidden" name="s" value="<?php echo $CurrentSort; ?>" />
                    <input type="hidden" name="d" value="<?php echo $CurrentDirection; ?>" />
                    <label>Jump to page: <input type="number" name="p" /></label> <input type="submit" value="Go" />
                </form>

                <div>Showing <?php echo min(($CurrentPage - 1) * $Limit + 1, $Pages->totalCount)."-".min($CurrentPage * $Limit, $Pages->totalCount)." of ".$Pages->totalCount." items."; ?></div>
            </td>
        </tr>
    </tfoot>
</table>