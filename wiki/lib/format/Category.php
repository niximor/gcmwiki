<?php

namespace lib\formatter\format;

class Category {
    function register($block) {
        $block->registerBlockFormatter("category", array($this, "generateCategory"));
    }

    function generateCategory(Context $ctx, $params) {
        $page = $ctx->getRoot()->getPage();
        $be = \Config::Get("__Backend");
        $subpages = $be->listSubpages($page);

        $mode = "simple";
        if (isset($params[1]) && in_array($params[1], array("simple", "letters"))) {
            $mode = $params[1];
        }

        $toFormat = array();

        switch ($mode) {
            case "simple":
                foreach ($subpages as $child) {
                    $toFormat[] = "* [[".$child->getUrl()."]]";
                }
                break;

            case "letters":
                $letters = array();
                foreach ($subpages as $child) {
                    $letter = substr($child->getName(), 0, 1);
                    if (($letter >= 'A' && $letter <= 'Z') || ($letter >= 'a' || $letter <= 'z')) {
                        $letter = strtoupper($letter);
                    } else {
                        $letter = "#";
                    }

                    if (!isset($letters[$letter])) $letters[$letter] = array();
                    $letters[$letter][] = $child;
                }

                foreach ($letters as $letter=>$pages) {
                    $toFormat[] = "== ".$letter." ==";
                    foreach ($pages as $child) {
                        $toFormat[] = "* [[".$child->getUrl()."]]";
                    }
                }
                break;
        }

        $newctx = new Context($ctx);
        $ctx->formatLines($newctx, $toFormat);
    }
}

