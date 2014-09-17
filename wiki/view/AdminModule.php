<?php

namespace view;

class AdminModule {
    public $name;
    public $url;

    public function __construct($name, $id, $url) {
        $this->name = $name;
        $this->id = $id;
        $this->url = $url;
    }
}
