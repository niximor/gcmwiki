<?php
	namespace mail;

	/**
	 * \brief One part in the MIME document.
	 */
	class MIMEPart {
		protected $contentType;
		protected $contentDisposition;
		protected $contentID;

		protected $content = null;

		protected $f;
		protected $dataStart;
		protected $dataLength;

		protected $headers;

		/**
		 * Constructor.
		 * @param file|string $contentType If file, points to resource where
		 *   mail file exists. If string, specifies content type of the part.
		 */
		function __construct($contentType) {
			if (is_resource($contentType)) {
				$this->f = $contentType;
			} else {
				$this->contentType = $contentType;
			}
			//$this->contentDisposition = "inline";
		}

		/**
		 * Set part content.
		 */
		function setData($content) {
			$this->content = $content;
		}

		/**
		 * Get data of the part.
		 */
		function getData() {
			if (!$this->content) {
				fseek($this->f, $this->dataStart, SEEK_SET);
				return fread($this->f, $this->dataLength);
			} else {
				return $this->content;
			}
		}

		function getContent() {
			$content = "";

			$content .= "Content-Type: ".$this->contentType.CRLF;

			if (!empty($this->contentDisposition)) {
				$content .= "Content-Disposition: ".$this->contentDisposition.CRLF;
			}

			$content .= "Content-Transfer-Encoding: base64".CRLF;

			if (!empty($this->contentID)) {
				$content .= "Content-ID: <".$this->contentID.">".CRLF;
			}

			foreach ($this->headers as $name=>$value) {
				$content .= $name.": ".$value.CRLF;
			}

			$content .= CRLF;

			$content .= chunk_split(base64_encode($this->content), 76, CRLF).CRLF;

			return $content;
		}

		function __get($name) {
			switch ($name) {
				case "contentType":
				case "contentDisposition":
				case "contentID":
					return $this->$name;

				default:
					if (isset($this->headers[$name])) {
						return $this->headers[$name];
					} else {
						return NULL;
					}
			}
		}

		function __set($name, $value) {
			switch ($name) {
				case "contentType":
				case "contentDisposition":
				case "contentID":
					$this->$name = $value;
					break;

				default:
					$this->headers[$name] = $value;
					break;
			}
		}
	} // class MIMEPart
?>