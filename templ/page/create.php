<h1>Create page</h1>

<form action="<?php echo $this->url($this->getSelf()); ?>?create" method="post">
    <div>
        <label for="name_child">Create a sub page:</label>
        <?php $pp = function($page) use (&$pp) { if (is_null($page)) return; $pp($page->getParent()); echo "<code style=\"font-size: 1.2em\">".$page->getName()."</code> / "; }; $pp($Page); ?> <input type="text" name="name_child" />
    </div>

    <div>
        <label for="name">Create new page:</label>
        <input type="text" name="name" />
    </div>

    <div class="buttons">
        <input type="submit" value="Create" />
    </div>
</form>