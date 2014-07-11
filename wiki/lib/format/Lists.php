<?php

namespace lib\formatter\format;

require_once "lib/format/Trigger.php";

class ListContext extends Context {
    public $levels = array();
    public $firstOnLevel = array();
    public $lastIndent = 0;
    public $lineContext = NULL;
    public $currentRe = NULL;
    public $first = true;
}

class Lists extends LineTrigger {
    protected $defaultRe = '/^(\s*)(\\*|-|[0-9]+\.|[a-z]+\.|[A-Z]+\.) (.*)/';

    function getRegExp(Context $ctx) {
        if (!$ctx instanceof ListContext || is_null($ctx->currentRe)) {
            return $this->defaultRe;
        } else {
            return $ctx->currentRe;
        }
    }

    function getEndRegExp(Context $ctx) {
        $re = '/^(?!\s\s)\s*[^\s*-].*$|^(?!\s\s)\s*$/';
        return $re;
    }

    function getContext(Context $parent, $line, $matches) {
        $ctx = new ListContext($parent, $this);
        $ctx->currentRe = '/^(\s*)([*-]) (.*)|(\s{2,})()(.*)/e';
        return $ctx;
    }
    
    function genIndent($level) {
        return str_repeat("\t", $level);
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
            
            if (!$context->first) {
                $context->generateHTML("\n");
            } else {
                $context->first = false;
            }
            $context->generateHTML($this->genIndent(($indent - 1) * 2)."<".$context->levels[$indent].">\n");
        } elseif ($indent < $context->lastIndent) {
            // Fall to new level
            for (; $context->lastIndent > $indent; --$context->lastIndent) {
                $context->generateHTML("</li>\n");
                $context->generateHTML($this->genIndent(($context->lastIndent - 1) * 2)."</".$context->levels[$context->lastIndent].">\n");
                $context->generateHTML($this->genIndent((($context->lastIndent - 1) * 2) - 1));
            }
        } elseif ($context->levels[$indent] == "ol" && $type == "*") {
            $context->generateHTML("</li>\n</ol>\n");
            $context->generateHTML("<ul>\n");
            $context->firstOnLevel[$indent] = true;
            $context->levels[$indent] = "ul";
        } elseif ($context->levels[$indent] == "ul" && $type != "*" && $type != "") {
            $context->generateHTML("</li>\n</ul>\n");
            $context->generateHTML("<ol>\n");
            $context->firstOnLevel[$indent] = true;
            $context->levels[$indent] = "ol";
        }

        if ($type != "") {
            if (!$context->firstOnLevel[$context->lastIndent]) {
                $context->generateHTML("</li>\n");
            } else {
                $context->firstOnLevel[$indent] = false;
            }

            $context->generateHTML($this->genIndent(($indent * 2) - 1)."<li>");
            $context->lineContext = new Context($context);
            $context->lineContext->formatLine($context->lineContext, $matches[3]);
        } else {
            $context->lineContext->formatLine($context->lineContext, substr($matches[0], $indent + 1));
        }
    }

    function callEnd(Context $context) {
        for (;$context->lastIndent > 0; --$context->lastIndent) {
            $context->generateHTML("</li>\n");
            $context->generateHTML("</".$context->levels[$context->lastIndent].">\n");
        }

        $context->currentRe = NULL;
    }

    static function testSuite(\lib\formatter\WikiFormatter $f) {
        self::testFormat($f, "* item 1
 * item 1.1
  * item 1.1.1
 * item 1.2
* item 2
* item 3
* item 4
  
  je seznam
    taky je seznam
- jinyseznam
taky neni seznam",
<<<EOF
<ul>
\t<li>item 1
\t\t<ul>
\t\t\t<li>item 1.1
\t\t\t\t<ul>
\t\t\t\t\t<li>item 1.1.1</li>
\t\t\t\t</ul>
\t\t\t</li>
\t\t\t<li>item 1.2</li>
\t\t</ul>
\t</li>
\t<li>item 2</li>
\t<li>item 3</li>
\t<li>item 4 je seznam taky je seznam</li>
</ul>
<ol>
\t<li>jinyseznam</li>
</ol>

<p>
taky neni seznam
</p>

EOF
);
    }
}

