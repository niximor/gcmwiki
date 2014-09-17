<h1>Comment history</h1>

<ul class="comments">
    <li class="comment">
        <div class="header">
            Revision <?php echo $Comment->getRevision(); ?> by
            <?php
                if (!is_null($Comment->EditUser)) {
                    echo $Comment->EditUser->profileLink($this);
                } else {
                    echo $Comment->EditUser->profileLink($this);
                }
            ?> :: <?php echo $Comment->last_modified; ?>
        </div>

        <div class="body wiki">
            <?php if ($Comment->getHidden()) { ?>
            Comment text was hidden by administrator.
            <?php } else { echo $Comment->getTextHtml(); } ?>
        </div>

        <div class="footer">
        </div>
    </li>

<?php foreach ($Comment->History as $history) { ?>
    <li class="comment">
        <div class="header">
            Revision <?php echo $history->getRevision(); ?> by <?php echo $history->EditUser->profileLink($this); ?> :: <?php echo $history->getLastModified(); ?>
        </div>

        <div class="body wiki">
            <?php echo $history->getText_html(); ?>
        </div>

        <div class="footer">
        </div>
    </li>
<?php } ?>
</ul>