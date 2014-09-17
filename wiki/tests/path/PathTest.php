<?php

use \lib\path;

class PathTest extends \PHPUnit_Framework_TestCase {
    public function setUp() {
        require_once "lib/path.php";
    }

    function testSimpleJoin1() {
        $this->assertEquals("a/b", path::join("a", "b"));
    }

    function testSimpleJoin2() {
        $this->assertEquals("a/b/c", path::join("a/b", "c"));
    }

    function testSimpleJoin3() {
        $this->assertEquals("a/b/c", path::join("a//b", "///c"));
    }

    function testArrayJoin() {
        $this->assertEquals("a/b/c/d", path::join(array("a", "b"), array("c/d")));
    }

    function testCombinedJoin1() {
        $this->assertEquals("a/b/c/d", path::join(array("a", "b"), "c/d"));
    }

    function testCombinedJoin2() {
        $this->assertEquals("a/b/c/d", path::join("a/b", array("c", "d")));
    }

    function testTrim1() {
        $this->assertEquals("/a/b", path::join("/a", "b"));
    }

    function testTrim2() {
        $this->assertEquals("/a/b", path::join("/a", "b/"));
    }

    function testTrim3() {
        $this->assertEquals("/a/b", path::join("/a/b/c", ".."));
    }

    function testTrim4() {
        $this->assertEquals("/a/b/c", path::join("/a/b/../b", "c"));
    }

    function testTrim5() {
        $this->assertEquals("a", path::join("./a", "."));
    }
    
    function testTrim6() {
        $this->assertEquals("a", path::join("a", ".", ".", "."));
    }
}
