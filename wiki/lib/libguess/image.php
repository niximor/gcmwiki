<?php

namespace lib\libguess;

require_once "lib/libguess/guess.php";

abstract class ImageType implements FileType {
    protected $width;
    protected $height;

    public function getClass() {
        return FileType::CLASS_IMAGE;
    }

    public function getWidth() {
        return $this->width;
    }

    public function getHeight() {
        return $this->height;
    }

    protected function setWidth($width) {
        $this->width = $width;
    }

    protected function setHeight($height) {
        $this->height = $height;
    }

    protected function setGeometry($width, $height) {
        $this->setWidth($width);
        $this->setHeight($height);
    }
}

class Png extends ImageType {
    public function getMime() {
        return "image/png";
    }

    public function getExtension() {
        return "png";
    }

    public function isMatch($fileName, $originalFileName) {
        // source: http://www.libpng.org/pub/png/spec/1.2/PNG-Rationale.html#R.PNG-file-signature
        $f = fopen($fileName, "rb");
        $isPng = fread($f, 8) == "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a";

        if (!$isPng) {
            fclose($f);
            return false;
        }

        // TODO: What endianess the png expects?

        // TODO: unpack returns array...
        $hdrLen = unpack("N", fread($f, 4))[1];

        // Here we have PNG file, load size.
        $hdr = fread($f, 4) == "IHDR";
        if (!$hdr) return false;

        $hdr = fread($f, $hdrLen);
        $width = unpack("N", substr($hdr, 0, 4))[1];
        $height = unpack("N", substr($hdr, 4, 4))[1];

        $this->setGeometry($width, $height);

        fclose($f);
        return true;
    }
}

class Jpeg extends ImageType {
    public function getMime() {
        return "image/jpeg";
    }

    public function getExtension() {
        return "jpg";
    }

    public function isMatch($fileName, $originalFileName) {
        $gotSize = false;

        $f = fopen($fileName, "rb");
        while (!feof($f)) {
            $marker = fread($f, 2);
            switch ($marker) {
                case "\xFF\xD8": // SOI
                case "\xFF\xD0": // RST0
                case "\xFF\xD1": // RST1
                case "\xFF\xD2": // RST2
                case "\xFF\xD3": // RST3
                case "\xFF\xD4": // RST4
                case "\xFF\xD5": // RST5
                case "\xFF\xD6": // RST6
                case "\xFF\xD7": // RST7
                    break;

                // Skip unimportant segments.
                case "\xFF\xC4": // DHT
                case "\xFF\xDB": // DQT
                case "\xFF\xDA": // SOS
                case "\xFF\xE0": // APP0
                case "\xFF\xE1": // APP1
                case "\xFF\xE2": // APP2
                case "\xFF\xE3": // APP3
                case "\xFF\xE4": // APP4
                case "\xFF\xE5": // APP5
                case "\xFF\xE6": // APP6
                case "\xFF\xE7": // APP7
                case "\xFF\xE8": // APP8
                case "\xFF\xE9": // APP9
                case "\xFF\xEA": // APPA
                case "\xFF\xEB": // APPB
                case "\xFF\xEC": // APPC
                case "\xFF\xED": // APPD
                case "\xFF\xEE": // APPE
                case "\xFF\xEF": // APPF
                case "\xFF\xFE": // COM
                    $len = unpack("n", fread($f, 2))[1];
                    fseek($f, $len - 2, SEEK_CUR);
                    break;

                case "\xFF\xC0":
                case "\xFF\xC1":
                case "\xFF\xC2":
                case "\xFF\xC3":
                case "\xFF\xC5":
                case "\xFF\xC6":
                case "\xFF\xC7":
                case "\xFF\xC9":
                case "\xFF\xCA":
                case "\xFF\xCB":
                case "\xFF\xCD":
                case "\xFF\xCE":
                case "\xFF\xCF":
                    $len = unpack("n", fread($f, 2))[1];
                    $precision = fread($f, 1);
                    $height = unpack("n", fread($f, 2))[1];
                    $width = unpack("n", fread($f, 2))[1];

                    $this->setGeometry($width, $height);
                    $gotSize = true;

                    break; // Start of frame (baseline)

                case "\xFF\xD9": // EOI
                    // End of image means, that we still don't have an image, so it is some faulty JPEG,
                    // where it is correct for us to return false and ignore it.
                    fclose($f);
                    return false;
            }

            if ($gotSize) break;
        }

        fclose($f);
        return true;
    }
}

class Gif extends ImageType {
    public function getMime() {
        return "image/gif";
    }

    public function getExtension() {
        return "gif";
    }

    public function isMatch($fileName, $originalFileName) {
        $f = fopen($fileName, "rb");
        $version = fread($fileHandle, 6);

        // GIF89a and GIF87a
        if ($version != "\x47\x49\x46\x38\x39\x61" && $version != "\x47\x49\x46\x38\x37\x61") {
            fclose($f);
            return false;
        }

        $width = unpack("v", fread($fileHandle, 2))[1];
        $height = unpack("v", fread($fileHandle, 2))[1];

        fclose($f);

        $this->setGeometry($width, $height);
        return true;
    }
}

Guess::registerType(new Png());
Guess::registerType(new Jpeg());
Guess::registerType(new Gif());

