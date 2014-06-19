<?php

namespace lib\formatter\format;

require_once "lib/format/Trigger.php";

class BlockquoteContext extends Context {
    public $level = 0;
}

class Blockquote extends LineTrigger {
    function getRegExp() {
        return "/^(>+) (.*)$/";
    }

    function getEndRegExp(Context $ctx) {
        return "/^[^>]/";
    }

    function getContext(Context $parent, $line, $matches) {
        return new BlockquoteContext($parent, $this);
    }

    function callLine(Context $context, $line, $matches) {
        $level = strlen($matches[1]);
        if ($level < $context->level) {
            for (; $context->level > $level; --$context->level) $context->getFormatter()->generateHTML("</blockquote>");
        } elseif ($level > $ctx->blockquoteLevel) {
            for (; $context->level < $level; ++$context->level) $context->getFormatter()->generateHTML("<blockquote>");
        }

        $newctx = new Context($context);
        $context->formatLine($newctx, $matches[2]);
    }

    function callEnd(Context $context) {
        for (;$context->level > 0; --$context->level) {
            $context->generateHTML("</blockquote>");
        }
    }
}

