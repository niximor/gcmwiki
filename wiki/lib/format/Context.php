<?php

namespace lib\formatter\format;

require_once "lib/format/Trigger.php";

class Context {
    protected $parent;
    protected $trigger;

    public function __construct(Context $parent, LineTrigger $trigger = NULL) {
        $this->parent = $parent;
        $this->trigger = $trigger;
    }

    public function getParent() {
        return $this->parent;
    }

    public function getFormatter() {
        return $this->getParent()->getFormatter();
    }

    public function getTrigger() {
        return $this->trigger;
    }

    public function generateHTML($html) {
        $this->getFormatter()->generateHTML($html);
    }

    public function generate($text) {
        $this->getFormatter()->generate($text);
    }
    
    public function generateHTMLInline($html) {
        $this->getFormatter()->generateHTMLInline($html);
    }

    public function replaceSpecials($line) {
        return htmlspecialchars(preg_replace('/\s+/', ' ', $line));
    }
    
    public function formatLines(Context &$ctx, $lines) {
        foreach ($lines as $line) {
            printf("Render line '%s' with context %s\n", $line, get_class($ctx));
            $ctx->formatLine($ctx, $line);
            printf("Resulting context is %s\n", get_class($ctx));
        }
        
        while ($ctx && $ctx != $this) {
            if ($ctx->getTrigger()) {
                printf("Closing context %s\n", get_class($ctx));
                $ctx->getTrigger()->callEnd($ctx);
            }
            $ctx = $ctx->getParent();
        }
    }

    public function formatLine(Context &$ctx, $line) {
        if (!is_null($trigger = $ctx->getTrigger())) {
            $re = $trigger->getEndRegExp($ctx);
            if (preg_match($re, $line)) {
                $trigger->callEnd($ctx);
                $ctx = $ctx->getParent();
            }
        }

        foreach (\lib\formatter\WikiFormatter::$lineTriggers as $trigger) {
            if (preg_match($trigger->getRegExp($ctx), $line, $matches)) {
                if ($ctx->getTrigger() != $trigger) {
                    $ctx = $trigger->getContext($ctx, $line, $matches);
                }

                $trigger->callLine($ctx, $line, $matches);
                return;
            }
        }

        $ctx->inlineFormat($line);
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
            $this->inlineFormat(substr($text, $matches[0][1] + strlen($matches[0][0])));
        } else {
            $this->generate($this->replaceSpecials($text));
        }
    }
}

class RootContext extends Context {
    function __construct(\lib\formatter\WikiFormatter $formatter) {
        $this->formatter = $formatter;
        parent::__construct($this);
    }

    function getParent() {
        return $this;
    }

    function getFormatter() {
        return $this->formatter;
    }
}

