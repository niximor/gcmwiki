<?php

namespace lib\formatter\format;

require_once "lib/format/Trigger.php";

class ExtendedFormat extends InlineTrigger {
    protected $startSeq;
    protected $endSeq;
    protected $startHTML;
    protected $endHTML;

    protected function __construct($startSeq, $endSeq, $startHTML, $endHTML) {
        $this->startSeq = $startSeq;
        $this->endSeq = $endSeq;
        $this->startHTML = $startHTML;
        $this->endHTML = $endHTML;
    }

    function getRegExp() {
        return sprintf('/%s(.*?)%s/', preg_quote($this->startSeq, '/'), preg_quote($this->endSeq, '/'));
    }

    function callback(Context $ctx, $matches) {
        $ctx->generateHTML($this->startHTML);
        $ctx->inlineFormat($matches[1]);
        $ctx->generateHTML($this->endHTML);
    }
}

class BasicFormat extends ExtendedFormat {
    static function Create($seq, $tag) {
        return new self($seq, $seq, "<".$tag.">", "</".$tag.">");
    }

    static function testSuite() {
        self::testFormat("Ahoj! **tohle je bold**, __tohle underline__, //tohle italika// a **//__tohle mix__//**",
            "Ahoj! <strong>tohle je bold</strong>, <ins>tohle underline</ins>, <em>tohle italika</em> a <strong><em><ins>tohle mix</ins></em></strong>");
    }
}


