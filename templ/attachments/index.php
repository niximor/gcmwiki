<h1><?php echo htmlspecialchars($Attachment->getName()); ?></h1>

<div style="float: right; width: 240px; height: 240px;" class="wrapimg">
    <img src="<?php echo $this->url($AttachmentUrl."?s=contain240x240"); ?>" />
</div>

<dl>
    <dt>Size:</dt>
    <dd><?php echo \lib\humanSize($Attachment->getBytes()); ?></dd>

    <dt>Created:</dt>
    <dd><?php echo $Attachment->getCreated(); ?></dd>

    <dt>Type:</dt>
    <dd><?php echo $Attachment->getTypeString(); ?></dd>

    <dt>Available formats:</dt>
    <dd>
        <ul>
            <li><?php echo "<a href=\"".$this->url($AttachmentUrl)."\">Original".((!is_null($Attachment->getWidth()) && !is_null($Attachment->getHeight()))?" (".$Attachment->getWidth()."x".$Attachment->getHeight().")":"")."</a>"; ?></li>
            <?php foreach ($AvailableFormats as $format) { ?>
                <li><?php echo "<a href=\"".$this->url($AttachmentUrl)."?s=".$format["id"]."\">".$format["width"]."x".$format["height"]."</a>"; ?></li>
            <?php } ?>
        </ul>
    </dd>
</dl>

<h2>History</h2>