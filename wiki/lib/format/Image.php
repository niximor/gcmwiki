<?php

namespace lib\formatter\format;

class Image extends InlineTrigger {
    function getRegExp(Context $ctx) {
        return '/\(\((.*?)\)\)/';
    }

    protected function generateImgTag(Context $ctx, $url, $inBox = false) {
        $splitted = preg_split('/#/', $url, 2);
        $url = htmlspecialchars($splitted[0]);
        $align = NULL;

        if (isset($splitted[1])) {
            $params = preg_split('/&/', $splitted[1]);
            $css = array();

            foreach ($params as $param) {
                if (preg_match('/^([0-9]+)(|px|em|%)x([0-9]+)(|px|em|%)$/i', $param, $matches)) {
                    if (empty($matches[2])) $matches[2] = "px";
                    if (empty($matches[4])) $matches[4] = "px";

                    $css[] = sprintf("width: %d%s;", $matches[1], strtolower($matches[2]));
                    $css[] = sprintf("height: %d%s;", $matches[3], strtolower($matches[4]));
                } elseif (preg_match('/^(left|right)$/i', $param, $matches)) {
                    if (!$inBox) {
                        $css[] = sprintf("float: %s;", strtolower($matches[1]));
                    } else {
                        $align = strtolower($matches[1]);
                    }
                }
            }

            if (!empty($css)) {
                $html = "<img src=\"".$url."\" style=\"".implode(" ", $css)."\" />";
            } else {
                $html = "<img src=\"".$url."\" />";
            }
        } else {
            $html = "<img src=\"".$url."\" />";
        }

        if (!$inBox) {
            return $html;
        } else {
            return array($html, $align);
        }
    }

    function callback(Context $ctx, $matches) {
        $splitted = preg_split('/\|/', $matches[1], 2);
        print_r($splitted);
        if (isset($splitted[1])) {
            // Image with caption
            list($url, $caption) = $splitted;
            list($html, $align) = $this->generateImgTag($ctx, $url, true);

            if (!is_null($align)) {
                $ctx->generateHTMLInline("<span class=\"image\" style=\"float: ".$align."\">");
            } else {
                $ctx->generateHTMLInline("<span class=\"image\">");
            }
            $ctx->generateHTMLInline($html);
            $ctx->generateHTMLInline("<br />");
            $ctx->inlineFormat($caption);
            $ctx->generateHTMLInline("</span>");
        } else {
            // Plain image
            $url = $splitted[0];
            $ctx->generateHTMLInline($this->generateImgTag($ctx, $url));
        }
    }
}
