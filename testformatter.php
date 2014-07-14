<?php

header("content-type: text/html; charset=utf-8");

require_once "wiki/wiki.php";
require_once "lib/wikiformatter.php";

use lib\formatter\WikiFormatterFull;
use lib\formatter\format;

?>

<!DOCTYPE html>
<html>
<head>
</head>
<body>
<form action="" method="post">
    Wiki text:<br />
    <textarea name="wiki_text" rows="10" cols="50"><?php if (isset($_REQUEST["wiki_text"])) echo htmlspecialchars($_REQUEST["wiki_text"]); ?></textarea><br />
    <input type="submit" />
</form>

<?php

if (!isset($_REQUEST["wiki_text"])) {
    $_REQUEST["wiki_text"] = "";
}

echo "Debug:";
echo "<pre>";

$f = new WikiFormatterFull();

format\BasicFormat::testSuite($f);
format\Block::testSuite($f);
format\Heading::testSuite($f);
format\Image::testSuite($f);
format\Link::testSuite($f);
format\Lists::testSuite($f);
format\Table::testSuite($f);
format\InlineVariable::testSuite($f);
format\LineBreak::testSuite($f);

$f->debug = true;
$out = $f->format($_REQUEST["wiki_text"]);
echo "</pre>";

echo "Formatted:";
echo "<div>".$out."</div>";

echo "RAW:";
echo "<pre>".htmlspecialchars($out)."</pre>";

?>

</body>
</html>
