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

    public $debug = false;

    public static $lineTriggers = array();
    public static $inlineTriggers = array();

	public static function installLineTrigger(format\LineTrigger $trigger) {
		self::$lineTriggers[] = $trigger;
	}

    public static function installInlineTrigger(format\InlineTrigger $trigger) {
        self::$inlineTriggers[] = $trigger;
    }

    public function log($msg) {
        if ($this->debug) {
            echo htmlspecialchars(call_user_func_array("sprintf", func_get_args()));
            echo "\n";
        }
    }

    public function format($text) {
        $this->output = array();

        $lines = explode("\n", $text);
        $ctx = $root = new format\RootContext($this);
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

