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
        if (self::varExists($name)) {
            return self::$variables[$name];
        } else {
            return NULL;
        }
    }

    public static function ifCall(Context $ctx, $params) {
        if (isset($params[1]) && self::varExists($params[1])) {
            $parent = $ctx->getParent();
            $parent->formatLines($parent, $ctx->lines);
        }
    }

    public static function ifNotSetCall(Context $ctx, $params) {
        if (isset($params[1]) && !self::varExists($params[1])) {
            $parent = $ctx->getParent();
            $parent->formatLines($parent, $ctx->lines);
        }
    }

    public static function echoCall(Context $ctx, $params) {
        if (isset($params[1])) {
            $parent = $ctx->getParent();
            if (self::varExists($params[1])) {
                $lines = self::getVar($params[1]);
                if (!is_array($lines)) {
                    $lines = preg_split('/\n/', $lines);
                }
                $parent->formatLines($parent, $lines);
            } elseif (isset($params[2])) {
                $parent->formatLine($parent, $params[2]);
            }
        }
    }

    public static function foreachCall(Context $ctx, $params) {
        $itemname = "row";
        if (isset($params[2]) && !empty($params[2])) {
            $itemname = $params[2];
        }

        if (isset($params[1])) {
            $parent = $ctx->getParent();
            if (is_array(self::getVar($params[1]))) {
                foreach (self::getVar($params[1]) as $row) {
                    self::set($itemname, $row);
                    $parent->formatLines($parent, $ctx->lines);
                }
            } else {
                self::set($itemname, self::getVar($name));
                $parent->formatLines($parent->getParent(), $ctx->lines);
            }
        }
    }

    public static function defineCall(Context $ctx, $params) {
        if (!isset($params[1])) return;

        if (substr($params[1], -2) == '[]') {
            self::set(substr($params[1], 0, -2), $ctx->lines);
        } else {
            self::set($params[1], implode("\n", $ctx->lines));
        }
    }

    public static function multiDefCall(Context $ctx, $params) {
        // Parse http-header-like string and get variables.
        $currentVarName = NULL;
        $currentVarValue = NULL;
        $currentVarArray = false;

        foreach ($ctx->lines as $line) {
            if (preg_match('/^([^\s]*?)(\[\])?:\s*(.*)/', $line, $matches)) {
                if (!is_null($currentVarName)) {
                    // Previous variable ends.
                    if ($currentVarArray) {
                        self::set($currentVarName, $currentVarValue);
                    } else {
                        self::set($currentVarName, implode("\n", $currentVarValue));
                    }
                }

                $currentVarName = $matches[1];
                $currentVarValue = array($matches[3]);
                $currentVarArray = (isset($matches[2]) && $matches[2] == "[]");
            } elseif (!is_null($currentVarName) && preg_match('/^ (.*)|^(.*)/', $line, $matches)) {
                if (isset($matches[2]) && !empty($matches[2])) $matches[1] = $matches[2];
                $currentVarValue[] = $matches[1];
            }
        }

        if (!is_null($currentVarName)) {
            if ($currentVarArray) {
                self::set($currentVarName, $currentVarValue);
            } else {
                self::set($currentVarName, implode("\n", $currentVarValue));
            }
        }
    }
}

$vars = new Variables();
Block::registerBlockFormatter("ifdef", array($vars, "ifCall"));
Block::registerBlockFormatter("ifndef", array($vars, "ifNotSetCall"));
Block::registerBlockFormatter("var", array($vars, "echoCall"));
Block::registerBlockFormatter("foreach", array($vars, "foreachCall"));
Block::registerBlockFormatter("define", array($vars, "defineCall"));
Block::registerBlockFormatter("multidef", array($vars, "multiDefCall"));

class InlineVariable extends InlineTrigger {
    function getRegExp(Context $ctx) {
        return '/{\$(.*?)(|(.*?))?}/';
    }

    function callback(Context $ctx, $matches) {
        $params = array($matches[1]);
        if (isset($matches[2])) {
            $params[] = $matches[3];
        }

        Variables::echoCall($ctx, $params);
    }

    static function testSuite() {
        self::testFormat(<<<'EOF'
{{{define:var_name
Value
}}}

{{{var:var_name}}}

{{{define:var_name[]
Row 1
**Row 2**
Row 3
}}}

{{{var:var_name}}}

{{{foreach:var_name
item = {$row}
}}}

{{{ifdef:var_name
var_name exists = {$var_name}
}}}

{{{ifdef:var_name
this should not get printed
}}}

{{{ifndef:another_var
this should get printed
}}}

{{{multidef:
variable1: one line
variable2: first line
 second:line with semicolon
variable3[]: array item 1
 array item 2
 array item 3
}}}

{$variable1}
----
{$variable2}
----
{$variable3}
EOF
,
<<<EOF

<p>
Value
</p>

<p>
Row 1 <strong>Row 2</strong> Row 3
</p>

<p>
item = Row 1 item = <strong>Row 2</strong> item = Row 3
</p>

<p>
var_name exists = Row 1 <strong>Row 2</strong> Row 3
</p>

<p>
this should get printed 
</p>

<p>
one line
</p>
<hr />
<p>
first line second:line with semicolon
</p>
<hr />
<p>
array item 1 array item 2 array item 3
</p>
EOF
);
    }
}

