<?php

namespace models;

/**
 * Attachment that joins together attachment and it's revision.
 */
class Attachment extends Model {
    const META_CONTENT_TYPE = "content-type";

    protected $id;
    protected $name;
    protected $revision;
    protected $related_page_id;
    protected $created;
    protected $last_modified;
    protected $user_id;
    protected $type;
    protected $type_string;
    protected $bytes;
    protected $width;
    protected $height;

    protected $meta = array();
    protected $_changed_meta = array();

    public function setMeta($name, $value) {
        $this->meta[$name] = $value;
        $this->_changed_meta[$name] = true;
    }

    public function getMeta($name) {
        if (isset($this->meta[$name])) {
            return $this->meta[$name];
        } else {
            return NULL;
        }
    }

    public function listChangedMeta() {
        return array_keys($this->_changed_meta);
    }

    public function clearChangedMeta() {
        $this->_changed_meta = array();
    }
}
