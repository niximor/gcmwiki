<?php

namespace lib\formatter;

require_once "lib/format/Context.php";

class WikiFormatter {
    protected $output = array();
    protected $space = true;
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
        $ctx = new format\RootContext($this);
        foreach ($lines as $line) {
            $ctx->formatLine($ctx, preg_replace('/\r$/', '', $line));
        }

        while ($ctx->getTrigger()) {
            $ctx->getTrigger()->callEnd($ctx);
            $ctx = $ctx->getParent();
        }

        return implode("", $this->output);
    }

    public function generateHTML($string) {
        $this->output[] = $string;
        $this->log("Generate ".$string);
        $this->space = true;
    }

    public function generate($string) {
    	if (empty($string)) return;

    	if ($this->space) {
    		$this->space = false;
    	} else {
    		$this->output[] = " ";
    	}
    	$this->output[] = $string;
    	$this->log("Generate %s", $string);
    }
}

