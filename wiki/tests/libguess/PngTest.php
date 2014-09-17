<?php

use \lib\libguess\Png;

class PngTest extends \PHPUnit_Framework_TestCase {
    protected $pngFile;

    public function setUp() {
        $this->pngFile = fopen("php://memory", "r+b");

        // 1x1px transparent PNG file, source: http://www.1x1px.me/
        fwrite($this->pngFile, "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a\x00\x00\x00\x0d\x49\x48\x44\x52");
        fwrite($this->pngFile, "\x00\x00\x00\x01\x00\x00\x00\x01\x08\x04\x00\x00\x00\xb5\x1c\x0c");
        fwrite($this->pngFile, "\x02\x00\x00\x00\x0b\x49\x44\x41\x54\x78\x9c\x63\xfa\xcf\x00\x00");
        fwrite($this->pngFile, "\x02\x07\x01\x02\x9a\x1c\x31\x71\x00\x00\x00\x00\x49\x45\x4e\x44");
        fwrite($this->pngFile, "\xae\x42\x60\x82");

        fseek($this->pngFile, 0, SEEK_SET);

        require_once "lib/libguess/image.php";
    }

    public function tearDown() {
        fclose($this->pngFile);
    }

    /**
     * Test correct detection of valid PNG file.
     */
    public function testDetect() {
        $png = new Png();
        $this->assertTrue($png->isMatch($this->pngFile));
    }

    /**
     * Test correct refusal of invalid PNG file.
     */
    public function testNotDetect() {
        $f = fopen("php://memory", "r+b");
        fwrite($f, "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F");
        fwrite($f, "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F");
        fwrite($f, "\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2A\x2B\x2C\x2D\x2E\x2F");
        fwrite($f, "\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3A\x3B\x3C\x3D\x3E\x3F");
        fwrite($f, "\x40\x41\x42\x43");

        fseek($f, 0, SEEK_SET);

        $png = new Png();
        $this->assertFalse($png->isMatch($f));
    }

    /**
     * Test readed width of image
     * @depends testDetect
     */
    public function testWidth() {
        $png = new Png();
        $png->isMatch($this->pngFile);
        $this->assertEquals(1, $png->getWidth());
    }

    /**
     * Test readed height of image.
     * @depends testDetect
     */
    public function testHeight() {
        $png = new Png();
        $png->isMatch($this->pngFile);
        $this->assertEquals(1, $png->getHeight());
    }
}

