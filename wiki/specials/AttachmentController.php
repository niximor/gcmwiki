<?php

namespace specials;

use \lib\libguess\FileType;

require_once "specials/SpecialController.php";
require_once "lib/sizeutil.php";
require_once "lib/libguess/guess.php";
require_once "models/Attachment.php";

/**
 * Attachment controller for the front-end part of attachments module.
 */
class AttachmentController extends SpecialController {
    /**
     * Return max upload file size. Admin can configure max file size in the Wiki config, but this can also
     * be affected by PHP's settings of upload_max_filesize and post_max_size. The minimal value of these three
     * does apply.
     * @return int Max allowed upload file size in bytes.
     */
    public static function maxUploadSize() {
        $uploadMax = \lib\sizeToBytes(ini_get("upload_max_filesize"));
        $postMax = \lib\sizeToBytes(ini_get("post_max_size"));
        $systemLimit = \lib\sizeToBytes(\Config::Get("Attachments.MaxSize"));

        $limit = min($uploadMax, $postMax, $systemLimit);
        return $limit;
    }

    /**
     * Constructor. Passes transparently all arguments to parent constructor.
     * It is used to add Attachments breadcrumb navigation item transparently for all methods here.
     */
    public function __construct() {
        call_user_func_array("parent::__construct", func_get_args());

        $this->template->addNavigation("Attachments", NULL);
    }

    /**
     * Index page shows details of attachment in a user-readable way.
     * @param string $name Attachment name. The attachment must be attached to current related page.
     */
    public function index($name = NULL) {
        // Attachments can be attached only to pages. So ensure we are in page context.
        $this->ensurePageContext();

        // The name is required, without name, the page does not makes sense.
        if (is_null($name)) {
            throw new \view\NotFound();
        }

        $be = $this->getBackend();

        // Attachment can be read as soon as a page can be read (because it does not make sense to deny access
        // to attachment when the attachment can be displayed directly on the page).
        if (!$this->Acl->page_read) {
            throw new \view\AccessDenided();
        }

        $at = $this->getBackend()->getAttachmentsModule();

        // Find the attachment based on related_page_id and name.
        $filter = new \lib\Object();
        $attachments = $at->load($filter
            ->setRelatedPageId($this->relatedPage->getId())
            ->setName($name), array("id", "name", "bytes", "created", "type_string", "width", "height"));

        // If attachment was not found...
        if (count($attachments) == 0) {
            throw new \view\NotFound();
        }

        // Generate view template.
        $child = new \view\Template("attachments/index.php");
        $child->addVariable("Attachment", $attachments[0]);
        $child->addVariable("AttachmentUrl", $this->relatedPage->getFullUrl()."/attachments:get/".$attachments[0]->getName());

        $sizes = array();

        // List available sizes as configured.
        $storage = \Config::Get("__Storage");
        foreach ((array)explode(",", \Config::Get("Attachments.Previews.".$attachments[0]->getTypeString())) as $subId) {
            $subId = trim($subId);
            if (empty($subId)) {
                continue;
            }

            try {
                // Try to create the attachment by fetching it from storage backend.
                if (preg_match("/(contain|fill|crop)?([0-9]+)x([0-9]+)/", $subId, $matches)) {
                    $storage->load($attachments[0], $subId);
                    $sizes[] = array(
                        "id" => $subId,
                        "mode" => (!empty($matches[1]))?$matches[1]:"contain",
                        "width" => $matches[2],
                        "height" => $matches[3]
                    );
                }
            } catch (\Exception $e) {
                // Ignore errors here.
            }
        }

        $child->addVariable("AvailableFormats", $sizes);

        // Add attachment to bread-crumb navigation.
        $this->template->addNavigation($attachments[0]->getName(), $this->template->getSelf());

        // And set the attachments/index template as the view to the master template.
        $this->template->setChild($child);
    }

    public function get($name = NULL) {
        // Attachments can be attached only to pages. So ensure we are in page context.
        $this->ensurePageContext();

        // The name is required, without name, the page does not makes sense.
        if (is_null($name)) {
            throw new \view\NotFound();
        }

        $be = $this->getBackend();

        // Attachment can be read as soon as a page can be read (because it does not make sense to deny access
        // to attachment when the attachment can be displayed directly on the page).
        if (!$this->Acl->page_read) {
            throw new \view\AccessDenided();
        }

        $at = $this->getBackend()->getAttachmentsModule();

        $filter = new \lib\Object();
        $attachments = $at->load($filter
            ->setRelatedPageId($this->relatedPage->getId())
            ->setName($name), array("id", "meta:".\models\Attachment::META_CONTENT_TYPE, "type_string"));

        if (count($attachments) == 0) {
            throw new \view\NotFound();
        }

        $at = $attachments[0];

        $subId = \storage\DataStore::ORIGINAL_FILE;
        if (isset($_REQUEST["s"])) {
            $subId = $_REQUEST["s"];
        }

        $storage = \Config::Get("__Storage");

        try {
            $fileName = $storage->load($at, $subId);

            $ct = $at->getMeta(\models\Attachment::META_CONTENT_TYPE);
            if (is_null($ct)) {
                header("Content-Type: application/octet-stream");
            } else {
                header("Content-Type: ".$ct);
            }

            $f = fopen($fileName, "rb");
            fpassthru($f);
            fclose($f);

            exit;
        } catch (\storage\FileNotFoundException $e) {
            throw new \view\NotFound();
        }
    }

