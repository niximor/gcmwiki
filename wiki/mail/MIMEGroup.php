<?php
	namespace mail;

	require_once "mail/MIMEPart.php";

	/**
	 * \brief Group containing MIME parts.
	 *
	 * Each MIME mail must contain at least one MIME group that will
	 * encapsulate the whole MIME body. Each group then can contain sub-groups
	 * or data parts.
	 */
	class MIMEGroup extends MIMEPart {
		const RELATED = 0;			/**< Related content (html + images) */
		const MIXED = 1;			/**< Text and attachments */
		const ALTERNATIVE = 2;		/**< Alternative content (text/html) */

		protected $boundary;
		protected $parts = array();

		static $sectionid = 0;

		function __construct($type) {
			switch ($type) {
				case self::RELATED:
					$this->contentType = "multipart/related";
					break;

				case self::MIXED:
					$this->contentType = "multipart/mixed";
					break;

				case self::ALTERNATIVE:
					$this->contentType = "multipart/alternative";
					break;
			}

			$this->boundary = "--MIME-Boundary-".self::$sectionid."-".md5(time());
			self::$sectionid++;
		}

		function __set($name, $value) {
			switch ($name) {
				// Disable of changing content-type, because we must supply
				// fixed content-type depending on the part type.
				case "contentType":
					break;

				default:
					return parent::__set($name, $value);
			}
		}

		function addPart(MIMEPart $part) {
			$this->parts[] = $part;
			return $part;
		}

		function buildContent() {
			$content = "";

			if (count($this->parts) == 0) {
				$content .= "--".$this->boundary.CRLF.CRLF;
			}

			foreach ($this->parts as $part) {
				$content .= "--".$this->boundary.CRLF;
				$content .= $part->getContent();
			}

			$content .= "--".$this->boundary."--".CRLF;

			$content .= CRLF;

			return $content;
		}

		function getContent() {
			$content = "";

			$content .= "Content-Type: ".$this->contentType.";".CRLF." boundary=\"".$this->boundary."\"".CRLF.CRLF.CRLF;
			$content .= $this->buildContent();

			return $content;
		}

		function buildMail(MailMessage $msg) {
			// Set MIME headers
			$msg->setHeader("MIME-Version", "1.0");
			$msg->setHeader("Content-Type", $this->contentType.";".CRLF." boundary=\"".$this->boundary."\"");

			// Set message body
			$msg->setBody(
				//"This is multi-part MIME message.\r\n\r\n".
				$this->buildContent()
			);
		}
	} // class MIMEGroup
?>