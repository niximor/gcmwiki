<?php header("Content-Type: text/html; charset=UTF-8"); ?>
<!DOCTYPE html>
<html>
	<head>
		<title><?php echo Config::Get("Title", "GCM::Wiki"); ?></title>
		<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
		<link rel="stylesheet" type="text/css" href="<?php echo $this->url("/static/style.css"); ?>" />
	</head>

	<body>
		<h1><?php echo "<a href=\"".$this->url("/".Config::Get("DefaultPage"))."\">".Config::Get("Title", "GCM::Wiki")."</a>"; ?></h1>

		<div id="main">
<?php
	foreach ($Messages as $msg) {
		switch ($msg->type) {
			case \view\Message::Error:
				echo "<p class=\"error message\">".$msg->text."</p>\n";
				break;

			case \view\Message::Information:
				echo "<p class=\"information message\">".$msg->text."</p>\n";
				break;

			case \view\Message::Warning:
				echo "<p class=\"warning message\">".$msg->text."</p>\n";
				break;

			case \view\Message::Success:
				echo "<p class=\"success message\">".$msg->text."</p>\n";
				break;
		}
	}
?>

			<?php if ($this->child) $this->child->render(); ?>
		</div>

		<div id="footer">
			<?php if (!$CurrentUser) { ?>
			[<a href="<?php echo $this->url("/wiki:login"); ?>">Log in</a>]
			[<a href="<?php echo $this->url("/wiki:register"); ?>">Register</a>]
			<?php } else { echo "<a href=\"".$this->url("/wiki:user/".$CurrentUser->getName())."\">".$CurrentUser->getName()."</a>"; ?>
			[<a href="<?php echo $this->url("/wiki:settings"); ?>">Settings</a>]
			[<a href="<?php echo $this->url("/wiki:logout"); ?>">Log out</a>]
			<?php } ?> :: Running on GCM::Wiki
		</div>
	</body>
</html>