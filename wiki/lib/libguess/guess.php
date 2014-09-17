<?php

namespace lib\libguess;

class Guess {
    private function __construct() {}

    protected static $types = array();

    static function guessType($fileName, $originalName) {
        $found = false;

        foreach (self::$types as $type) {
            if ($type->isMatch($fileName, $originalName)) {
                $found = true;
                break;
            }
        }

        if ($found) {
            return $type;
        } else {
            return new BinaryFile();
        }
    }

    static function registerType(FileType $type) {
        self::$types[] = $type;
    }
}

/**
 * Class that guesses what type the file is.
 */
interface FileType {
    const CLASS_IMAGE = 1;
    const CLASS_VIDEO = 2;
    const CLASS_AUDIO = 3;
    const CLASS_TEXT = 4;
    const CLASS_BINARY = 5;

    /**
     * MIME type for the file
     */
    public function getMime();

    /**
     * Extension the file should have, NULL if no exact extension is available.
     */
    public function getExtension();

    /**
     * Class of the file. One of Guess::CLASS_* constants.
     */
    public function getClass();

    /**
     * Return true if file specified by given file handle matches the type.
     * @return true if stream matches, false otherwise.
     */
    public function isMatch($fileName, $originalName);
}

require_once "lib/libguess/image.php";
require_once "lib/libguess/video.php";
require_once "lib/libguess/audio.php";

/**
 * Fallback
 */
class BinaryFile implements FileType {
    public function getMime() {
        return "application/octet-stream";
    }

    public function getExtension() {
        return NULL;
    }

    public function getClass() {
        return Guess::TYPE_BINARY;
    }

    public function isMatch($fileName, $originalName) {
        return true;
    }
}

