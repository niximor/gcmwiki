<?php

namespace lib\formatter\format;

require_once "lib/format/Trigger.php";
require_once "lib/format/Block.php";

class TOCCapturingContext extends CapturingContext {
    function close() {
        $root = $this->getRoot();
        if (isset($root->TABLE_OF_CONTENTS)) {
            $newctx = new Context($this->getParent());
            $newctx->generateHTML("<div class=\"toc\">\n");
            $newctx->formatLines($newctx, $root->TABLE_OF_CONTENTS);
            $newctx->generateHTML("</div>\n");
        }

        parent::close();
    }
}

class Heading extends LineTrigger {
    protected $startRe;
    protected $tagName;
    protected $level;

    static function Create($level) {
        $h = new Heading();
        $h->startRe = sprintf('/^%s([^=].*)%s$/', str_repeat("=", $level + 1), str_repeat("=", $level + 1));
        $h->tagName = sprintf("h%d", $level);
        $h->level = $level;
        
        static $registered = false;
        if (!$registered) {
            Block::registerBlockFormatter("toc", array($h, "generateToc"));
            $registered = true;
        }
        
        return $h;
    }

    function getRegExp(Context $ctx) { return $this->startRe; }
    
    function callLine(Context $context, $line, $matches) {
        $root = $context->getRoot();
        if (!isset($root->TABLE_OF_CONTENTS)) {
            $root->TABLE_OF_CONTENTS = array();
        }
        
        $root->TABLE_OF_CONTENTS[] = str_repeat(" ", $this->level - 1)."* ".$matches[1]."\n";
    
        $context->generateHTML(sprintf("<%s>", $this->tagName));
        $context->inlineFormat($matches[1]);
        $context->generateHTML(sprintf("</%s>\n", $this->tagName));
    }
    
    function generateToc(Context $ctx, $params) {
        // Find context right after root.
        $findctx = $ctx;
        $beforeRoot = $findctx;
        while (!($findctx instanceof RootContext)) {
            $beforeRoot = $findctx;
            $findctx = $findctx->getParent();
        }

        // Put capturing context between root and current context.
        $capture = new TOCCapturingContext($ctx->getRoot());
        $beforeRoot->setParent($capture);
    }

    static function testSuite() {
        self::testFormat(<<<EOF
== h1 ==
{{{toc
}}}

=== h2 1 ===
=== h2 2 ===
==== h3 ====
=== h2 3 ===
EOF
,
<<<EOF
<h1>h1 </h1>
<div class="toc">
<ul>
    <li>h1 
        <ul>
            <li>h2 1 </li>
            <li>h2 2 
                <ul>
                    <li>h3 </li>
                </ul>
            </li>
            <li>h2 3 </li>
</ul>
</li>
</ul>
</div>
<h2>h2 1 </h2>
<h2>h2 2 </h2>
<h3>h3 </h3>
<h2>h2 3 </h2>
EOF
);
    }
}

