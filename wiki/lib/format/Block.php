<?php

namespace lib\formatter\format;

require_once "lib/format/Trigger.php";

class PlainText extends InlineTrigger {
    function getRegExp(Context $ctx) {
        return '/{{{(.*?)}}}/';
    }

    function callback(Context $ctx, $matches) {
        $ctx->generate(htmlspecialchars($matches[1]));
    }
}

class BlockContext extends Context {
    public $firstline = "";
    public $lines = array();
    public $endRe = '/$^/';
    public $isFirstLine = true;
    public $inheritanceLevel = 0;
}

class Block extends LineTrigger {
    protected $blocks = array();

    public function registerBlockFormatter($name, $callback) {
        if (!isset($this->blocks[$name])) {
            if (is_callable($callback)) {
                $this->blocks[$name] = $callback;
            } else {
                throw new \RuntimeException("Second parameter of Block::registerBlockFormatter must be valid callback.");
            }
        } else {
            throw new \RuntimeException("Block formatter with name `".$name."' already exists.");
        }
    }

    function getRegExp(Context $ctx) {
        return '/^\s*{{{(?!.*}}})(.*?)$|^\s*{{{(.*?)}}}$/';
    }

    function getEndRegExp(Context $ctx) {
        return $ctx->endRe;
    }

    function getContext(Context $parent, $line, $matches) {
        $ctx = new BlockContext($parent, $this);
        if (isset($matches[1]) && !empty($matches[1])) {
            $ctx->firstline = $matches[1];
        } elseif (isset($matches[2]) && !empty($matches[2])) {
            $ctx->firstline = $matches[2];
        }
        return $ctx;
    }

    function callLine(Context $ctx, $line, $matches) {
        if (!$ctx->isFirstLine && strpos($line, '{{{') !== false) {
            ++$ctx->inheritanceLevel;
        }

        if ($ctx->inheritanceLevel <= 0 && (ltrim($line) == '}}}' || (is_array($matches) && isset($matches[2])))) {
            $ctx->endRe = '/.*/'; // Anything that follows ends the block.
        } elseif (!$ctx->isFirstLine) {
            $ctx->lines[] = $line;
            if (ltrim($line) == '}}}') --$ctx->inheritanceLevel;
        }

        if ($ctx->isFirstLine) {
            $ctx->isFirstLine = false;
        }
    }

    function callEnd(Context $ctx) {
        if ($ctx->firstline == "") {
            $ctx->generateHTML("\n<pre>");
            $ctx->generate(htmlspecialchars(implode("\n", $ctx->lines)), false);
            $ctx->generateHTML("</pre>\n");
        } else {
            $params = preg_split('/:/', $ctx->firstline);
            if (isset($this->blocks[$params[0]])) {
                $cb = $this->blocks[$params[0]];
                $cb($ctx, $params);
            } else {
                $ctx->generateHTML("\n<pre>");
                $ctx->lines = array_merge(array($ctx->firstline), $ctx->lines);
                $ctx->generate(htmlspecialchars(implode("\n", $ctx->lines)), false);
                $ctx->generateHTML("</pre>\n");
            }
        }
    }

    static function testSuite(\lib\formatter\WikiFormatter $f) {
        self::testFormat($f, "{{{
this is raw escaped block
}}}
aaa {{{preformatted text that **does** not get wiki formatted}}} bbb
{{{not //formatted// text}}}aaa
after must get formatted too.

{{{code:c++
some c++ code
}}}",
"
<pre>this is raw escaped block</pre>

<p>
aaa preformatted text that **does** not get wiki formatted bbb not //formatted// textaaa
after must get formatted too.
</p>

<pre>code:c++
some c++ code</pre>
"
);

        self::testFormat($f, "before
{{{code
inheritance test:
{{{
something
}}}
}}}
aaa",
"
<p>
before
</p>

<pre>code
inheritance test:
{{{
something
}}}</pre>

<p>
aaa
</p>
");
    }

}

