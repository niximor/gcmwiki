<?php

namespace lib;

/**
 * Preview generator for attachments.
 */
class ImagePreview {
    const MODE_CONTAIN = 1; /**< Resize image so that it fits the given bounding box */
    const MODE_CROP = 2; /**< Crop image so that it fits the given bounding box */
    const MODE_FILL = 3; /**< Resize the image so that it fills the given bounding box */

    public function create($originalFile, $targetFile, $width, $height, $mode) {
        $provider = $this->getProvider();
        switch ($mode) {
            case self::MODE_CONTAIN:
                $provider->contain($originalFile, $targetFile, $width, $height);
                break;

            case self::MODE_CROP:
                $provider->crop($originalFile, $targetFile, $width, $height);
                break;

            case self::MODE_FILL:
                $provider->fill($originalFile, $targetFile, $width, $height);
                break;

            default:
                throw new \PreviewerException("Unknown mode supplied for ImagePreview::create().");
        }
    }

    protected function getProvider() {
        if (class_exists("\\Imagick")) {
            return new ImageMagickResizer();
        } elseif (function_exists("\\imagecopyresampled")) {
            return new GcResizer();
        } else {
            throw new \PreviewerException("There is not available implementation for your system. Previewer needs either ImageMagick or GD compiled in to work.");
        }
    }
}

/**
 * Image resizer interface that defines the resize mode methods. Result of each method must always be image with size
 * specified by $width and $height parameter.
 */
interface ImageResizeProvider {
    /**
     * The image is fully contained in the bounding box.
     *                 +-----+
     *                 | BOX |
     *  +-------+      +-----+
     *  |  IMG  |  ->  | IMG |
     *  +-------+      +-----+
     *                 | BOX |
     *                 +-----+
     */
    function contain($originalFile, $targetFile, $width, $height);

    /**
     * The image is not resized, only cropped to the bounding box.
     *                  +-----+
     *                  | BOX |
     *  +-------+     +-+-----+-+
     *  |  IMG  |  -> | | IMG | |
     *  +-------+     +-+-----+-+
     *                  | BOX |
     *                  +-----+
     */
    function crop($originalFile, $targetFile, $width, $height);

    /**
     * The image is resized so that it fills the bounding box and optionally overlap the sides, which are cropped.
     *                +---------+-----+---------+
     *                |         | BOX |         |
     *  +-------+     |         |     |         |
     *  |  IMG  |  -> |   IMG   | IMG |   IMG   |
     *  +-------+     |         |     |         |
     *                |         | BOX |         |
     *                +---------+-----+---------+
     */
    function fill($originalFile, $targetFile, $width, $height);
}

/**
 * Image resizer implemented using ImageMagick library.
 */
class ImageMagickResizer implements ImageResizeProvider {
    protected $image;

    protected function openImage($originalFile) {
        $this->image = new \Imagick($originalFile);
    }

    protected function saveImage($targetFile) {
        $this->image->writeImage($targetFile);
        $this->image->destroy();
        $this->image = NULL;
    }

    protected function getResampleFilter() {
        return \Imagick::FILTER_SINC;
    }

    function contain($originalFile, $targetFile, $width, $height) {
        $this->openImage($originalFile);

        $geometry = $this->image->getImageGeometry();
        $ar = (float)$geometry["width"] / (float)$geometry["height"];

        $imgWidth = $width;
        $imgHeight = $height;

        if ($imgWidth / $ar > $imgHeight) {
            $imgWidth = $imgHeight * $ar;
        } else {
            $imgHeight = $imgWidth / $ar;
        }

        // Only resize bigger images, keep smaller ones intact.
        if ($imgWidth < $geometry["width"]) {
            $this->image->resizeImage($imgWidth, $imgHeight, $this->getResampleFilter(), 0.5);
        }

        $this->saveImage($targetFile);
    }


    function crop($originalFile, $targetFile, $width, $height) {
        $this->openImage($originalFile);

        $geometry = $this->image->getImageGeometry();
        $this->image->cropImage($width, $height, ($geometry["width"] - $width) / 2, ($geometry["height"] - $height) / 2);

        $this->saveImage($targetFile);
    }

    function fill($originalFile, $targetFile, $width, $height) {
        $this->openImage($originalFile);

        $geometry = $this->image->getImageGeometry();
        $ar = (float)$geometry["width"] / (float)$geometry["height"];

        $imgWidth = $width;
        $imgHeight = $height;

        if ($ar > 1) {
            $imgWidth = $height * $ar;
            $cropX = ($imgWidth - $width) / 2;
            $cropY = 0;
        } else {
            $imgHeight = $width / $ar;
            $cropX = 0;
            $cropY = ($imgHeight - $height) / 2;
        }

        $this->image->resizeImage($imgWidth, $imgHeight, $this->getResampleFilter(), 1);
        $this->image->cropImage($width, $height, $cropX, $cropY);

        $this->saveImage($targetFile);
    }
}

/**
 * Image resizer implemented using GD library.
 */
class GdResizer implements ImageResizeProvider {
    function contain($originalFile, $targetFile, $width, $height) {

    }

    function crop($originalFile, $targetFile, $width, $height) {

    }

    function fill($originalFile, $targetFile, $width, $height) {

    }
}

/**
 * Exception thrown when Previewer fails.
 */
class PreviewerException extends \RuntimeException {
}
