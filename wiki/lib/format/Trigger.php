<?php

namespace lib\formatter\format;

require_once "lib/format/Context.php";

abstract class Trigger {
    static function testFormat($in, $out) {
        $fmt = new \lib\formatter\WikiFormatter;
        $realOut = $fmt->format($in);
        if ($realOut != $out) {
            trigger_error(sprintf("Assertion failed. Input does not match output.<br />Expected: %s<br />Got: %s", htmlspecialchars($out), htmlspecialchars($realOut)), E_USER_WARNING);
        }
    }
}

abstract class LineTrigger extends Trigger {
    abstract function getRegExp();
    function getEndRegExp(Context $ctx) { return "/^/"; } // Match anything by default.
    function getContext(Context $parent, $line, $matches) { return new Context($parent, $this); } // If it does not generate new context
    abstract function callLine(Context $context, $line, $matches);
    function callEnd(Context $context) {}
}

abstract class InlineTrigger extends Trigger {
    abstract function getRegExp();
    abstract function callback(Context $ctx, $matches);
}
