<?php

namespace lib\formatter\format;

require_once "lib/format/Trigger.php";

class ListContext extends Context {
    public $levels = array();
    public $firstOnLevel = array();
    public $lastIndent = 0;
}

class Lists extends LineTrigger {
    protected $defaultRe = '/^(\s*)(\\*|-|[0-9]+\.|[a-z]+\.|[A-Z]+\.) (.*)/';
    protected $currentRe = NULL;
    protected $lineContext = NULL;

    function getRegExp() {
        if (is_null($this->currentRe)) {
            return $this->defaultRe;
        } else {
            return $this->currentRe;
        }
    }

    function getEndRegExp(Context $ctx) {
        $re = '/^(?!\s\s)\s*[^\s*-].*$|^(?!\s\s)\s*$/';
        return $re;
    }

    function getContext(Context $parent, $line, $matches) {
        $this->currentRe = '/^(\s*)([*-]) (.*)|(\s{2,})()(.*)/e';
        return new ListContext($parent, $this);
    }

    function callLine(Context $context, $line, $matches) {
        $indent = strlen($matches[1]) + 1;
        $type = $matches[2];

        if ($type == "") {
            // Continuous line
            $indent = strlen($matches[1]) + 1;
        }

        if ($indent == $context->lastIndent + 1) {
            // New level
            if ($type == "*") {
                $context->levels[$indent] = "ul";
            } else {
                $context->levels[$indent] = "ol";
            }

            $context->firstOnLevel[$indent] = true;
            $context->lastIndent = $indent;

            // TODO: Optional params here

            $context->generateHTML("<".$context->levels[$indent].">");
        } elseif ($indent < $context->lastIndent) {
            // Fall to new level
            for (; $context->lastIndent > $indent; --$context->lastIndent) {
                $context->generateHTML("</li>");
                $context->generateHTML("</".$context->levels[$context->lastIndent].">");
            }
        } elseif ($context->levels[$indent] == "ol" && $type == "*") {
            $context->generateHTML("</li></ol>");
            $context->generateHTML("<ul>");
            $context->firstOnLevel[$indent] = true;
            $context->levels[$indent] = "ul";
        } elseif ($context->levels[$indent] == "ul" && $type != "*" && $type != "") {
            $context->generateHTML("</li></ul>");
            $context->generateHTML("<ol>");
            $context->firstOnLevel[$indent] = true;
            $context->levels[$indent] = "ol";
        }

        if ($type != "") {
            if (!$context->firstOnLevel[$context->lastIndent]) {
                $context->generateHTML("</li>");
            } else {
                $context->firstOnLevel[$indent] = false;
            }

            $context->generateHTML("<li>");
            $this->lineContext = new Context($context);
            $context->formatLine($this->lineContext, $matches[3]);
        } else {
            $context->formatLine($this->lineContext, substr($matches[0], $indent + 1));
        }
    }

    function callEnd(Context $context) {
        for (;$context->lastIndent > 0; --$context->lastIndent) {
            $context->generateHTML("</li>");
            $context->generateHTML("</".$context->levels[$context->lastIndent].">");
        }

        $this->currentRe = NULL;
    }

    static function testSuite() {
        self::testFormat("* item 1
 * item 1.1
  * item 1.1.1
 * item 1.2
* item 2
* item 3
  
  je seznam
    taky je seznam
- jinyseznam
taky neni seznam",
"<ul><li>item 1<ul><li>item 1.1<ul><li>item 1.1.1</li></ul></li><li>item 1.2</li></ul></li><li>item 2</li><li>item 3 je seznam<ol></li></ol>taky je seznam</li></ul><ol><li>jinyseznam</li></ol>taky neni seznam");
    }
}

