<?php

namespace storage\MySQL;

require_once "storage/storage.php";
require_once "storage/MySQL/base.php";

class Attachments extends Module implements \storage\Attachments {
    /**
     * Load attachment from database.
     * @param struct $filter Filtering options for attachments.
     *     - array $ids List of attachment IDs to load.
     *     - int $relatedPageId ID of related page to use for attachment loading
     *     - string $name Load attachment with given name (exact match).
     * @param array $features Features to load.
     *     - string $feature Name of feature to load. Can be one of following options:
     *         id: Attachment ID
     *         name: Attachment name
     *         revision: Attachment revision
     *         type: Attachment type ID
     *         type_string: Attachment type name
     *         related_page_id: ID of related page
     *         created: Date when the attachment was created
     *         last_modified: Date of last modification of attachment
     *         user_id: User who created this revision of attachment
     *         bytes: Size in bytes of the attachment
     *         width: Width of attachment (if applicable)
     *         height: Height of attachment (if applicable)
     *         "meta:*": Meta information with specified name
     *         meta: All meta information available for attachment
     */
    public function load(\lib\Object $filter, $features = NULL) {
        $columns = array();

        $joins = array();

        $load_meta = array();

        foreach ((array)$features as $feature) {
            switch ($feature) {
                // type_string is name of image type from the attachment_type table.
                case "type_string":
                    $joins[] = "JOIN attachment_type at ON (a.type = at.id)";
                    $columns[] = "at.name AS type_string";
                    break;

                // Plain meta argument means that all metadata for the file will be loaded.
                case "meta":
                    $load_meta = true;
                    break;

                default:
                    // meta:<meta-name> argument means load meta information with specified name.
                    if (substr($feature, 0, strlen("meta:")) == "meta:") {
                        if (is_array($load_meta)) {
                            $load_meta[] = substr($feature, strlen("meta:"));
                        }

                    // other meaning is property name.
                    } elseif (property_exists("\\models\\Attachment", $feature)) {
                        $columns[] = "a.".$feature;
                    } else {
                        // TODO: After conversion to RPC, there will be check directly in the RPC interface,
                        // so no need to implement it now.
                    }
            }
        }

        // ID column is required always, so ensure it is in the list of columns.
        if (empty($columns) || !in_array("a.id", $columns)) {
            $columns[] = "a.id";
        }

        // Build query conditions

        $conditions = array();
        $values = array();

        // Filter by attachment IDs.
        if (!is_null($ids = $filter->getOrDefault("ids", NULL))) {
            $conditions[] = "a.id IN (".implode(",", array_map(function($dummy) { return "%s"; }, $ids)).")";
            $values += $ids;
        }

        // Filter by related page id.
        if (!is_null($relatedPageId = $filter->getOrDefault("relatedPageId", NULL))) {
            $conditions[] = "a.related_page_id = %s";
            $values[] = $relatedPageId;
        }

        // Filter by name.
        if (!is_null($name = $filter->getOrDefault("name", NULL))) {
            $conditions[] = "a.name = %s";
            $values[] = $name;
        }

        // No conditions means nothing is loaded.
        if (empty($conditions)) {
            return array();
        }

        // Build the query itself.
        $query = "SELECT ".implode(", ", $columns)."
            FROM attachments a ".implode(" ", $joins)." WHERE ".implode(" AND ", $conditions);

        $transactionStarted = false;
        if (!$this->base->currentTransaction) {
            $trans = $this->base->db->beginRO();
            $transactionStarted = true;
        } else {
            $trans = $this->base->currentTransaction;
        }

        // Load attachments
        $res = $trans->query($query, $values);
        $res->setClassFactory("\\models\\Attachment");

        $out = array();
        $atById = array();

        foreach ($res as $attachment) {
            $out[] = $attachment;
            $atById[$attachment->getId()] = $attachment;
        }

        // Load metadata. If $load_meta is true, all meta information will be loaded. Otherwise if it is array,
        // only listed meta information will be loaded.
        if (((is_array($load_meta) && !empty($load_meta)) || $load_meta === true) && !empty($out)) {
            $query = "SELECT a.id, m.name, m.value
                FROM attachments_meta m
                JOIN attachments a ON (m.attachment_id = a.id AND m.revision = a.revision)
                WHERE a.id IN (".implode(",", array_fill(0, count($out), "%s")).")";
            $values = array_map(function($at) { return $at->getId(); }, $out);

            // If we have listed meta information names, append it to query.
            if (is_array($load_meta)) {
                $query .= " AND m.name IN (".implode(",", array_fill(0, count($load_meta), "%s")).")";
                $values = array_merge($values, $load_meta);
            }

            $res = $trans->query($query, $values);

            foreach ($res as $meta) {
                if (isset($atById[$meta->id])) {
                    $atById[$meta->id]->setMeta($meta->name, $meta->value);
                }
            }
        }

        // Nothing is changed if we are loading from DB.
        foreach ($out as $attachment) {
            $attachment->clearChangedMeta();
            $attachment->clearChanged();
        }

        if ($transactionStarted) {
            $trans->commit();
        }

        return $out;
    }

