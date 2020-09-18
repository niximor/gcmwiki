<?php

namespace lib\formatter\format;

require_once "lib/format/Block.php";
require_once "lib/path.php";

class Attachment {
    public function register(Block $format) {
        $format->registerBlockFormatter("attachment", array($this, "attachment"));
    }

    public function attachment(Context $ctx, $params) {
        $be = \Config::Get("__Backend");
        $at = $be->getAttachmentsModule();

        $filter = new \lib\XObject();

        if (!isset($params[1])) {
            return;
        }

        $page = $ctx->getRoot()->getPage();

        $attachment = $at->load($filter
            ->setName($params[1])
            ->setRelatedPageId($page->getId()),
            array("id", "name", "type_string"));

        if (count($attachment) > 0) {
            $attachment = $attachment[0];

            $root = \lib\path::getRoot();

            $ctx->generateHTML("<a href=\"".$root."/".$page->getFullUrl()."/attachments:index/".htmlspecialchars($attachment->getName())."\">");

            switch ($attachment->getTypeString()) {
                // Embed image...
                case "image":
                    if (isset($params[2])) {
                        $subId = "?s=".$params[2];
                    } else {
                        $subId = "";
                    }

                    $ctx->generateHTML("<img src=\"".$root."/".$page->getFullUrl()."/attachments:get/".htmlspecialchars($attachment->getName().$subId)."\" alt=\"".htmlspecialchars($attachment->getName())."\" />");
                    break;

                // TODO: Audio and video tags.
                case "audio":
                case "video":
                case "text":
                case "binary":
                default:
                    $ctx->generateHTMLInline(htmlspecialchars($attachment->getName()));
            }

            $ctx->generateHTML("</a>");
        }
    }
}
