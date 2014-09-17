<?php

use \lib\libguess\Gif;

class GifTest extends \PHPUnit_Framework_TestCase {
    protected $fileHandle;

    public function setUp() {
        $this->fileHandle = fopen("php://memory", "r+b");

        // sample GIF image, source: http://sample-file.bazadanni.com/2012/01/gif.html
        fwrite($this->fileHandle, "\x47\x49\x46\x38\x39\x61\x30\x00\x30\x00\xa1\x02\x00\x12\x00\xff");
        fwrite($this->fileHandle, "\xf6\x01\x01\x00\x00\x00\x00\x00\x00\x21\xf9\x04\x01\x0a\x00\x02");
        fwrite($this->fileHandle, "\x00\x2c\x00\x00\x00\x00\x30\x00\x30\x00\x00\x02\x74\x94\x8f\xa9");
        fwrite($this->fileHandle, "\x07\xed\x0f\xa3\x5b\xb4\xca\x8b\xab\x4e\xb8\xc3\x0d\x0a\xde\x08");
        fwrite($this->fileHandle, "\x84\x1b\xe9\x99\x1a\xda\xa9\x16\x7b\xb9\x14\x1c\xcb\x0a\x2d\xd9");
        fwrite($this->fileHandle, "\x37\xfe\xe9\x08\xdf\xf3\x19\x80\x0f\x21\x83\xd8\x30\x0e\x91\x25");
        fwrite($this->fileHandle, "\x25\xb3\x69\x7c\x2a\x45\xcc\xa9\xd4\x59\xc5\x22\xad\xd9\x68\x57");
        fwrite($this->fileHandle, "\x78\xf5\x22\x03\xe4\xb2\xf9\x8c\x4e\xab\xd7\xec\xb6\xfb\x0d\x8f");
        fwrite($this->fileHandle, "\xcb\xe7\xf4\xba\xfd\x8e\xcf\xeb\xf7\xfc\xbe\xff\x0f\x18\x28\x38");
        fwrite($this->fileHandle, "\x48\x58\x68\x78\x88\x98\xa8\xb8\xc8\xd8\xe8\xf8\x08\x19\xa9\x57");
        fwrite($this->fileHandle, "\x00\x00\x3b");

        fseek($this->fileHandle, 0, SEEK_SET);

        require_once "lib/libguess/image.php";
    }

    public function tearDown() {
        fclose($this->fileHandle);
    }

    public function testDetect() {
        $gif = new Gif();
        $this->assertTrue($gif->isMatch($this->fileHandle));
    }

    public function testWidth() {
        $gif = new Gif();
        $gif->isMatch($this->fileHandle);
        $this->assertEquals(48, $gif->getWidth());
    }

    public function testHeight() {
        $gif = new Gif();
        $gif->isMatch($this->fileHandle);
        $this->assertEquals(48, $gif->getHeight());
    }
}