    /**
     * Store attachment to database, creating it if not exists, modifying it if it already exists.
     * @param \models\Attachment $attachment Attachment to store.
     */
    public function store(\models\Attachment $attachment) {
        $trans = $this->base->db->beginRW();

        // Resolve type id if given only as type name.
        if (!is_null($attachment->getTypeString())) {
            $attachment->setType($trans->query("SELECT id FROM attachment_type WHERE name = %s", $attachment->getTypeString())->fetch()->id);
        }

        if (!is_null($attachment->getId())) {
            if ($attachment->isChanged("revision")) {
                // Archive old revision.
                $trans->query("INSERT INTO attachments_history (attachment, revision, last_modified, user_id, type, bytes, width, height)
                    SELECT id, revision, COALESCE(last_modified, created), user_id, bytes, width, height FROM attachments
                    WHERE id = %s", $attachment->getId());

                // Copy meta for new revision from latest revision.
                $trans->query("INSERT INTO attachments_meta (attachment_id, revision, name, value)
                    SELECT a.id, %s, m.name, m.value FROM attachments a JOIN attachment_meta m ON (m.attachment_id = a.id AND m.revision = a.revision)
                    WHERE a.id = %s", $attachment->getRevision(), $attachment->getId());
            }

            $columns = array();
            $values = array();

            foreach ($attachment->listChanged() as $column) {
                $columns[] = $column." = %s";
                $values[] = $attachment->$column;
            }

            $values[] = $attachment->getId();

            $trans->query("UPDATE attachments SET ".implode(",", $columns)." WHERE id = %s", $values);
        } else {
            $trans->query("INSERT INTO attachments (name, related_page_id, created, last_modified, user_id, type, bytes, width, height)
                VALUES (%s, %s, NOW(), NULL, %s, %s, %s, %s, %s)",
                $attachment->getName(),
                $attachment->getRelatedPageId(),
                $attachment->getUserId(),
                $attachment->getType(),
                $attachment->getBytes(),
                $attachment->getWidth(),
                $attachment->getHeight());

            $attachment->setId($trans->lastInsertId());
        }

        $insert_meta_query = array();
        $insert_meta_values = array();

        foreach ($attachment->listChangedMeta() as $key) {
            $insert_meta_query[] = "(%s, %s, %s, %s)";
            $insert_meta_values += array($attachment->getId(), $attachment->getRevision(), $key, $attachment->getMeta($key));
        }

        if (!empty($insert_meta_query)) {
            $trans->query("INSERT INTO attachments_meta (attachment_id, revision, name, value)
                VALUES ".implode(",", $insert_meta_query)."
                ON DUPLICATE KEY UPDATE value = VALUES(value)", $insert_meta_values);
        }

        $trans->commit();

        // After store, nothing is changed.
        $attachment->clearChanged();
        $attachment->clearChangedMeta();
    }
}
