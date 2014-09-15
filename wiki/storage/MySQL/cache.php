<?php

namespace storage\MySQL;

class Cache extends Module {
    function invalidateWikiCache($key, $trans = NULL) {
        $transactionStarted = false;
        if (is_null($trans)) {
            $trans = $this->base->db->beginRW();
            $transactionStarted = true;
        }

        $trans->query("UPDATE wiki_text_cache SET valid = 0 WHERE LEFT(`key`, %s) = %s", strlen($key), $key);

        if ($transactionStarted) {
            $trans->commit();
        }
    }

    function storeWikiCache($key, $text, $trans = NULL) {
        $transactionStarted = false;
        if (is_null($trans)) {
            if ($this->base->currentTransaction) {
                $trans = $this->base->currentTransaction;
            } else {
                $trans = $this->base->db->beginRW();
                $transactionStarted = true;
            }
        }

        $trans->query("INSERT INTO wiki_text_cache (`key`, wiki_text)
                VALUES (%s, %s)
                ON DUPLICATE KEY UPDATE valid = 1, wiki_text = VALUES(wiki_text)",
            $key, $text);

        if ($transactionStarted) {
            $trans->commit();
        }
    }
}
