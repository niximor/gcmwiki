<?php

namespace lib;

interface Formatter {
	public function format($text, \models\WikiPage $pageContext=NULL);
	public function getRootContext();
}
