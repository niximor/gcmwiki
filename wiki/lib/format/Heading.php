<?php

namespace lib\formatter\format;

require_once "lib/format/Trigger.php";

class Heading extends LineTrigger {
    protected $startRe;
    protected $tagName;

    static function Create($level) {
        $h = new Heading();
        $h->startRe = sprintf('/^%s([^=].*)%s$/', str_repeat("=", $level + 1), str_repeat("=", $level + 1));
        $h->tagName = sprintf("h%d", $level);
        return $h;
    }

    function getRegExp(Context $ctx) { return $this->startRe; }
    function callLine(Context $context, $line, $matches) {
        $context->generateHTML(sprintf("<%s>", $this->tagName));
        $context->inlineFormat($matches[1]);
        $context->generateHTML(sprintf("</%s>\n", $this->tagName));
    }
}

