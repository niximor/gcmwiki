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
    Page context: <input type="text" name="page_context" value="<?php if (isset($_REQUEST["page_context"])) echo htmlspecialchars($_REQUEST["page_context"]); ?>" /><br />
    <input type="submit" />
</form>

<?php

if (!isset($_REQUEST["wiki_text"])) {
    $_REQUEST["wiki_text"] = "";
}

$page = NULL;
if (isset($_REQUEST["page_context"]) && !empty($_REQUEST["page_context"])) {
    $be = Config::Get("__Backend");
    try {
        $page = $be->loadPage(preg_split("|/|", $_REQUEST["page_context"]));
    } catch (\storage\PageNotFoundException $e) {
    }
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
$out = $f->format($_REQUEST["wiki_text"], $page);
echo "</pre>";

echo "Formatted:";
echo "<div>".$out."</div>";

echo "RAW:";
echo "<pre>".htmlspecialchars($out)."</pre>";

?>

</body>
</html>
