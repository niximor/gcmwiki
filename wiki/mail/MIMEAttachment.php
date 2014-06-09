<?php
	namespace mail;

	require_once "mail/MIMEPart.php";

	/**
	 * \brief File attachment.
	 */
	class MIMEAttachment extends MIMEPart {
		function __construct($type, $filename) {
			parent::__construct($type);
			$this->contentDisposition = "attachment;".CRLF." filename=\"".urlencode($filename)."\"";
		}
	} // class MIMEAttachment
?>