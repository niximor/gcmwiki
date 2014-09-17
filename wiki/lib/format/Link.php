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

    public static function addLink($ctx, $link) {
        $root = $ctx->getRoot();
        if (!isset($root->WIKI_LINKS)) {
            $root->WIKI_LINKS = array();
        }
        $root->WIKI_LINKS[] = $link;
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

            $pathParts = preg_split('|/|', ($url[0] == "/")?substr($url, 1):$url);

            // Pages are searched from current level.
            if ($url[0] != "/") {
                $contextPage = $ctx->getRoot()->getPage();
                if (!is_null($contextPage)) {
                    $currentPagePath = $contextPage->getPath();
                    // By default, links without / are on the same level
                    // as current page, so we need to split current page
                    // name from currentPagePath. That, in fact, creates
                    // parentPagePath.
                    $currentPagePath = array_splice($currentPagePath, 0, -1);
                } else {
                    $currentPagePath = array();
                }

                $fullPageUrl = array_merge($currentPagePath, $pathParts);
            } else {
                $fullPageUrl = $pathParts;
            }

            try {
                // If URL contains slash, pages are searched from root.
                $page = $be->loadPage($fullPageUrl, NULL, NULL, false);

                if ($text == $url) $text = NULL;

                $url = implode("/", $fullPageUrl);

                $root = dirname($_SERVER["SCRIPT_NAME"]);
                if ($url[0] == "/") {
                    $url = $root.$url;
                } else {
                    $url = $root."/".$url;
                }

                $ctx->generateHTMLInline(sprintf('<a href="%s" class="page">', htmlspecialchars($url)));

                if (!is_null($text)) {
                    $newctx = new LinkContext($ctx);
                    $newctx->inlineFormat($text);
                    $ctx->log("Name passed in format string.");
                } else {
                    $ctx->log("Get name of link from page name.");
                    $ctx->generate(htmlspecialchars($page->getName()));
                }

                $ctx->generateHTML('</a>');
                $generated = true;

                $this->addLink($ctx, (int)$page->getId());
            } catch (\storage\PageNotFoundException $e) {
                if (preg_match('/^[a-zA-Z][a-zA-Z0-9]*\.[a-zA-Z][a-zA-Z0-9]*(\.[a-zA-Z][a-zA-Z0-9]*)+$/', $url)) {
                    $url = "http://".$url;
                } else {
                    $root = dirname($_SERVER["SCRIPT_NAME"]);

                    if ($text == $url) {
                        $text = $pathParts[count($pathParts) - 1];
                    }

                    $url = implode("/", $fullPageUrl);

                    if ($url[0] == "/") {
                        $fullurl = $root.$url;
                    } else {
                        $fullurl = $root."/".$url;
                    }

                    $ctx->generateHTMLInline(sprintf('<a href="%s" class="page notfound">', htmlspecialchars($fullurl)));
                    $ctx->generate(htmlspecialchars($text));
                    $ctx->generateHTMLInline('</a>');
                    $generated = true;

                    $this->addLink($ctx, preg_split('|/|', $url));
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

    static function testSuite(\lib\formatter\WikiFormatter $f) {
        self::testFormat($f, "Link na www.google.com a http://www.google.com, stejne tak taky [[www.google.com]] a nebo [[http://www.google.com]] a taky [[www.google.com|s popiskem]].",
<<<EOF

<p>
Link na <a href="http://www.google.com" class="external">www.google.com</a> a
<a href="http://www.google.com" class="external">www.google.com</a>, stejne tak taky
<a href="http://www.google.com" class="external">www.google.com</a> a nebo
<a href="http://www.google.com" class="external">http://www.google.com</a> a taky
<a href="http://www.google.com" class="external">s popiskem</a>.
</p>

EOF
);
    }
}

