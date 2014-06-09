<?php
	namespace mail;

	/**
	 * A class representing one header entry.
	 */
	class MailHeader implements ArrayAccess {
		protected $value = "";
		protected $keys = array();

		/**
		 * Parse the header's value. Split it into subsections.
		 */
		function __construct($v) {

			mb_internal_encoding("utf-8");
			$v = mb_decode_mimeheader($v);

			$state = 0;
			$name = "";
			$value = "";
			$len = strlen($v);

			for ($x = 0; $x < $len; $x++) {
				$ch = $v[$x];

				switch ($state) {
					case 0:
						if ($ch == ";") {
							$state = 1;
							$this->value = trim($this->value);
						} else {
							$this->value .= $ch;
						}
						break;

					case 1:
						if (!ctype_space($ch)) {
							$state = 2;
							$name = $ch;
						}
						break;

					case 2:
						if ($ch == "=") {
							$state = 3;
						} else {
							$name .= $ch;
						}
						break;

					case 3:
						if ($ch == "\"") {
							$state = 4;
							$value = "";
						} elseif (!ctype_space($ch)) {
							$value = $ch;
							$state = 5;
						}
						break;

					case 4:
						if ($ch == "\"") {
							$state = 5;
						} else {
							$value .= $ch;
						}
						break;

					case 5:
						if ($ch == ";") {
							$state = 1;
							$this->processKVPair($name, $value);
						} else {
							$value .= $ch;
						}
						break;
				}
			}

			if ($state > 2) {
				$this->processKVPair($name, $value);
			}

			foreach ($this->keys as $name=>$value) {
				if (is_array($value)) {
					$encoded = (isset($value["encoded"]) && $value["encoded"]);
					unset($value["encoded"]);

					$value = implode("", $value);

					if ($encoded && preg_match("/([a-zA-Z0-9-]*)'([a-zA-Z-]*)'(.*)/", $value, $matches)) {
						$charset = $matches[1];
						$value = iconv($charset, "utf-8", urldecode($matches[3]));
					}

					$this->keys[$name] = $value;
				}
			}
		}

		protected function processKVPair($name, $value) {
			$name = trim($name);
			if (preg_match('/(.*)\\*([0-9]+)(\\*)?$/', $name, $matches)) {
				if (isset($this->keys[$matches[1]]) && is_array($this->keys[$matches[1]])) {
					$this->keys[$matches[1]][$matches[2]] = trim($value);
				} else {
					$this->keys[$matches[1]] = array($matches[2] => trim($value));
				}

				$this->keys[$matches[1]]["encoded"] = (isset($matches[3]) && $matches[3] == "*");
			} else {
				if (preg_match("/(.*)\\*/", $name, $matches)) {
					$this->keys[$matches[1]] = array(
						"encoded" => true,
						trim($value)
					);
				} else {
					$this->keys[$name] = array(
						"encoded" => false,
						trim($value)
					);
				}
			}
		}

		function __toString() {
			return (string)$this->value;
		}

		function offsetExists($offset) {
			return isset($this->keys[$offset]);
		}

		function offsetGet($offset) {
			return $this->keys[$offset];
		}

		function offsetSet($offset, $value) {
			// Read-only
		}

		function offsetUnset($offset) {
			// Read-only
		}

		function getFullValue() {
			$out = $this->value;

			foreach ($this->keys as $k=>$v) {
				$out .= "; ".$k."=\"".$v."\"";
			}

			return $out;
		}
	}

	/**
	 * A class representing one part of the multipart MIME message OR whole
	 * email message if no multipart is used.
	 * Currently, the parser supports RFC 822, RFC 2045, RFC 2047, RFC 2183,
	 * RFC 2184, althrough the support might not be (and probably isn't) complete.
	 */
	class MIMEChunk implements ArrayAccess {
		const STATE_INIT_HEADERS = 0;
		const STATE_MESSAGE_BODY = 1;

		/**
		 * Parser state
		 */
		protected $state = self::STATE_INIT_HEADERS;

		/**
		 * File where the email message is located.
		 */
		protected $f;

		/**
		 * Parts as childs of this part.
		 */
		protected $parts = array();

		/**
		 * Parent message part.
		 */
		protected $parent = null;

		/**
		 * Headers of the part.
		 */
		protected $headers;

		/**
		 * Offset of the body.
		 */
		protected $bodyStart = 0;

		/**
		 * Length of body.
		 */
		protected $bodyLength = 0;

		protected $selfBoundary;

		protected $unget = null;

		/**
		 * Constructor.
		 * @param file $f File resource to be parsed.
		 * @param string $boundary Used internally by the parser to identify
		 *   boundary of current part.
		 */
		function __construct($f, $boundary = "") {
			if (is_resource($f)) {
				$this->f = $f;
			} elseif (is_object($f) && $f instanceof MIMEChunk) {
				$this->parent = $f;
				$this->f = $this->parent->f;
			}

			$this->selfBoundary = $boundary;

			$this->scanFile();
		}

		function __get($name) {
			switch ($name) {
				case "parts":
					return $this->parts;

				case "attachments":
					$stack = array($this);
					$attachments = array();

					while (!empty($stack)) {
						$top = array_pop($stack);
						if (isset($top["content-disposition"]) && $top["content-disposition"] == "attachment") {
							$attachments[] = $top;
						}

						foreach ($top->parts as $part) {
							array_unshift($stack, $part);
						}
					}
					return $attachments;

				default:
					return NULL;
			}
		}

		function getLine() {
			if ($this->unget) {
				$out = $this->unget;
				$this->unget = null;
				return $out;
			} else {
				return fgets($this->f);
			}
		}

		function ungetLine($line) {
			$this->unget = $line;
		}

		protected function scanFile() {
			$lastHeader = "";
			$part = null;
			$boundary = "";

			while ($l = $this->getLine()) {

				switch ($this->state) {
					case self::STATE_INIT_HEADERS:
						if (!trim($l)) {
							if ($lastHeader != "") {
								$this->processHeader($lastHeader);
								$lastHeader = "";
							}

							if (isset($this->headers["content-type"])) {
								if (
									substr($this->headers["content-type"], 0, strlen("multipart/")) == "multipart/" &&
									isset($this->headers["content-type"]["boundary"])
								) {
									// Awaiting boundary...
									$boundary = $this->headers["content-type"]["boundary"];
								}
							}

							$this->state = self::STATE_MESSAGE_BODY;
							$this->bodyStart = ftell($this->f);

						} else {
							// Multi-line headers begins with space.
							if ($lastHeader != "" && (!isset($l[0]) || ($l[0] != " " && $l[0] != "\t"))) {
								$this->processHeader($lastHeader);
								$lastHeader = $l;
							} else {
								$lastHeader .= ltrim($l);
							}
						}
						break;

					case self::STATE_MESSAGE_BODY:
						if (rtrim($l) == "--".$boundary) {
							// Begining of the part...
							$this->bodyLength += strlen($l);
							$pos = ftell($this->f);
							$this->parts[] = new MIMEChunk($this, $boundary);
							$this->bodyLength += ftell($this->f) - $pos;
						} elseif ($this->selfBoundary && rtrim($l) == "--".$this->selfBoundary) {
							// Another part of the same boundary.
							$this->parent->ungetLine($l);
							return;
						} elseif ($this->selfBoundary && rtrim($l) == "--".$this->selfBoundary."--") {
							// End of current part.
							return;
						} else {
							$this->bodyLength += strlen($l);
						}
						break;
				}
			}
		}

		protected function processHeader($header) {
			if (($pos = strpos($header, ":")) !== false) {
				$name = substr($header, 0, $pos);
				$rest = trim(substr($header, $pos + 1));
				$this->headers[strtolower($name)] = new MailHeader($rest);
			} else {
				// Error when parsing header...
				echo "Error when parsing message header (".$header.").<br />";
			}
		}

		public function offsetExists($offset) {
			return isset($this->headers[$offset]);
		}

		public function offsetGet($offset) {
			return $this->headers[$offset];
		}

		public function offsetSet($offset, $value) {
		}

		public function offsetUnset($offset) {
		}

		public function getBody($decode = true) {
			fseek($this->f, $this->bodyStart, SEEK_SET);
			$len = $this->bodyLength;

			if (isset($this["content-transfer-encoding"])) {
				$cte = $this["content-transfer-encoding"];
			} else {
				$cte = "";
			}

			$old = "";
			while ($len > 0) {
				$read = min($len, 1024);
				$chunk = $old.fread($this->f, $read);

				if ($decode) {
					switch ($cte) {
						case "base64":
							$chunk = preg_replace("/\\s+/", "", $chunk);
							$chlen = floor(strlen($chunk) / 4) * 4;
							$old = substr($chunk, $chlen);
							$chunk = substr($chunk, 0, $chlen);
							$chunk = base64_decode($chunk);
							break;

						case "quoted-printable":
							$chunk = quoted_printable_decode($chunk);
							break;
					}

					if (isset($this["content-type"]) && isset($this["content-type"]["charset"])) {
						if (strtolower($this["content-type"]["charset"]) != "utf-8") {
							$chunk = iconv($this["content-type"]["charset"], "utf-8", $chunk);
						}
					}

				}

				echo $chunk;

				$len -= $read;
			}

			//stream_filter_remove($filter);
		}

		/**
		 * Scan parts and find first part with given content-type and no inline or attachment content-disposition.
		 * @param string $ct Content type of the body part to retrieve.
		 */
		public function getBodyPart($ct = "text/plain") {
			$stack = array($this);
			while (!empty($stack)) {
				$top = array_pop($stack);
				if (isset($top["content-type"]) && $top["content-type"] == $ct) {
					if (!isset($top["content-disposition"]) || ($top["content-disposition"] != "attachment" && $top["content-disposition"] != "inline")) {
						return $top;
					}
				}

				foreach ($top->parts as $part) {
					array_unshift($stack, $part);
				}
			}
		}
	}
?>
