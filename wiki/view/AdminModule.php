<?php

namespace view;

class AdminModule {
    public $name;
    public $url;

    public function __construct($name, $url) {
        $this->name = $name;
        $this->url = $url;
    }
}
