<?php

namespace specials;

require_once "specials/SpecialController.php";

class AttachmentController extends SpecialController {
    function attach() {
        
    }

    function _list() {

    }
}

\Config::registerSpecial("attachments", "\\specials\\AttachmentController");