    /**
     * Add new attachment to wiki page.
     */
    public function attach() {
        // Attachments can be attached only to page. So ensure we are in page context.
        $this->ensurePageContext();

        $be = $this->getBackend();

        // Attachments can be attached only if user has specific privilege to attach attachments.
        if (!$this->Acl->attachment_write) {
            throw new \view\AccessDenided();
        }

        // When the form has been posted, process the uploaded file.
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Only if we have uploaded file.
            if (isset($_FILES["file"]) && $_FILES["file"]["error"] == UPLOAD_ERR_OK
                    && is_uploaded_file($_FILES["file"]["tmp_name"])) {

                // Move uploaded file to safe temporary location where we can open handle to it. In certain PHP setups,
                // PHP does not need to have access to the uploaded file itself, so the only safe thing to do with
                // that file is to use the move_uploaded_file function. After we move it to safe location (temp dir
                // in our case), we can do anything we want with the file, and later store it in it's final destination.
                $nm = tempnam(sys_get_temp_dir(), "wikiupload");
                if (move_uploaded_file($_FILES["file"]["tmp_name"], $nm)) {
                    // Create Attachment entry for the file.
                    $at = new \models\Attachment();
                    $at->setName($_POST["name"]);
                    $at->setRevision(1);
                    $at->setBytes(filesize($nm));
                    $at->setUserId(\lib\CurrentUser::ID());
                    $at->setRelatedPageId($this->relatedPage->getId());

                    // Guess the file type using libguess. It needs file name to examine file data
                    // (and extract metadata, if available), and file name when the guess is done by file extension.
                    $guess = \lib\libguess\Guess::guessType($nm, $_FILES["file"]["name"]);

                    // Set the file type in attachment entry.
                    switch ($guess->getClass()) {
                        case FileType::CLASS_IMAGE:
                            $at->setTypeString("image");
                            $at->setWidth($guess->getWidth());
                            $at->setHeight($guess->getHeight());
                            break;

                        case FileType::CLASS_VIDEO:
                            $at->setTypeString("video");
                            break;

                        case FileType::CLASS_AUDIO:
                            $at->setTypeString("audio");
                            break;

                        case FileType::CLASS_TEXT:
                            $at->setTypeString("text");
                            break;

                        case FileType::CLASS_BINARY:
                            $at->setTypeString("binary");
                            break;
                    }

                    $at->setMeta(\models\Attachment::META_CONTENT_TYPE, $guess->getMime());

                    $be = $this->getBackend()->getAttachmentsModule();

                    $dt = \Config::Get("__Storage");
                    if (!is_null($dt)) {
                        // First, we need to store the attachment entry to get attachment ID and revision number
                        // from backend.
                        $be->store($at);

                        // When we have attachment ID and revision number, we can store the file to it's final location
                        // on the file system.
                        $dt->store($nm, $at, \storage\DataStore::ORIGINAL_FILE);

                        foreach ((array)explode(",", \Config::Get("Attachments.Previews.".$at->getTypeString())) as $subId) {
                            $subId = trim($subId);
                            if (empty($subId)) {
                                continue;
                            }

                            try {
                                // Try to create the attachment by fetching it from storage backend.
                                $dt->load($at, $subId);
                            } catch (\Exception $e) {
                                // Ignore errors here.
                            }
                        }
                    } else {
                        \view\Messages::Add(
                            "StorageBackend not configured. Unable to store attachment.",
                            \view\Message::Error);
                    }
                }

                if (file_exists($nm)) {
                    unlink($nm);
                }
            }

            // In any case, when we are in the POST handler, redirect back to prevent double posting.
            $this->template->redirect($this->template->getSelf());
        } else {
            // In case of GET request (we are ignoring other methods than GET and POST here and defaulting
            // all to GET if no POST has been issued), display the attach form.
            $this->template->addNavigation("Attach file", $this->template->getSelf());

            $child = new \view\Template("attachments/attach.php");
            $maxSize = self::maxUploadSize();
            $child->addVariable("UploadMaxBytes", $maxSize);
            $child->addVariable("UploadMaxSize", \lib\humanSize($maxSize));
            $this->template->setChild($child);
        }
    }
}

\Config::registerSpecial("attachments", "\\specials\\AttachmentController");

