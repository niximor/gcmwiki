<?php

namespace storage\FileSystem;

require_once "storage/storage.php";
require_once "lib/libpreview/Preview.php";

class DataStore implements \storage\DataStore {
    protected static function joinPath() {
        $args = func_get_args();

        $prefix = "";
        if (count($args) > 0 && !empty($args[0])) {
            if ($args[0][0] == DIRECTORY_SEPARATOR) {
                $prefix = DIRECTORY_SEPARATOR;
            }
        }

        return $prefix.implode(DIRECTORY_SEPARATOR, array_map(function($path) { return trim($path, DIRECTORY_SEPARATOR); }, $args));
    }

    protected function getFilePath(\models\Attachment $attachment, $subId) {
        $root = \Config::Get("Attachments.Location");
        return self::joinPath($root, sprintf("%04x", $attachment->getId() / 1024), sprintf("%04x", $attachment->getId() % 1024), $subId);
    }

    function load(\models\Attachment $attachment, $subId) {
        $path = $this->getFilePath($attachment, $subId);
        if (file_exists($path)) {
            return $path;
        } else {
            if ($attachment->getTypeString() == "image") {
                if (preg_match("/(contain|crop|fill)?([0-9]+)x([0-9]+)/", $subId, $matches)) {
                    $mode = false;
                    switch ($matches[1]) {
                        case "crop":
                            $mode = \lib\ImagePreview::MODE_CROP;
                            break;

                        case "fill":
                            $mode = \lib\ImagePreview::MODE_FILL;
                            break;

                        case "contain":
                        default:
                            $mode = \lib\ImagePreview::MODE_CONTAIN;
                            break;
                    }

                    if ($mode !== false) {
                        $prev = new \lib\ImagePreview();
                        $prev->create(
                            $this->getFilePath($attachment, \storage\DataStore::ORIGINAL_FILE),
                            $path,
                            $matches[2],
                            $matches[3],
                            $mode);

                        return $path;
                    }
                }
            }

            throw new \storage\FileNotFoundException();
        }
    }

    function store($localFileName, \models\Attachment $attachment, $subId) {
        $path = $this->getFilePath($attachment, $subId);
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            \mkdir($dir, intval(\Config::Get("Attachments.DirectoryMode", "0777"), 0), true);
        }

        \rename($localFileName, $path);
        \chmod($path, intval(\Config::Get("Attachments.FileMode", "0666"), 0));
    }
}
