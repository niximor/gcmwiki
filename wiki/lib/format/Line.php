<?php

namespace lib\formatter\format;

require_once "lib/format/Trigger.php";

class Line extends LineTrigger {
    function getRegExp(Context $ctx) { return '/^-{4,}$/'; }
    function callLine(Context $context, $line, $matches) {
        $context->generateHTML("\n<hr />\n");
    }
}
