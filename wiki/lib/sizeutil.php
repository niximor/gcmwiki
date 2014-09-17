<?php

namespace lib;

define('SIZE_MULTIPLY', 1024);
define('SIZE_MULTIPLY_SI', 1000);
define('BITS_IN_BYTE', 8);

function sizeToBytes($size) {
    $outSize = 0;

    if (preg_match("/([0-9]+(\.[0-9]+)?)\s*(B|kB|KB|kiB|KiB|MB|MiB|GB|GiB|TB|TiB|PB|PiB|b|kb|kib|Kb|Kib|Mb|Mib|Gb|Gib|Tb|Tib|Pb|Pib|k|K|M|G|T|P)/", $size, $matches)) {
        $outSize = (float)$matches[1];

        switch ($matches[3]) {
            case "PB":
                $outSize *= SIZE_MULTIPLY_SI;
            case "TB":
                $outSize *= SIZE_MULTIPLY_SI;
            case "GB":
                $outSize *= SIZE_MULTIPLY_SI;
            case "MB":
                $outSize *= SIZE_MULTIPLY_SI;
            case "kB":
            case "KB":
                $outSize *= SIZE_MULTIPLY_SI;
                break;

            case "PiB":
            case "P":
                $outSize *= SIZE_MULTIPLY;
            case "TiB":
            case "T":
                $outSize *= SIZE_MULTIPLY;
            case "GiB":
            case "G":
                $outSize *= SIZE_MULTIPLY;
            case "MiB":
            case "M":
                $outSize *= SIZE_MULTIPLY;
            case "KiB":
            case "kiB":
            case "k":
            case "K":
                $outSize *= SIZE_MULTIPLY;
                break;

            case "Pb":
                $outSize *= SIZE_MULTIPLY_SI;
            case "Tb":
                $outSize *= SIZE_MULTIPLY_SI;
            case "Gb":
                $outSize *= SIZE_MULTIPLY_SI;
            case "Mb":
                $outSize *= SIZE_MULTIPLY_SI;
            case "kb":
            case "Kb":
                $outSize *= SIZE_MULTIPLY_SI;
            case "b":
                $outSize /= BITS_IN_BYTE;
                break;

            case "Pib":
                $outSize *= SIZE_MULTIPLY;
            case "Gib":
                $outSize *= SIZE_MULTIPLY;
            case "Mib":
                $outSize *= SIZE_MULTIPLY;
            case "kib":
            case "Kib":
                $outSize *= SIZE_MULTIPLY;
                $outSize /= BITS_IN_BYTE;
                break;
        }
    }

    return $outSize;
}

function humanSize($size) {
    $units = array("B", "kB", "MB", "GB", "TB", "PB");
    $size = (float)$size;
    $num = 0;
    while ($size > SIZE_MULTIPLY) {
        if (!isset($units[$num])) break;

        $size /= SIZE_MULTIPLY;
        ++$num;
    }

    return sprintf("%1.2f%s", $size, $units[$num]);
}
