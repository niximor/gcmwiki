<?php

namespace lib;

class path {
    // This is only class holding various static methods, don't need to create instances.
    private function __construct() { }

    /**
     * Join two or more paths together.
     * @param array of string $path1 Path 1 to be joined
     * @param array of string $path2 Path 2 to be joined
     * @param array of string ... Any other paths to be joined together.
     */
    public static function join($path1, $path2) {
        $args = func_get_args();
        $paths = array();
        foreach ($args as $arg) {
            if (is_array($arg)) {
                $paths = array_merge($paths, $arg);
            } else {
                $paths = array_merge($paths, explode("/", (string)$arg));
            }
        }

        $joined = join("/", $paths);

        // Cut off two slashes next to each other.
        $joined = preg_replace("#/{2,}#", "/", $joined);

        // Cut off single dot where possible.
        $joined = preg_replace('#^\\./|/\\.(?=/)|/\\.$|^\\.$#', '', $joined);

        // Cut off double dots where possible.
        $joined = preg_replace('#^(.*)((^|/).*?)(/\\.\\.(/)|/\\.\\.$)#', '\\1\\5', $joined);

        return rtrim($joined, "/");
    }
}
