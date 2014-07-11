<?php

namespace lib\formatter;

require_once "lib/format/Context.php";

class WikiFormatter {
    protected $output = array();
    protected $space = true;
    protected $inParagraph = false;
    protected $startParagraph = true;

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
            $this->log("End paragraph");
            $this->output[] = "\n</p>\n";
        }

        return implode("", $this->output);
    }

    public function generateHTML($string) {
        if (empty($string)) return;

        if ($this->inParagraph) {
            $this->log("End paragraph");
            $this->output[] = "\n</p>\n";
            $this->inParagraph = false;
        }
        $this->startParagraph = false;
        
        $this->output[] = $string;
        $this->log("Generate block '%s'", trim($string));
        $this->space = true;
    }
    
    public function generateHTMLInline($string) {
        if (empty($string)) return;

        if ($this->startParagraph && !$this->inParagraph) {
            $this->log("Start paragraph");
            $this->output[] = "\n<p>\n";
            $this->startParagraph = false;
            $this->inParagraph = true;
        }
    	
        $this->output[] = $string;
        $this->log("Generate inline '%s'", $string);
        //$this->space = true;
    }

    public function generate($string) {
    	if ($this->space && \ctype_space(substr($string, 0, 1))) {
            $string = ltrim($string);
    	}

        if (empty($string)) return;

        if ($this->startParagraph && !$this->inParagraph) {
            $this->log("Start paragraph");
            $this->output[] = "\n<p>\n";
            $this->startParagraph = false;
            $this->inParagraph = true;
        }
    	
        $this->output[] = $string;
    	$this->log("Generate string '%s'", $string);
    	$this->space = \ctype_space(substr($string, -1));
    }

    public function newParagraph() {
        if ($this->inParagraph) {
            $this->log("End paragraph");
            $this->output[] = "\n</p>\n";
            $this->inParagraph = false;
        }

        $this->startParagraph = true;
    }
}

