<?php

namespace lib\formatter\format;

require_once "lib/format/Block.php";

class Variables {
    protected static $variables = array();

    public static function set($name, $value) {
        self::$variables[$name] = $value;
    }

    protected static function varExists($name) {
        return isset(self::$variables[$name]);
    }

    protected static function getVar($name) {
        if ($this->varExists($name)) {
            return self::$variables[$name];
        } else {
            return NULL;
        }
    }

    public static function ifCall(Context $ctx, $params) {
        if (isset($params[1]) && self::varExists($params[1])) {
            $ctx->formatLines($ctx->lines);
        }
    }

    public static function echoCall(Context $ctx, $params) {
        if (isset($params[1])) {
            if (self::varExists($params[1])) {
                $ctx->formatLines(preg_split('/\n/', self::getVar($params[1])));
            }

            if (isset($params[2])) {
                $ctx->formatLine($params[2]);
            }
        }
    }

    public static function foreachCall(Context $ctx, $params) {
        $itemname = "row";
        if (isset($params[2]) && !empty($params[2])) {
            $itemname = $params[2];
        }

        if (isset($params[1])) {
            if (is_array(self::getVar($name))) {
                foreach (self::getVar($name) as $row) {
                    self::set($itemname, $row);
                    $ctx->formatLines($this->lines);
                }
            } else {
                self::set($itemname, self::getVar($name));
                $ctx->formatLines($this->lines);
            }
        }
    }
}

$vars = new Variables();
Block::registerBlockFormatter("ifset", array($vars, "ifCall"));
Block::registerBlockFormatter("var", array($vars, "echoCall"));
Block::registerBlockFormatter("foreach", array($vars, "foreachCall"));

class InlineVariable extends InlineTrigger {
    function getRegExp(Context $ctx) {
        return '/{\$(.*?)(|(.*?))?}/';
    }

    function callback(Context $ctx, $matches) {
        $params = array($matches[1]);
        if (isset($matches[2])) {
            $params[] = $matches[3];
        }

        $vars->echoCall($ctx, $params);
    }
}

