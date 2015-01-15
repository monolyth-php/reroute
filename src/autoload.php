<?php

/**
 * PSR-4 compliant autoloader for Reroute code.
 */
spl_autoload_register(function($class) {
    $file = str_replace(['\\', '_'], DIRECTORY_SEPARATOR, $class).'.php';
    $file = preg_replace('@^reroute@', '', $file);
    include realpath(__DIR__).$file;
});

