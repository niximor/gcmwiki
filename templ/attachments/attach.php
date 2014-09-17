<h1>Attach file</h1>

<form action="<?php echo $this->url($this->getSelf()); ?>" method="post" enctype="multipart/form-data">
    <div class="fullwidth">
        <label for="name">Name:</label>
        <div><input type="text" name="name" /></div>
        <div class="comment">Name of attachment that will be displayed to the visitors.</div>
    </div>

    <div class="fullwidth">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $UploadMaxBytes; ?>" />
        <label for="file">File:</label>
        <div><input type="file" name="file" /></div>
        <div class="comment">Max. allowed file size is <?php echo $UploadMaxSize; ?>.</div>
    </div>

    <div class="buttons">
        <input type="submit" value="Attach" />
        <a href="<?php echo $this->url($this->getSelf(), -1); ?>">Cancel</a>
    </div>
</form>

<script type="text/javascript">
startup(function(){
    var name = document.querySelector("input[name=name]");

    addEvent(document.querySelector("input[name=file]"), "change", function(){
        if (name.value == "") {
            name.value = this.value;
        }
    });
});
</script>