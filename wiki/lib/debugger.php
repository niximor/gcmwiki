<?php

namespace lib;

class Debugger {
	protected function export_var(&$v) {
		switch (gettype($v)) {
			case "string":
				return "'".addcslashes(htmlspecialchars($v), "\n\t")."'";

			case "int":
			case "float":
			case "integer":
			case "double":
				return (string)$v;

			case "array":
				$exported = array();
				foreach ($v as $key=>$val) {
					$exported[] = $this->export_var($key)."=>".$this->export_var($val);
				}
				return "array(".implode(", ", $exported).")";

			case "object":
				return "(object)".get_class($v);

			case "resource":
				return "(resource)".(string)$v;

			case "boolean":
				return ($v)?"true":"false";

			default:
				return gettype($v);
		}
	}

	function exception($exc) {
		echo '<div style="background-color: white; color: black; font-family: arial; font-size: 1em">';
		echo '<h1>Unhandled exception</h1>';
		echo '<h2>'.get_class($exc).'</h2>';
		echo '<dl>';
		echo '<dt style="background-color: #436F9C; color: white; font-weight: bold; font-size: 1.2em; padding: 0.2em 0.5em;">Message</dt>';
		echo '<dd style="padding: 0.2em; 0.5em; margin: 0; margin-bottom: 1em;">'.$exc->getMessage().'</dd>';

		if ($exc instanceof \drivers\mysql\QueryException) {
			echo '<dt style="background-color: #436F9C; color: white; font-weight: bold; font-size: 1.2em; padding: 0.2em 0.5em;">Query</dt>';
			echo '<dd style="padding: 0.2em; 0.5em; margin: 0; margin-bottom: 1em;">'.$exc->getQuery().'</dd>';
		}

		echo '<dt style="background-color: #436F9C; color: white; font-weight: bold; font-size: 1.2em; padding: 0.2em 0.5em;">Stack trace</dt>';
		echo '<dd style="padding: 0.2em; 0.5em; margin: 0; margin-bottom: 1em;"><ol>';

		/*$backtrace = array_merge(array(array(
			"file" => $exc->getFile(),
			"line" => $exc->getLine()
		)), $exc->getTrace());*/
		$backtrace = $exc->getTrace();

		foreach ($backtrace as $trace) {
			if (isset($trace["file"]) && $trace["file"]) {
				$file = htmlspecialchars($trace["file"]);
			} else {
				$file = "unknown";
			}

			if (isset($trace["line"]) && $trace["line"]) {
				$line = $trace["line"];
			} else {
				$line = "-1";
			}

			echo '<li>In <code>'.$file."</code> on line <b>".$line."</b>";
			if (isset($trace["function"]) && $trace["function"]) {
				echo '<blockquote style="margin-top: 0; margin-bottom: 0"><code>';

				if (isset($trace["class"]) && $trace["class"]) {
					echo $trace["class"];
					echo '::';
				}

				echo $trace["function"].'(';

				if (isset($trace["args"]) && $trace["args"]) {
					$first = true;
					foreach ($trace["args"] as $argv) {
						if (!$first) echo ", ";
						$first = false;
						echo $this->export_var($argv);
					}
				}

				echo ')</code></blockquote>';
			}
			echo '</li>';
		}

		echo '</ol></dd>';
	}

