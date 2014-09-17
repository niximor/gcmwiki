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
		echo '<div style="background-color: #800000; padding: 2em; position: fixed; left: 0; top: 0; right: 0; bottom: 0; overflow: auto;">';
		echo '<div style="max-width: 50em; background-color: white; color: black; font-family: arial; font-size: 1em; margin-left: auto; margin-right: auto; border-radius: 0.5em; padding: 1em; padding-top: 1.4em; -webkit-box-shadow: 4px 4px 10px rgba(0, 0, 0, 1); -moz-box-shadow: 4px 4px 10px rgba(0, 0, 0, 1); box-shadow: 4px 4px 10px rgba(0, 0, 0, 1); background-image: url(\'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADAAAAAwCAYAAABXAvmHAAAF/klEQVRoge2V2U8UBxzHSdqCKwurCO7MrOAFCOzNioLMjuxyBAGvQjDSWlvTw9bUeFbiegHrKmoIVdLoc/vEH9DU4MxesCOWalSCF6IURUQulYdN1Hz7QFhdUNljlpfySz6P883nk5lkwsJmbuZmzu9rzAoTXWSki4Xau8hIF/+pJ+KE2vvoXdITZTajDK7iBLA0aQp2j6VJk6s4Ac6CeLB64kchHD94l/REmc0gw1B9Otx/ZIIvCS6CpUkTX5IA9++ZGL2QAWfBgtBFsDS50WaQYciiBi5kABcy4G5YHnCER75hOXA+AzifgdG69NBEjMsP1qiA33QYPaXGyEk1cC4d7rM68H5+TixNmvjiBLjP6oBz6Rg5qcaLWjXQoMPoGa2wER75ahXQkI6XJ1UYMCsxYFZi2KIC6rVw12t9jvDI12uBei2GLW/3np9QAWfTMXpKI0wEly1dZzPIMHhMAfyqxQuLEs+qFV4MmZVAnQbuOs2UER75Og1Qp8GQefLeyHElUK/F6Ek1nPlBRHjkD8mBOg2e1yjQf1T+XgarFMBpNdyn1eCL4t8bwdKkiS+Kh/u0GjitxmDVh/eGqxXAGTVGLarAIjzypjTgjAojVWl4ejj1owwcSQVqlXDXqiZFeORrVUCtEgNHPr719HAqho6lAaeUGK1R+BfRKA8Lt+ZQ6N+VDJxQYOhwKp4cTPGJflMK3lgUcFuU4NeMRbA0aeLXxMNtUeKNRYF+k29bTw6mYOBQKl4fV2DglxQ482Tw6WfXRFMJzvwF6N6VhN4Dy/zmaeUyvK6Ww10jB18UD74oHu4aOV5Xy/G00v+93gPL0L0rCY48GdiseTKf3gLLUJXO/AV4uDMJj/cl+03f/mS8OpoGd7Uc7mo5Xh1NQ99+/3ce70vGw58T4ciTgdMT+32SfzfCkSfDgx1L0bM70W969ySib28S+vYmoXeP/8/37E5E109L4cgNQH5ixP3tS9C9c+m00rl9SXDyXhG5MnR+vwgPdyyeFjq/WySM/MSIu9sWouuHRSHl7raFsBsFlPeKMFK4/XUCOr8NDbe3xsNupISXfzfCbqRwe0s87n0jLLe+DLH8xIiOzTLc2SIMHZtlsBumQd4rwkChfROFWxXB0V5OTa/8WABpbsmj0PMFhTubCHSUB8adTQR6Kig051Lg9FTVtMm35lN4uVWG3goSHWVStJcGRkeZFE8qSLz4SobLedMQwTKkuTWPwvMKAo/KpWjfEIcb64OjfUMcHpdLMVJBgA/lm2AZ0nw5l8RwuRQ9n8/HzbWxuF4iDDfXxuJR6XwMl0vBG0MQwTKk+bKRxGBpHLrXx+J6UQyurRGW60Ux+HdDLAZL48AbSeEiWIY080YSA+tj0VUcg2sFc/FPfmi4VjAXD4rn4dn6WLgMAkSwDGnmDQSelcTgfuEcXM2VoM0YWq7mStBVOAf9JTFw5QQRwTKkmc8h0Fc4F/fyJGhbHY0r00Tb6mh05kvwpHAuXDmE/xEsQ5pdOQR68yW4Y4jCFVqMywHA09Hg6eiAnr1Ci3HXEIXH+RK0rPYjgsuOXWZnCPy9SowbtBitWZHgA8C1KgpWPQGrnoBrVVRAG61ZkbhJi9GWLYaNIfBXVlzilAGNWWEijiHRnCmBa8XsgGhZKYZVT4BlqEqWoSqtegItK8UB7zVnSsAxFBrlYeE+vgXpOo4h4VgRjWadyC+cyyM98m8/ybEI5/JIv/ccK6LBMRSa9ESxT/KTIjLEcGojfMKhE8FKe8t7RdAEHDqR73sZYnAMCS5bus4vea8IPQm7TgyHKvzjaGbBSkvfK/9uBEcTcGhmTblnT48Epw9CfmKETRsJu+Kz96OKADeF/MQIuyrig3s27Zg8S5Mbg5KfGGHVzIYt7VNvFBHgsn2T946QwqaImLynFgkrPylCJYI15ZMx5OF+y3tFZEthlYe/3VOOyV9ipJsElR+/8QhOKQKXGrj8+I1HcKnh4BSzQis/fp6IIOXHzxOhJ8HRVIUQjlNeY1aYqImmEoTaa6KphEZ5nFiovZmbuf/T/Qf0iASVIPe6UwAAAABJRU5ErkJggg==\'); background-repeat: no-repeat; background-position: 1em 1em; padding-left: 5em;">';
		echo '<h1 style="margin: 0; font-size: 1.5em; color: #A12800; padding: 0.2em 0">Unhandled exception</h1>';
		echo '<dl style="margin: 0; padding: 0; margin-top: 0.5em;">';
		echo '<dt style="color: #A12800; font-weight: bold; font-size: 1.2em; padding: 0.2em 0;">'.get_class($exc).'</dt>';
		echo '<dd style="padding: 0.5em 0; margin: 0; margin-bottom: 1em;">'.htmlspecialchars($exc->getMessage()).'</dd>';

		if ($exc instanceof \drivers\mysql\QueryException) {
			echo '<dt style="color: #A12800; font-weight: bold; font-size: 1.2em; padding: 0.2em 0;">Query</dt>';
			echo '<dd style="padding: 0.5em 0; margin: 0; margin-bottom: 1em;"><pre>'.htmlspecialchars($exc->getQuery()).'</pre></dd>';
		}

		echo '<dt style="color: #A12800; font-weight: bold; font-size: 1.2em; padding: 0.2em 0;">Stack trace</dt>';
		echo '<dd style="padding: 0.5em 0; margin: 0; margin-bottom: 1em;"><ol>';

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

		echo '<dt style="color: #A12800; font-weight: bold; font-size: 1.2em; padding: 0.2em 0;">Error code</dt>';
		echo '<dd style="padding: 0.5em 0; margin: 0; margin-bottom: 1em;">'.$exc->getCode().'</dd>';

		echo '</dl></div></div>';
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
			echo "<p style=\"margin: 0;\"><strong>".$errtype.":</strong> ".$errstr." in <strong>".$errfile."</strong> on line <strong>".$errline."</strong> <span style=\"cursor: pointer\" onclick=\"this.parentNode.nextSibling.style.display=''; this.style.display='none';\">...</span></p>";
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

