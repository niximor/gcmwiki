<?php

namespace lib\formatter\format;

require_once "lib/format/Trigger.php";

class Line extends LineTrigger {
    function getRegExp() { return '/^-{4,}$/'; }
    function callLine(Context $context, $line, $matches) {
        $context->generateHTML("<hr />");
    }
}
