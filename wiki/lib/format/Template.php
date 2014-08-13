<?php

namespace lib\formatter\format;

require_once "lib/format/Block.php";
require_once "lib/format/Link.php";

class Template {
    public function register(Block $format) {
        $format->registerBlockFormatter("template", array($this, "template"));
    }

    public function template(Context $ctx, $params) {
        $cls = get_class($ctx->getFormatter());
        // Format using same formatter as was used to render the page.
        $f = new $cls();
        $f->getVariables()->multiDefCall($ctx, array());

        if (!isset($params[1])) return;
        $templateName = $params[1];

        // Try to find template
        $be = \Config::Get("__Backend");
        try {
            $page = $be->loadPage(array(sprintf("template:%s", $templateName)), array("body_wiki"));
            $out = $f->format($page->body_wiki);
            $ctx->generateHTML($out);

            Link::addLink($ctx, (int)$page->getId());
        } catch (\storage\PageNotFoundException $e) {
            $ctx->log("Page template:%s was not found.", $templateName);
            Link::addLink($ctx, $templateName);
        }
    }
}

