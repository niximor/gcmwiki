<?php

namespace lib\formatter\format;

require_once "lib/format/Trigger.php";

class Context {
    protected $parent;
    protected $trigger;
    protected $root;
    private $_closed = false;

    public function __construct(Context $parent, LineTrigger $trigger = NULL) {
        $this->parent = $parent;
        $this->trigger = $trigger;
        $this->root = $parent->getRoot();
    }

    public function getParent() {
        return $this->parent;
    }

    function setParent(Context $parent) {
        $this->parent = $parent;
    }
    
    public function getRoot() {
        return $this->root;
    }

    public function getFormatter() {
        return $this->getParent()->getFormatter();
    }

    public function getTrigger() {
        return $this->trigger;
    }

    // Called when context gets destroyed.
    public function close() {
        $this->_closed = true;
    }

    function __destruct() {
        if (!$this->_closed) $this->close();
    }

    public function generateHTML($html) {
        $this->getParent()->generateHTML($html);
    }

    public function generate($text) {
        $this->getParent()->generate($text);
    }
    
    public function generateHTMLInline($html) {
        $this->getParent()->generateHTMLInline($html);
    }
    
    public function log() {
        call_user_func_array(array($this->getFormatter(), "log"), func_get_args());
    }

    public function replaceSpecials($line) {
        return htmlspecialchars(preg_replace('/\s+/', ' ', $line));
    }
    
    public function formatLines(Context $ctx, $lines) {
        foreach ($lines as $line) {
            $ctx->formatLine($ctx, $line);
        }
        
        while ($ctx && $ctx != $this) {
            if ($ctx->getTrigger()) {
                $ctx->getTrigger()->callEnd($ctx);
            }
            $ctx->close();

            if ($ctx instanceof RootContext) break; // Avoid infinite loop.

            $ctx = $ctx->getParent();
        }
    }

    public function formatLine(Context &$ctx, $line) {
        if (!is_null($trigger = $ctx->getTrigger())) {
            $re = $trigger->getEndRegExp($ctx);
            if (preg_match($re, $line)) {
                $trigger->callEnd($ctx);
                $ctx = $ctx->getParent();

                if ($ctx instanceof RootContext) {
                    $this->getFormatter()->newParagraph();
                }
            } else {
                if (!preg_match($ctx->getTrigger()->getRegExp($ctx), $line, $matches)) {
                    $matches = NULL;
                }
                $ctx->getTrigger()->callLine($ctx, $line, $matches);
                return;
            }
        }

        foreach (\lib\formatter\WikiFormatter::$lineTriggers as $trigger) {
            if (preg_match($trigger->getRegExp($ctx), $line, $matches)) {
                $ctx = $trigger->getContext($ctx, $line, $matches);
                $trigger->callLine($ctx, $line, $matches);
                return;
            }
        }

        if (!$this->getRoot()->__isFirstLine) {
            $this->generate(" ");
        } else {
            $this->getRoot()->__isFirstLine = false;
        }

        $this->log("Process '%s' with context %s", $line, get_class($ctx));
        if (empty($line) && $ctx instanceof RootContext) {
            $ctx->getFormatter()->newParagraph();
        } else {
            $ctx->inlineFormat($line);
        }
    }

    public function inlineFormat($text) {
        $lowest = NULL;
        $lowestIndex = 0;
        foreach (\lib\formatter\WikiFormatter::$inlineTriggers as $trigger) {
            if (preg_match($trigger->getRegExp($this), $text, $matches, PREG_OFFSET_CAPTURE)) {
                if (is_null($lowest) || $lowestIndex > $matches[0][1]) {
                    $lowest = $trigger;
                    $lowestIndex = $matches[0][1];
                }
            }
        }

        if (!is_null($lowest)) {
            preg_match($lowest->getRegExp($this), $text, $matches, PREG_OFFSET_CAPTURE);
            $this->generate($this->replaceSpecials(substr($text, 0, $matches[0][1])));

            $matchToCall = array();
            foreach ($matches as $match) {
                $matchToCall[] = $match[0];
            }

            $lowest->callback($this, $matchToCall);
            $toFormat = substr($text, $matches[0][1] + strlen($matches[0][0]));
            if (!empty($toFormat)) {
                $this->inlineFormat($toFormat);
            }
        } else {
            $this->generate($this->replaceSpecials($text));
        }
    }
}

abstract class CapturedLine {
    protected $data;

    function __construct($data) {
        $this->data = $data;
    }

    abstract function generate(Context $ctx);
}

class CapturedHTMLLine extends CapturedLine {
    function generate(Context $ctx) { $ctx->generateHTML($this->data); }
}

class CapturedInlineHTMLLine extends CapturedHTMLLine {
    function generate(Context $ctx) { $ctx->generateHTMLInline($this->data); }
}

class CapturedTextLine extends CapturedLine {
    function generate(Context $ctx) { $ctx->generate($this->data); }
}

class CapturingContext extends Context {
    protected $lines = array();

    function generateHTML($html) {
        if (!empty($html)) {
            $this->log("Captured block '%s'", trim($html));
        }

        $this->lines[] = new CapturedHTMLLine($html);
    }

    function generateHTMLInline($html) {
        if (!empty($html)) {
            $this->log("Captured inline '%s'", trim($html));
        }

        $this->lines[] = new CapturedInlineHTMLLine($html);
    }

    function generate($text) {
        if (!empty($text)) {
            $this->log("Captured text '%s'", trim($text));
        }

        $this->lines[] = new CapturedTextLine($text);
    }

    function close() {
        $parent = $this->getParent();
        foreach ($this->lines as $line) {
            $line->generate($parent);
        }

        parent::close();
    }
}

class RootContext extends Context {
    protected $__isFirstLine = true;

    function __construct(\lib\formatter\WikiFormatter $formatter) {
        $this->formatter = $formatter;
        parent::__construct($this);
    }

    function getParent() {
        return $this;
    }
    
    function getRoot() {
        return $this;
    }

    function getFormatter() {
        return $this->formatter;
    }

    function generateHTML($html) {
        $this->getFormatter()->generateHTML($html);
    }

    function generateHTMLInline($html) {
        $this->getFormatter()->generateHTMLInline($html);
    }

    function generate($text) {
        $this->getFormatter()->generate($text);
    }
}

