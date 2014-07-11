<?php

header("content-type: text/html; charset=utf-8");

require_once "wiki/wiki.php";
require_once "lib/wikiformatter.php";

require_once "lib/format/Blockquote.php";
require_once "lib/format/Line.php";
require_once "lib/format/Lists.php";
require_once "lib/format/Line.php";
require_once "lib/format/Heading.php";
require_once "lib/format/Table.php";
require_once "lib/format/Block.php";

require_once "lib/format/BasicFormat.php";
require_once "lib/format/Link.php";
require_once "lib/format/Image.php";
require_once "lib/format/Variables.php";

use lib\formatter\WikiFormatter;
use lib\formatter\format;

$f = new WikiFormatter();

$f->installLineTrigger(new format\Blockquote());
$f->installLineTrigger($heading = format\Heading::Create(2));
$f->installLineTrigger(format\Heading::Create(3));
$f->installLineTrigger(format\Heading::Create(4));
$f->installLineTrigger(format\Heading::Create(5));
$f->installLineTrigger(format\Heading::Create(6));
$f->installLineTrigger(new format\Line());
$f->installLineTrigger(new format\Lists());
$f->installLineTrigger(new format\Table());
$f->installLineTrigger($block = new format\Block());

$variables = new format\Variables();
$variables->register($block);

$block->registerBlockFormatter("toc", array($heading, "generateToc"));

$f->installInlineTrigger(format\BasicFormat::Create("**", "strong"));
$f->installInlineTrigger(format\BasicFormat::Create("//", "em"));
$f->installInlineTrigger(format\BasicFormat::Create("__", "ins"));
$f->installInlineTrigger(format\BasicFormat::Create("--", "del"));
$f->installInlineTrigger(format\BasicFormat::Create("''", "code"));
$f->installInlineTrigger(new format\Link());
$f->installInlineTrigger(new format\LinkInText());
$f->installInlineTrigger(new format\PlainText());
$f->installInlineTrigger(new format\Image());
$f->installInlineTrigger(new format\InlineVariable($variables));
$f->installInlineTrigger(new format\LineBreak());

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

format\BasicFormat::testSuite($f);
format\Block::testSuite($f);
format\Heading::testSuite($f);
format\Image::testSuite($f);
format\Link::testSuite($f);
format\Lists::testSuite($f);
format\Table::testSuite($f);
format\InlineVariable::testSuite($f);
format\LineBreak::testSuite($f);

$f = new WikiFormatter();
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