	function error($errno, $errstr, $errfile, $errline, $errcontext) {
		if ((error_reporting() & $errno) == 0) return;

		$break = false;

		$bgcolor = "white";
		switch ($errno) {
			case E_ERROR:
			case E_USER_ERROR:
				$errtype = "Error";
				$bgcolor = "#ffebeb";
				$break = true;
				break;

			case E_WARNING:
			case E_USER_WARNING:
				$errtype = "Warning";
				$bgcolor = "#fdffeb";
				break;

			case E_PARSE:
				$errtype = "Parse error";
				$bgcolor = "#ffebeb";
				$break = true;
				break;

			case E_NOTICE:
			case E_USER_NOTICE:
				$errtype = "Notice";
				$bgcolor = "#f6ffeb";
				break;

			case E_CORE_ERROR:
				$errtype = "Core error";
				$bgcolor = "#ffebeb";
				$break = true;
				break;

			case E_CORE_WARNING:
				$errtype = "Core warning";
				$bgcolor = "#fdffeb";
				break;

			case E_COMPILE_ERROR:
				$errtype = "Compile error";
				$bgcolor = "#ffebeb";
				$break = true;
				break;

			case E_COMPILE_WARNING:
				$errtype = "Compile warning";
				$bgcolor = "#fdffeb";
				break;

			case E_STRICT:
				$errtype = "Strict warning";
				$bgcolor = "#fdffeb";
				break;

			case E_RECOVERABLE_ERROR:
				$errtype = "Catchable fatal error";
				$bgcolor = "#ffebeb";
				break;

			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				$errtype = "Deprecated";
				$bgcolor = "#efefef";
				break;

			default:
				$errtype = "Unknown error";
				$bgcolor = "#ffebeb";
				break;
		}

		$f = file($errfile);
		if ($errline >= 2) {
			$lines = array_slice($f, $errline - 2, 3);
		} else {
			$lines = array_merge(array(""), array_slice($f, $errline, 2));
		}

		while (count($lines) < 3) {
			$lines[] = "";
		}

		$cli_msg = "";
		$cli_msg .= $errtype.": ".$errstr."\n";
		$cli_msg .= "In ".$errfile." on line ".$errline."\n";
		$cli_msg .= ($errline - 1).str_replace("\t", "   ", rtrim($lines[0]))."\n";
		$cli_msg .= ($errline).str_replace("\t", "   ", rtrim($lines[1]))."\n";
		$cli_msg .= ($errline + 1).str_replace("\t", "   ", rtrim($lines[2]))."\n";
		$cli_msg .= "\n";
		$cli_msg .= "Call stack\n";

		if (php_sapi_name() == "cli" || !ini_get("html_errors")) {
			echo $cli_msg;
			$console = true;
		} else {
			echo "<div style=\"border: solid 1px #808080; padding: 0.5em; font-family: verdana, arial, helvetica, sans-serif; margin: 1em; font-size: 12px; background-color: ".$bgcolor."; color: black;\">\n";
			echo "<p style=\"margin: 0;\"><strong>".$errtype.":</strong> ".$errstr." <span style=\"cursor: pointer\" onclick=\"this.parentNode.nextSibling.style.display=''; this.style.display='none';\">...</span></p>";
			echo '<div style="display: none; margin-top: 1em;">';
			echo "<code><strong>".$errfile."</strong></code><br />";
			echo "<code style=\"background-color: ".$bgcolor."; display: block;\">&nbsp;".($errline - 1)."&nbsp;&nbsp;".str_replace("\t", "&nbsp;&nbsp;&nbsp;", str_replace(" ", "&nbsp;", htmlspecialchars(rtrim($lines[0]))))."</code>\n";
			echo "<code style=\"background-color: #e0e0e0; display: block; font-weight: bold;\">&nbsp;".($errline)."&nbsp;&nbsp;".str_replace("\t", "&nbsp;&nbsp;&nbsp;", str_replace(" ", "&nbsp;", htmlspecialchars(rtrim($lines[1]))))."</code>\n";
			echo "<code style=\"background-color: ".$bgcolor."; display: block;\">&nbsp;".($errline + 1)."&nbsp;&nbsp;".str_replace("\t", "&nbsp;&nbsp;&nbsp;", str_replace(" ", "&nbsp;", htmlspecialchars(rtrim($lines[2]))))."</code>\n";
			echo "<p><strong>Call stack</strong></p>\n";
			echo "<ol>\n";
			$console = false;
		}

		$stack = debug_backtrace();
		array_shift($stack);
		$i = 0;
		foreach ($stack as $item) {
			$call = $item["function"]."(";
			if (isset($item["class"])) {
				$call = $item["class"].$item["type"].$item["function"]."(";
			}

			$first = true;
			if (!isset($item["args"]) || !is_array($item["args"])) $item["args"] = array();
			foreach ($item["args"] as $argv) {
				if (!$first) $call .= ", ";
				$first = false;
				$call .= $this->export_var($argv);
			}

			$call .= ")";

			if (isset($item["file"])) $file = $item["file"];
			if (!isset($file) || !$file) $file = "Unknown";

			if (isset($item["line"])) $line = $item["line"];
			if (!isset($line) || !$line) $line = 0;

			$cli_msg .= sprintf("%d.\t%s in %s on line %s\n", ++$i, $call, $file, $line);
			if ($console) {
				echo $cli_msg;
			} else {
				echo "<li>".sprintf("<strong><code>%s</code></strong> in <code>%s</code> on line <strong>%d</strong>", $call, $file, $line)."</li>\n";
			}
		}

		if (!$console) {
			echo "</ol></div>\n";
			echo "</div>\n";
		} else {
			echo "\n";
		}

		if (ini_get("log_errors")) {
			foreach (explode("\n", $cli_msg) as $line) {
				error_log($line);
			}
		}

		if ($break) exit;
	}
}

$dbg = new Debugger();

set_exception_handler(array($dbg, "exception"));
set_error_handler(array($dbg, "error"));

