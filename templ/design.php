<?php header("Content-Type: text/html; charset=UTF-8"); ?>
<!DOCTYPE html>
<html>
	<head>
		<title><?php
			if (isset($Title)) {
				printf("%s: %s", Config::Get("Title", "GCM::Wiki"), $Title);
			} else {
				echo Config::Get("Title", "GCM::Wiki");
			}
		?></title>
        <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
		<link rel="stylesheet" type="text/css" href="<?php echo $this->url("/static/style.css"); ?>" />
		<script type="text/javascript" src="<?php echo $this->url("/static/wiki.js"); ?>"></script>
	</head>

	<body>
		<div id="container">
			<div id="header">
				<h1><?php echo "<a href=\"".$this->url("/".Config::Get("DefaultPage"))."\">".Config::Get("Title", "GCM::Wiki")."</a>"; ?></h1>
<?php
	if (isset($Title)) { echo "<h2>".htmlspecialchars($Title)."</h2>"; }
?>
				<div id="userpanel">
					<?php if (!$CurrentUser) { ?>
					<a href="<?php echo $this->url("/wiki:login"); ?>">Log in</a> |
					<a href="<?php echo $this->url("/wiki:register"); ?>">Register</a>
					<?php } else { echo "You are ".$CurrentUser->profileLink($this); ?>
					(<a href="<?php echo $this->url("/wiki:settings"); ?>">Settings</a> |
					<a href="<?php echo $this->url("/wiki:logout"); ?>">Log out</a>)
					<?php } ?>
				</div>

				<div id="actions">
<?php
	if (isset($Actions) && !empty($Actions)) {
		echo "<ul>";
		foreach ($Actions as $Action) {
			echo "<li>".$Action."</li>";
		}
		echo "</ul>";
	}
?>
				</div>

				<div id="navigation">
<?php
	if (isset($Navigation) && !empty($Navigation)) {
		echo "<ul>";
		foreach ($Navigation as $Action) {
			echo "<li>".$Action."</li>";
		}
		echo "</ul>";
	}
?>
				</div>
			</div>

			<div id="main">
<?php
	foreach ($Messages as $msg) {
		switch ($msg->type) {
			case \view\Message::Error:
				echo "<p class=\"error message\">".htmlspecialchars($msg->text)."</p>\n";
				break;

			case \view\Message::Information:
				echo "<p class=\"information message\">".htmlspecialchars($msg->text)."</p>\n";
				break;

			case \view\Message::Warning:
				echo "<p class=\"warning message\">".htmlspecialchars($msg->text)."</p>\n";
				break;

			case \view\Message::Success:
				echo "<p class=\"success message\">".htmlspecialchars($msg->text)."</p>\n";
				break;
		}
	}
?>

				<?php if ($this->child) $this->child->render(); ?>
			</div>

			<div id="footer">
<?php
	if (isset($BottomNavigation) && !empty($BottomNavigation)) {
		echo "<ul>";
		foreach ($BottomNavigation as $Action) {
			echo "<li>".$Action."</li>";
		}
		echo "</ul>";
	}
?>
				 Running on <a href="http://github.com/niximor/gcmwiki">GCM::Wiki</a>
			</div>
		</div>
	</body>
</html>
