<?php

namespace lib\formatter;

require_once "lib/format/Context.php";

class WikiFormatter {
    const LINE_BREAK_POINT = 80; // Number of characters on line.

    protected $output = array();
    protected $space = true;
    protected $inParagraph = false;
    protected $startParagraph = true;
    protected $currentLineLength = 0;
    protected $context;

    public $debug = false;

    public $lineTriggers = array();
    public $inlineTriggers = array();

	public function installLineTrigger(format\LineTrigger $trigger) {
		$this->lineTriggers[] = $trigger;
	}

    public function installInlineTrigger(format\InlineTrigger $trigger) {
        $this->inlineTriggers[] = $trigger;
    }

    public function log($msg) {
        if ($this->debug) {
            echo htmlspecialchars(call_user_func_array("sprintf", func_get_args()));
            echo "\n";
        }
    }

    public function getRootContext() {
        if (!is_null($this->context)) {
            return $this->context->getRoot();
        } else {
            return NULL;
        }
    }

    public function format($text) {
        $this->output = array();
        $this->space = true;
        $this->inParagraph = false;
        $this->startParagraph = true;
        $this->currentLineLength = 0;

        $lines = explode("\n", $text);
        $ctx = $root = new format\RootContext($this);
        $this->context = $root;
        
        foreach ($lines as $line) {
            $line = preg_replace('/\r$/', '', $line);
            $ctx->formatLine($ctx, $line);
        }

        while ($ctx != $root) {
            if ($ctx->getTrigger()) {
                $ctx->getTrigger()->callEnd($ctx);
            }
            $ctx->close();
            $ctx = $ctx->getParent();
        }

        if ($this->inParagraph) {
            $this->output[] = "\n</p>\n";
            $this->space = true;
            $this->currentLineLength = 0;
        }

        return implode("", $this->output);
    }

    public function generateHTML($string) {
        if (empty($string)) return;

        if ($this->inParagraph) {
            $this->output[] = "\n</p>\n";
            $this->space = true;
            $this->inParagraph = false;
            $this->currentLineLength = 0;
        }
        $this->startParagraph = false;
        
        $this->output[] = $string;
        $this->log("Generate block '%s'", trim($string));
        $this->space = true;

        if (preg_match('/\n([^\n]*)$/', $string, $matches)) {
            $this->currentLineLength = strlen($matches[1]);
        } else {
            $this->currentLineLength += strlen($string);
        }
    }
    
    public function generateHTMLInline($string) {
        if (empty($string)) return;

        if ($this->startParagraph && !$this->inParagraph) {
            $this->output[] = "\n<p>\n";
            $this->space = true;
            $this->startParagraph = false;
            $this->inParagraph = true;
            $this->currentLineLength = 0;
        }

        $newline = false;
        if (preg_match('/\n([^\n]*)$/', $string, $matches)) {
            $newline = true;
            $lineLength = strlen($matches[1]);
        } else {
            $lineLength = strlen($string);
        }

        if ($this->currentLineLength > 0 && $this->currentLineLength + $lineLength > self::LINE_BREAK_POINT && $this->space) {
            $this->output[] = "\n";
            $this->currentLineLength = 0;
        }
    	
        $this->output[] = $string;
        $this->log("Generate inline '%s'", $string);
        //$this->space = true;

        if ($newline) {
            $this->currentLineLength = $lineLength;
        } else {
            $this->currentLineLength += $lineLength;
        }
    }

    public function generate($string, $break = true) {
    	if ($this->space && \ctype_space(substr($string, 0, 1))) {
            $string = ltrim($string);
    	}

        if (empty($string)) return;

        if ($this->startParagraph && !$this->inParagraph) {
            $this->output[] = "\n<p>\n";
            $this->space = true;
            $this->startParagraph = false;
            $this->inParagraph = true;
            $this->currentLineLength = 0;
        }

        $this->log("Generate string '%s'", $string);

        if ($break && $this->currentLineLength > self::LINE_BREAK_POINT) {
            if ($this->space) {
                $this->log("Got space, can safely split.");
                $this->output[] = "\n";
                $this->currentLineLength = 0;
            } else {
                $split = preg_split("/\s/", $string, 1);
                if (isset($split[1])) {
                    $this->log("Split after '%s' and before '%s'.", $split[0], $split[1]);
                    $this->output[] = $split[0];
                    $this->output[] = "\n";
                    $this->currentLineLength = 0;
                    $string = $split[1];
                }
            }
        }

        if (!empty($string)) {
    	   $this->output[] = $string;
           $this->currentLineLength += strlen($string);
        }

        $this->space = \ctype_space(substr($string, -1));
    }

    public function newParagraph() {
        if ($this->inParagraph) {
            $this->output[] = "\n</p>\n";
            $this->space = true;
            $this->inParagraph = false;
            $this->currentLineLength = 0;
        }

        $this->startParagraph = true;
    }
}

class WikiFormatterSimple extends WikiFormatter {
    protected $blockFormatter;

    function __construct() {
        require_once "lib/format/Blockquote.php";
        require_once "lib/format/Line.php";
        require_once "lib/format/Lists.php";
        require_once "lib/format/Line.php";
        require_once "lib/format/Table.php";
        require_once "lib/format/Block.php";

        require_once "lib/format/BasicFormat.php";
        require_once "lib/format/Link.php";
        require_once "lib/format/Image.php";

        $this->installLineTrigger(new format\Blockquote());
        $this->installLineTrigger(new format\Line());
        $this->installLineTrigger(new format\Lists());
        $this->installLineTrigger(new format\Table());
        $this->installLineTrigger($this->blockFormatter = new format\Block());

        $this->installInlineTrigger(format\BasicFormat::Create("**", "strong"));
        $this->installInlineTrigger(format\BasicFormat::Create("//", "em"));
        $this->installInlineTrigger(format\BasicFormat::Create("__", "ins"));
        $this->installInlineTrigger(format\BasicFormat::Create("--", "del"));
        $this->installInlineTrigger(format\BasicFormat::Create("''", "code"));
        $this->installInlineTrigger(new format\Link());
        $this->installInlineTrigger(new format\LinkInText());
        $this->installInlineTrigger(new format\PlainText());
        $this->installInlineTrigger(new format\Image());
        $this->installInlineTrigger(new format\LineBreak());
    }
}

class WikiFormatterFull extends WikiFormatterSimple {
    protected $variables;

    function getVariables() {
        return $this->variables;
    }

    function __construct() {
        parent::__construct();

        require_once "lib/format/Heading.php";
        require_once "lib/format/Block.php";
        require_once "lib/format/Variables.php";
        require_once "lib/format/Template.php";

        $this->installLineTrigger($heading = format\Heading::Create(2));
        $this->installLineTrigger(format\Heading::Create(3));
        $this->installLineTrigger(format\Heading::Create(4));
        $this->installLineTrigger(format\Heading::Create(5));
        $this->installLineTrigger(format\Heading::Create(6));

        $this->variables = new format\Variables();
        $this->variables->register($this->blockFormatter);

        $this->blockFormatter->registerBlockFormatter("toc", array($heading, "generateToc"));

        $tmpl = new format\Template();
        $tmpl->register($this->blockFormatter);

        $this->installInlineTrigger(new format\InlineVariable($this->variables));
    }
}
