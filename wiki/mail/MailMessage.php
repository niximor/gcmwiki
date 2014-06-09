<?php
	namespace mail;

	require_once "mail/MIMEGroup.php";

	if (!defined('CRLF')) {
		define('CRLF', "\r\n");
	}

	/**
	 * \brief Class used to send mails.
	 *
	 * Using this class, you can create a mail message with either plain text
	 * or MIME format.
	 * For plain text messages, you can specify template which will be used
	 * to load mail data (body and subject). In templates, there can be simple
	 * variables that are replaced with text. Use MailMessage::bind() method
	 * to set variable values.
	 *
	 * \section Usage
	 *
	 * Simple message:
	 * \code
	 * $mail = new MailMessage();
	 * $mail->setSubject("Test message");
	 * $mail->setBody("Hello world!");
	 * $mail->addRecipient("somebody@example.com");
	 * $mail->Send();
	 * \endcode
	 *
	 * Message using template:
	 * \code
	 * $mail = new MailMessage();
	 * $mail->setTemplate("hello.mtpl");
	 * $mail->bind("who", "world");
	 * $mail->addRecipient("somebody@example.com");
	 * $mail->Send();
	 * \endcode
	 *
	 * The template looks like:
	 * \code
	 * @subject Test message
	 * Hello %who%!
	 * \endcode
	 *
	 * And the mail sent will have subject Test message, and body Hello world!.
	 *
	 * There are some basic predefined variables, that you can use in the
	 * template:
	 *   - \c \%ip% - Current IP address of the client.
	 *   - \c \%title% - Page title as specified in the configuration.
	 *   - \c \%siteurl% - Full URL to the site root (only available if running
	 *       at the webserver, not available in command line scripts.
	 *
	 * The variables can be contained in the subject and body too.
	 */
	class MailMessage {
		protected $subject = "";		/**< Mail subject. */
		protected $body = "";			/**< Mail body. */

		protected $variables = array();	/**< Variables to be replaced in the message body. */
		protected $recipient = array();	/**< List of mail recipients. */

		protected $mime = false;		/**< Is message in MIME format? */

		protected $headers = array();	/**< List of additional headers. */

		/**
		 * Constructor. Sets some basic variables.
		 */
		function __construct() {
			if (php_sapi_name() != "cli") {
				$this->bind("ip", Session::IP());
			}

			$this->bind("title", Config::Get("Title", "GCM::Wiki"));

			if (php_sapi_name() != "cli") {
				$this->bind("siteurl", "http://".$_SERVER["HTTP_HOST"].dirname($_SERVER["SCRIPT_NAME"]));
			}
		} // MailMessage::__construct()

		/**
		 * Read template and set message body and subject (if specified).
		 * Overwrites body and subject (if specified in the template), if they
		 * were set before call to MailMessage::setTemplate() using
		 * MailMessage::setBody() and/or MailMessage::setSubject().
		 * @param file Name of template file. The template file can be located
		 *   in any template position in applications, same as the view
		 *   templates.
		 */
		function setTemplate($file) {
			$tpl = dirname(__FILE__)."/../../templ/".$file;

			if (!file_exists($tpl)) {
				throw new RuntimeException("Mail template ".$file." was not found.");
			}

			$f = file_get_contents($tpl);

			// Extract subject from the template
			if (preg_match('#^@subject\s+(.*)(\r\n|\n|$)#', $f, $match, PREG_OFFSET_CAPTURE)) {
				$this->subject = $match[1][0];
				$f = substr($f, 0, $match[0][1]).substr($f, $match[0][1] + strlen($match[0][0]));
			}

			// Extract rest of the message
			$this->body = $f;
		} // MailMessage::setTemplate()

		/**
		 * Return current subject of the message.
		 */
		function getSubject() {
			return $this->subject;
		} // MailMessage::getSubject()

		/**
		 * Set subject of the message. Overwrites subject that is loaded
		 * from template if MailMessage::setTemplate() was used before
		 * MailMessage::setSubject() call.
		 */
		function setSubject($subject) {
			$this->subject = $subject;
		} // MailMessage::setSubject()

		/**
		 * Return contents of the body (without replaced variables).
		 */
		function getBody() {
			return $this->body;
		} // MailMessage::getBody()

		/**
		 * Sets message body. Overwrites content readed from template, if
		 * MailMessage::setTemplate() was used before MailMessage::setBody().
		 * Use this method to assign MIME container to the message.
		 */
		function setBody($body) {
			if (is_object($body) && is_instance_of($body, "MIMEGroup")) {
				$this->mime = $body;
			} else {
				$this->body = $body;
			}
		} // MailMessage::setBody()

		/**
		 * Bind variable value to specific variable.
		 * Subsequent calls to bind value to same variable will overwrite
		 * previous variable content.
		 * @param name Variable name.
		 * @param value Variable value.
		 */
		function bind($name, $value) {
			$this->variables[$name] = $value;
		} // MailMessage::bind()

		/**
		 * Bind multiple variables specified as associative array.
		 * @param array $vars Array where key is variable name and value is
		 *   variable value.
		 */
		function bindAll($vars) {
			foreach ($vars as $key=>$val) {
				$this->bind($key, $val);
			}
		} // MailMessage::bindAll()

		/**
		 * Add new recipient of this message to the list. You can add any
		 * number of recipients, and each of them will receive the message.
		 * @param string $recipient Recipient's email address.
		 */
		function addRecipient($recipient) {
			/*if (!preg_match('#<(.*)>#', $recipient)) {
				$recipient = "<".$recipient.">";
			}*/
			$this->recipient[] = $recipient;
		} // MailMessage::addRecipient()

		/**
		 * Set additional header value.
		 * Subsequent calls to set same header multiple times will overwrite
		 * all previous values of that header - ie. only one header with
		 * given name will exists and it's value will be the last one passed
		 * to the MailMessage::setHeader() call.
		 * @param name Header name
		 * @param value Header value
		 */
		function setHeader($name, $value) {
			$this->headers[$name] = $value;
		} // MailMessage::setHeader()

		/**
		 * Return value of the header. If header does not exists, return NULL.
		 * @param name Requested header name.
		 * @return Requested header value of NULL if header does not exists.
		 */
		function getHeader($name) {
			if (isset($this->headers[$name])) {
				return $this->headers[$name];
			} else {
				return NULL;
			}
		} // MailMessage::getHeader()

		/**
		 * Replace variables in specified text. Variables are set using
		 * MailMessage::bind() method.
		 * @param txt Text where to replace the variables.
		 * @return Text with variables replaced.
		 */
		protected function replaceVariables($txt) {
			foreach ($this->variables as $var=>$value) {
				$txt = str_replace("%".$var."%", $value, $txt);
			}
			return $txt;
		} // MailMessage::replaceVariables()

		/**
		 * Return UTF-8 string encoded acording to RFC 2047.
		 * @param input Input string.
		 * @param line_max Maximum characters allowed on signle line. If more
		 *   than this characters is about to be encoded, output is splitted
		 *   to multiple lines acording using valid syntax as specified in RFC.
		 * @return UTF-8 quoted printable encoded string that can be used as
		 *   MIME header value. It has the form of =?UTF-8?Q?string?=, or
		 *   multiple strings such as this concatenated using '\r\n ' if
		 *   input string is too long to fit on one line.
		 */
		static function QuotedPrintableEncode($input, $line_max = 75) {
			$hex = array('0','1','2','3','4','5','6','7',
				'8','9','A','B','C','D','E','F');
			$lines = preg_split("/(?:\r\n|\r|\n)/", $input);
			$linebreak = "?=".CRLF." =?UTF-8?Q?";
			/* the linebreak also counts as characters in the mime_qp_long_line
			 * rule of spam-assassin */
			$line_max = $line_max - strlen($linebreak);
			$escape = "=";
			$output = "";
			$cur_conv_line = "";
			$length = 0;
			$whitespace_pos = 0;
			$addtl_chars = 0;

			// iterate lines
			for ($j=0; $j<count($lines); $j++) {
				$line = $lines[$j];
				$linlen = strlen($line);

				// iterate chars
				for ($i = 0; $i < $linlen; $i++) {
					$c = substr($line, $i, 1);
					$dec = ord($c);

					$length++;

					if ($dec == 32) {
						// space occurring at end of line, need to encode
						//if (($i == ($linlen - 1))) {
							$c = "=20";
							$length += 2;
						//}

						$addtl_chars = 0;
						$whitespace_pos = $i;

					// ASCII >126, ?, = and _ are encoded too.
					} elseif ( ($dec == 61) || ($dec == 95) || ($dec == 63) || ($dec < 32 ) || ($dec > 126) ) {
						$h2 = floor($dec/16); $h1 = floor($dec%16);
						$c = $escape . $hex[$h2] . $hex[$h1];
						$length += 2;
						$addtl_chars += 2;
					}

					// length for wordwrap exceeded, get a newline into the text
					if ($length >= $line_max) {
						$cur_conv_line .= $c;

						// read only up to the whitespace for the current line
						$whitesp_diff = $i - $whitespace_pos + $addtl_chars;

						/* the text after the whitespace will have to be read
						 * again ( + any additional characters that came into
						 * existence as a result of the encoding process after the whitespace)
						 *
						 * Also, do not start at 0, if there was *no* whitespace in
						 * the whole line */
						if (($i + $addtl_chars) > $whitesp_diff) {
							$output .= substr($cur_conv_line, 0, (strlen($cur_conv_line) - $whitesp_diff)) . $linebreak;
							$i =  $i - $whitesp_diff + $addtl_chars;
						} else {
							$output .= $cur_conv_line . $linebreak;
						}

						$cur_conv_line = "";
						$length = 0;
						$whitespace_pos = 0;
					} else {
						// length for wordwrap not reached, continue reading
						$cur_conv_line .= $c;
					}
				} // end of for

				$length = 0;
				$whitespace_pos = 0;
				$output .= $cur_conv_line;
				$cur_conv_line = "";

				if ($j < count($lines)-1) {
					$output .= $linebreak;
				}
			} // end for

			return "=?UTF-8?Q?".trim($output)."?=";
		} // MailMessage::QuotedPrintableEncode()

		/**
		 * Return UTF-8 string encoded using quoted printable encoding.
		 */
		function encodeUTF8($string) {
			// Anything that contains non-ascii or control chars should
			// get escaped.
			if (preg_match('/[^\x20-\x7F]+/u', $string)) {
				return self::QuotedPrintableEncode($string);
			} elseif (strlen($string) > 75) {
				return substr(chunk_split($string, 75, "\r\n "), 0, -3);
			} else {
				return $string;
			}
		} // MailMessage::encodeUTF8()

		/**
		 * Return finished mail body as string.
		 * @return string containing message body.
		 */
		function getPayload() {
			return $this->replaceVariables($this->body);;
		} // MailMessage::getPayload()

		/**
		 * Complete the headers to one string which can be passed to PHP's
		 * mail() function call.
		 * @return String containing encoded headers.
		 */
		function encodeHeaders() {
			$headers = "";
			foreach ($this->headers as $head=>$value) {
				$headers .= $head.": ".$this->EncodeUTF8($value).CRLF;
			}

			// Cut out last CRLF.
			if (substr($headers, -1 * strlen(CRLF)) == CRLF) {
				$headers = substr($headers, 0, -1 * strlen(CRLF));
			}

			return $headers;
		} // MailMessage::encodeHeaders()

		/**
		 * Return subject encoded using UTF-8 quoted printable encoding.
		 * @return string containing encoded subject.
		 */
		function getEncodedSubject() {
			return $this->EncodeUTF8($this->replaceVariables($this->subject));
		} // MailMessage::getEncodedSubject()

		/**
		 * Send the message to all recipients.
		 * @return true if message was sent successfully, false if it failed
		 *   to send to at least one recipient.
		 */
		function Send() {
			$subject = $this->getEncodedSubject();

			if ($this->mime) {
				$this->mime->buildMail($this);
				$body = "This is a multi-part message in MIME format.".CRLF.$this->body;
			} else {
				$body = $this->getPayload();
				$this->setHeader("Content-Type", "text/plain; charset=UTF-8");
				$this->setHeader("Content-Transfer-Encoding", "8bit");
				$this->setHeader("MIME-Version", "1.0");
			}

			if (isset($this->headers["From"])) {
				$from = $this->headers["From"];
			} elseif (is_array(Config::Get("Mail")) && isset(Config::Get("Mail")["from"])) {
				$from = Config::Get("Mail")["from"];
			}
			/*if (!preg_match("#<(.*)>#", $from)) {
				$from = "<".$from.">";
			}*/

			if (isset($from)) {
				$this->setHeader("From", $from);
			}

			$headers = $this->encodeHeaders();
			//var_dump($headers);

			if (!Config::Get("Mail") || !Config::Get("Mail")["enabled"]) return true;

			$failed = false;
			foreach ($this->recipient as $r) {
				if (Config::Get("Debug")) {
					if (!mail($r, $subject, $body, $headers)) {
						$failed = true;
					}
				} else {
					if (!@mail($r, $subject, $body, $headers)) {
						$failed = true;
					}
				}
			}

			return !$failed;
		} // MailMessage::Send()
	} // class MailMessage
?>
