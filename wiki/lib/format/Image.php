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

            // Need to get image to retrieve dimensions.
            if (preg_match('|://|', $url)) {
                $ctx->log("Get image size for ".$url);
                $size = getimagesize($url);
            } elseif (preg_match('|^/|', $url)) {
                $root = dirname($_SERVER["SCRIPT_NAME"]);
                $oldurl = $url;
                $url = $root.$url;

                $myfile = dirname($_SERVER["SCRIPT_FILENAME"]);
                $ctx->log("Get image size for ".$myfile.$url);

                $size = getimagesize($myfile.$oldurl);
            } elseif (preg_match('|:|', $url)) {
                // Ask URL plugin to retrieve the image data.
            }

            if (!isset($size)) return NULL;

            foreach ($params as $param) {
                if (preg_match('/^([0-9]+)(|px|em)x([0-9]+)(|px|em)$/i', $param, $matches)) {
                    if (empty($matches[2])) $matches[2] = "px";
                    if (empty($matches[4])) $matches[4] = "px";

                    $css[] = sprintf("width: %d%s;", $matches[1], strtolower($matches[2]));
                    $css[] = sprintf("height: %d%s;", $matches[3], strtolower($matches[4]));
                } elseif (preg_match('/^([0-9]+(\.[0-9]+)?)%$/', $param, $matches)) {
                    $width = round($size[0] / 100.0 * (double)$matches[1]);
                    $css[] = sprintf("width: %dpx;", $width);
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

        // Image with caption
        list($url, $caption) = $splitted;
        list($html, $align) = $this->generateImgTag($ctx, $url, true);

        // Not correct URL of image, render as plain text.
        if (is_null($html)) {
            $ctx->generate("((");
            $ctx->inlineFormat(substr($matches[0], 2));
            return;
        }

        $class = array("image");
        
        if (isset($caption) && !empty($caption)) {
            $class[] = "withCaption";
        }

        if (!is_null($align)) {
            switch ($align) {
                case "left":
                    $class[] = "alignLeft";
                    break;

                case "right":
                    $class[] = "alignRight";
                    break;
            }
        }

        // TODO: Move styles to CSS
        $ctx->generateHTMLInline("<span class=\"".implode(" ", $class)."\" style=\"display: table; width: 1%\"><span style=\"height: auto; overflow: hidden;\">\n\t");
        $ctx->generateHTMLInline($html);

        if (isset($caption) && !empty($caption)) {
            $ctx->generateHTMLInline("\n\t<br />");
            $ctx->generateHTMLInline("\n\t<span class=\"caption\">\n\t\t");
            $ctx->inlineFormat($caption);
            $ctx->generateHTMLInline("\n\t</span>");
        }

        $ctx->generateHTMLInline("\n</span></span>\n");
    }
}
