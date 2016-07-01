<?php

namespace lib\formatter\format;

require_once "lib/format/Block.php";

class Html {
    public function register(Block $format) {
        $format->registerBlockFormatter("html", array($this, "htmlCall"));
    }

    public function htmlCall(Context $ctx, $params) {
        $ctx->generateHTML(implode("\n", $ctx->lines));
    }
}

