<?php

namespace lib\formatter\format;

require_once "lib/format/Block.php";

class Variables {
    protected $variables = array();

    public function register(Block $format) {
        $format->registerBlockFormatter("ifdef", array($this, "ifCall"));
        $format->registerBlockFormatter("ifndef", array($this, "ifNotSetCall"));
        $format->registerBlockFormatter("var", array($this, "echoCall"));
        $format->registerBlockFormatter("foreach", array($this, "foreachCall"));
        $format->registerBlockFormatter("define", array($this, "defineCall"));
        $format->registerBlockFormatter("multidef", array($this, "multiDefCall"));
    }

    public function set($name, $value) {
        $this->variables[$name] = $value;
    }

    protected function varExists($name) {
        return isset($this->variables[$name]);
    }

    protected function getVar($name) {
        if ($this->varExists($name)) {
            return $this->variables[$name];
        } else {
            return NULL;
        }
    }

    public function ifCall(Context $ctx, $params) {
        if (isset($params[1]) && $this->varExists($params[1])) {
            $parent = $ctx->getParent();
            $parent->formatLines($parent, $ctx->lines);
        }
    }

    public function ifNotSetCall(Context $ctx, $params) {
        if (isset($params[1]) && !$this->varExists($params[1])) {
            $parent = $ctx->getParent();
            $parent->formatLines($parent, $ctx->lines);
        }
    }

    public function echoCall(Context $ctx, $params) {
        if (isset($params[1])) {
            $parent = $ctx->getParent();
            if ($this->varExists($params[1])) {
                $lines = $this->getVar($params[1]);
                if (!is_array($lines)) {
                    $lines = preg_split('/\n/', $lines);
                }
                $parent->formatLines($parent, $lines);
            } elseif (isset($params[2])) {
                $parent->formatLine($parent, $params[2]);
            }
        }
    }

    public function foreachCall(Context $ctx, $params) {
        $itemname = "row";
        if (isset($params[2]) && !empty($params[2])) {
            $itemname = $params[2];
        }

        if (isset($params[1])) {
            $parent = $ctx->getParent();
            if (is_array($this->getVar($params[1]))) {
                foreach ($this->getVar($params[1]) as $row) {
                    $this->set($itemname, $row);
                    $parent->formatLines($parent, $ctx->lines);
                }
            } else {
                $this->set($itemname, $this->getVar($name));
                $parent->formatLines($parent->getParent(), $ctx->lines);
            }
        }
    }

    public function defineCall(Context $ctx, $params) {
        if (!isset($params[1])) return;

        if (substr($params[1], -2) == '[]') {
            $this->set(substr($params[1], 0, -2), $ctx->lines);
        } else {
            $this->set($params[1], implode("\n", $ctx->lines));
        }
    }

    public function multiDefCall(Context $ctx, $params) {
        // Parse http-header-like string and get variables.
        $currentVarName = NULL;
        $currentVarValue = NULL;
        $currentVarArray = false;

        foreach ($ctx->lines as $line) {
            if (preg_match('/^([^\s]*?)(\[\])?:\s*(.*)/', $line, $matches)) {
                if (!is_null($currentVarName)) {
                    // Previous variable ends.
                    if ($currentVarArray) {
                        $this->set($currentVarName, $currentVarValue);
                    } else {
                        $this->set($currentVarName, implode("\n", $currentVarValue));
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
                $this->set($currentVarName, $currentVarValue);
            } else {
                $this->set($currentVarName, implode("\n", $currentVarValue));
            }
        }
    }
}

class InlineVariable extends InlineTrigger {
    protected $variables;

    function __construct(Variables $variables) {
        $this->variables = $variables;
    }

    function getRegExp(Context $ctx) {
        return '/{\$(.*?)(|(.*?))?}/';
    }

    function callback(Context $ctx, $matches) {
        $params = array($matches[1]);
        if (isset($matches[2])) {
            $params[] = $matches[3];
        }

        $this->variables->echoCall($ctx, $params);
    }

    static function testSuite(\lib\formatter\WikiFormatter $f) {
        self::testFormat($f, <<<'EOF'
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

{{{ifdef:another_var
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

