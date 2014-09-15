<?php

namespace lib\formatter\format;

class Redirect {
    function register($block) {
        $block->registerBlockFormatter("redirect", array($this, "setRedirect"));
    }

    function setRedirect(Context $ctx, $params) {
        if (isset($params[1])) {
            $relatedPage = $ctx->getRoot()->getPage();

            // Redirect works only on pages...
            if (!is_null($relatedPage)) {
                $currentPagePath = $relatedPage->getPath();
                $currentPagePath = array_splice($currentPagePath, 0, -1);

                // If specified redirect is absolute path, ignore current page path.
                if (substr($params[1], 0, 1) == "/") {
                    $currentPagePath = array();
                    // Strip leading and trailing slashes
                    $params[1] = preg_replace(',^/+|/+$,', "", $params[1]);
                }

                $pathParts = preg_split("|/+|", $params[1]);
                $currentPagePath = array_merge($currentPagePath, $pathParts);

                $be = \Config::Get("__Backend");
                try {
                    $redirectTo = $be->loadPage($currentPagePath);
                    $relatedPage->setRedirect_to($redirectTo->getId());
                } catch (\storage\PageNotFoundException $e) {
                    // TODO: What to do if target page does not exists?
                }
            }
        }
    }
}
