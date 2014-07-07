<?php

namespace lib\formatter\format;

class LinkInText extends InlineTrigger {
    function getRegExp(Context $ctx) {
        if (!($ctx instanceof LinkContext)) {
            return '/(((http|https|ftp):\/\/([a-zA-Z][a-zA-Z0-9]*(\.[a-zA-Z][a-zA-Z0-9]*)*))|(www\.([a-zA-Z][a-zA-Z0-9]*(\.[a-zA-Z][a-zA-Z0-9]*)*)))/';
        } else {
            return '/$^/';
        }
    }

    function callback(Context $ctx, $matches) {
        // Add missing http
        $url = $matches[0];
        if (empty($matches[3])) $url = "http://".$matches[0];

        // Strip protocol from the text of link.
        $text = $matches[0];
        if (!empty($matches[3])) $text = $matches[4];

        $ctx->generateHTMLInline(sprintf('<a href="%s" class="external">', htmlspecialchars($url)));
        $ctx->generate(htmlspecialchars($text));
        $ctx->generateHTMLInline("</a>");
    }
}

class LinkContext extends Context {
}

class Link extends InlineTrigger {
    function getRegExp(Context $ctx) {
        if (!($ctx instanceof LinkContext)) {
            return '/\[\[(.*?)\]\]/';
        } else {
            return '/$^/'; // Never match if we are inside the link.
        }
    }

    function callback(Context $ctx, $matches) {
        $pos = strpos($matches[1], "|");
        if ($pos !== false) {
            $url = substr($matches[1], 0, $pos);
            $text = substr($matches[1], $pos + 1);
        } else {
            $url = $text = $matches[1];
        }

        $generated = false;
        if (!preg_match('/[a-zA-Z][a-zA-Z0-9]:\/\//', $url)) {
            $be = \Config::Get("__Backend");
            try {
                $page = $be->loadPage(preg_split('|/|', $url));

                $ctx->generateHTMLInline(sprintf('<a href="%s" class="page">', htmlspecialchars($url)));

                if ($text != $url) {
                    $newctx = new LinkContext($ctx);
                    $newctx->inlineFormat($text);
                } else {
                    $ctx->generate(htmlspecialchars($page->getName()));
                }

                $ctx->generateHTML('</a>');
                $generated = true;
            } catch (\storage\PageNotFoundException $e) {
                if (preg_match('/^[a-zA-Z][a-zA-Z0-9]*\.[a-zA-Z][a-zA-Z0-9]*(\.[a-zA-Z][a-zA-Z0-9]*)+$/', $url)) {
                    $url = "http://".$url;
                } else {
                    $ctx->generateHTMLInline(sprintf('<a href="%s" class="page notfound">', htmlspecialchars($url)));
                    $ctx->generate(htmlspecialchars($text));
                    $ctx->generateHTMLInline('</a>');
                    $generated = true;
                }
            }
        }

        if (!$generated) {
            $ctx->generateHTMLInline(sprintf('<a href="%s" class="external">', htmlspecialchars($url)));
            $newctx = new LinkContext($ctx);
            $newctx->inlineFormat($text);
            $ctx->generateHTMLInline('</a>');
        }
    }

    static function testSuite() {
        self::testFormat("Link na www.google.com a http://www.google.com, stejne tak taky [[www.google.com]] a nebo [[http://www.google.com]] a taky [[www.google.com|s popiskem]].",
            'Link na <a href="http://www.google.com" class="external">www.google.com</a> a <a href="http://www.google.com" class="external">www.google.com</a>, stejne tak taky <a href="http://www.google.com" class="external">www.google.com</a> a nebo <a href="http://www.google.com" class="external">http://www.google.com</a> a taky <a href="http://www.google.com" class="external">s popiskem</a>.');
    }
}

