<?php

namespace lib\formatter\format;

require_once "lib/format/Trigger.php";

class TableContext extends Context {
    public $table = array();
    public $currentRow = NULL;
}

class TableRow {
    public $isHeaderRow = true;
    public $columns = array();
}

class TableColumn {
    const LEFT = 1;
    const RIGHT = 2;
    const CENTER = 3;
    
    const TOP = 4;
    const MIDDLE = 5;
    const BOTTOM = 6;
    
    public $isHeader = false;
    public $colspan = 1;
    public $rowspan = 1;
    public $content = array();
    public $context = NULL;
    public $align = self::LEFT;
    public $valign = self::MIDDLE;
}

class Table extends LineTrigger {
	function getRegExp(Context $ctx) {
	    return '/^\|\|(.*)\|\|$|^\|\|(-+)$/';
    }
    
    function getEndRegExp(Context $ctx) {
        return '/^(?!\|\|((.*\|\|)|(-+))).*$/';
    }
    
    function getContext(Context $parent, $line, $matches) {
        return new TableContext($parent, $this);
    }
    
    function callLine(Context $context, $line, $matches) {    
        if ((isset($matches[2]) && !empty($matches[2]) && $matches[2][0] == "-") || is_null($context->currentRow)) {
            if (!is_null($context->currentRow)) $break = true; else $break = false;
            $context->currentRow = new TableRow();
            $context->table[] = $context->currentRow;
            
            if ($break) {
                return;
            }
        }
        
        $colIndex = 0;
        $count = count($context->currentRow->columns);
        
        $cols = preg_split('/\|\|/', $matches[1]);
        foreach ($cols as $val) {
            if (empty($val) && $count > 0) {
                $context->currentRow->columns[$colIndex - 1]->colspan++;
                continue;
            }
        
            if ($colIndex + 1 > $count) {
                $col = new TableColumn();
                $col->context = new Context($context);
                
                if (preg_match('/^\^(.*)\^$/', $val, $matches)) {
                    $col->isHeader = true;
                    $val = $matches[1];
                } else {
                    $context->currentRow->isHeaderRow = false;
                }
                
                if ($val[0] == "<") {
                    $col->align = TableColumn::LEFT;
                    $val = substr($val, 1);
                } elseif ($val[0] == ">") {
                    $col->align = TableColumn::RIGHT;
                    $val = substr($val, 1);
                } else {
                    $col->align = TableColumn::CENTER;
                }
                
                $context->currentRow->columns[] = $col;
                
                $count++;
            } else {
                $col = $context->currentRow->columns[$colIndex];
            }
            
            $col->content[] = $val;
            
            $colIndex++;
        }
    }
    
    function callEnd(Context $context) {    
        $context->generateHTML("<table>\n");
        
        $header = NULL;
        
        foreach ($context->table as $row) {
            if ($row->isHeaderRow && is_null($header)) {
                $header = true;
                $context->generateHTML("\t<thead>\n");
            } elseif ($header && !$row->isHeaderRow) {
                $context->generateHTML("\t</thead>\n");
                $context->generateHTML("\t<tbody>\n");
                $header = false;
            } elseif (is_null($header) && !$row->isHeaderRow) {
                $context->generateHTML("\t</tbody>\n");
                $header = false;
            }
        
            $context->generateHTML("\t\t<tr>\n");
            
            foreach ($row->columns as $column) {
                $tag = "td";
                if ($column->isHeader) $tag = "th";
                
                $attribs = array();
                if ((!$column->isHeader && $column->align != TableColumn::LEFT) || ($column->isHeader && $column->align != TableColumn::CENTER)) {
                    switch ($column->align) {
                        case TableColumn::LEFT: $align = "left"; break;
                        case TableColumn::RIGHT: $align = "right"; break;
                        case TableColumn::CENTER: $align = "center"; break;
                    }
                    
                    if (isset($align)) {
                        $attribs[] = sprintf("style=\"text-align: %s;\"", $align);
                    }
                }
                
                if ($column->colspan > 1) {
                    $attribs[] = sprintf("colspan=\"%d\"", $column->colspan);
                }
                
                if ($column->rowspan > 1) {
                    $attribs[] = sprintf("rowspan=\"%d\"", $column->rowspan);
                }
                
                if ($attribs) {
                    $context->generateHTML(sprintf("\t\t\t<%s %s>", $tag, implode(" ", $attribs)));
                } else {
                    $context->generateHTML(sprintf("\t\t\t<%s>", $tag));
                }
                
                $context->formatLines($column->context, $column->content);
                $context->generateHTML(sprintf("</%s>\n", $tag));
            }
            
            $context->generateHTML("\t\t</tr>\n");
        }
        
        if ($header) {
            $context->generateHTML("\t</thead>\n");
        } elseif (!is_null($header)) {
            $context->generateHTML("\t</tbody>\n");
        }
        
        $context->generateHTML("</table>\n");
    }
    
    static function testSuite() {
        self::testFormat(
<<<EOF
||^column 1^||^column 2^||^column 3^||
||-
||>1||2||<3||
||-
||multi line column||x ||y ||
||continues on multiple lines|| || ||
||* and contains list|| || ||
||* with multiple items|| || ||
|| * and levels|| || ||
||* and first level again|| || ||
||and something that is not a list.|| || ||
EOF
,
<<<EOF
<table>
	<thead>
		<tr>
			<th>column 1</th>
			<th>column 2</th>
			<th>column 3</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td style="text-align: right;">1</td>
			<td style="text-align: center;">2</td>
			<td>3</td>
		</tr>
		<tr>
			<td style="text-align: center;">multi line column continues on multiple lines<ul>
	<li>and contains list</li>
	<li>with multiple items
		<ul>
			<li>and levels</li>
		</ul>
	</li>
	<li>and first level again</li>
</ul>
and something that is not a list.</td>
			<td style="text-align: center;">x</td>
			<td style="text-align: center;">y</td>
		</tr>
	</tbody>
</table>
EOF
);
    }
}

