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

use lib\formatter\WikiFormatter;
use lib\formatter\format;

WikiFormatter::installLineTrigger(new format\Blockquote());
WikiFormatter::installLineTrigger(format\Heading::Create(2));
WikiFormatter::installLineTrigger(format\Heading::Create(3));
WikiFormatter::installLineTrigger(format\Heading::Create(4));
WikiFormatter::installLineTrigger(format\Heading::Create(5));
WikiFormatter::installLineTrigger(format\Heading::Create(6));
WikiFormatter::installLineTrigger(new format\Line());
WikiFormatter::installLineTrigger(new format\Lists());
WikiFormatter::installLineTrigger(new format\Table());
WikiFormatter::installLineTrigger(new format\Block());

WikiFormatter::installInlineTrigger(format\BasicFormat::Create("**", "strong"));
WikiFormatter::installInlineTrigger(format\BasicFormat::Create("//", "em"));
WikiFormatter::installInlineTrigger(format\BasicFormat::Create("__", "ins"));
WikiFormatter::installInlineTrigger(format\BasicFormat::Create("--", "del"));
WikiFormatter::installInlineTrigger(format\BasicFormat::Create("''", "code"));
WikiFormatter::installInlineTrigger(new format\Link());
WikiFormatter::installInlineTrigger(new format\LinkInText());
WikiFormatter::installInlineTrigger(new format\PlainText());
WikiFormatter::installInlineTrigger(new format\Image());

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

format\Lists::testSuite();
format\BasicFormat::testSuite();
format\Link::testSuite();
format\Block::testSuite();

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
