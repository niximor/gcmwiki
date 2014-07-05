<?php

namespace lib\formatter\format;

require_once "lib/format/Trigger.php";

class BlockContext extends Context {
    public $firstline = "";
    public $lines = array();
    public $endRe = '/$^/';
    public $isFirstLine = true;
}

class Block extends LineTrigger {
    protected static $blocks = array();
    public static function registerBlockFormatter($name, $callback) {
        if (!isset(self::$blocks[$name])) {
            if (is_callable($callback)) {
                self::$blocks[$name] = $callback;
            } else {
                throw \RuntimeException("Second parameter of Block::registerBlockFormatter must be valid callback.");
            }
        } else {
            throw \RuntimeException("Block formatter with name `".$name."' already exists.");
        }
    }

    function getRegExp(Context $ctx) {
        if ($ctx instanceof BlockContext) {
            return '/.*/';
        } else {
            return '/^{{{(?!.*}}})(.*?)$/';
        }
    }
    
    function getEndRegExp(Context $ctx) {
        return $ctx->endRe;
    }
    
    function getContext(Context $parent, $line, $matches) {
        if (isset($matches[2]) && $matches[2] == '}}}') {
            // It is one-liner, so no first line, just plain formatting.
            $parent->generate(htmlspecialchars($matches[1]));
            $parent->formatInline($matches[3]);
            return $parent;
        } else {
            // Multi line
            $ctx = new BlockContext($parent, $this);
            $ctx->firstline = $matches[1];
            return $ctx;
        }
    }
    
    function callLine(Context $ctx, $line, $matches) {
        if ($ctx->isFirstLine) {
            $ctx->isFirstLine = false;
        } elseif ($line == '}}}') {
            $ctx->endRe = '/.*/'; // Anything that follows ends the block.
        } else {
            $ctx->lines[] = $line;
        }
    }
    
    function callEnd(Context $ctx) {
        if ($ctx->firstline == "") {
            $ctx->generateHTML("\n<pre>");
            $ctx->generateHTML(htmlspecialchars(implode("\n", $ctx->lines)));
            $ctx->generateHTML("</pre>\n");
        } else {
            $params = preg_split('/:/', $ctx->firstline, 1);
            if (isset(self::$blocks[$params[0]])) {
                self::$block[$params[0]]($ctx->lines, $params);
            } else {
                $ctx->generateHTML("\n<pre>");
                $ctx->lines = array_merge(array($ctx->firstline), $ctx->lines);
                $ctx->generate(htmlspecialchars(implode("\n", $ctx->lines)));
                $ctx->generateHTML("</pre>\n");
            }
        }
    }
    
    static function testSuite() {
        self::testFormat(<<<EOF
{{{
this is raw escaped block
}}}
aaa {{{preformatted text that **does** not get wiki formatted}}} bbb
{{{not //formatted// text}}}aaa
after must get formatted too.

{{{code:c++
some c++ code
}}}
EOF
,
<<<EOF
<pre>this is raw escaped block</pre>
aaa preformatted text that **does** not get wiki formatted bbb not //formatted// textaaa after must get formatted too. 
<pre>code:c++
some c++ code</pre>
EOF
);
    }
}

class PlainText extends InlineTrigger {
    function getRegExp(Context $ctx) {
        return '/{{{(.*?)}}}/';
    }
    
    function callback(Context $ctx, $matches) {
        $ctx->generate(htmlspecialchars($matches[1]));
    }
}
