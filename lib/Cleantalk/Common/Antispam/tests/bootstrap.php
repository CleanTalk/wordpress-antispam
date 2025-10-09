<?php

/**
 * Autoloader for \CleantalkSP\* classes
 *
 * @param string $class
 *
 * @return void
 */

spl_autoload_register(function ($class) {

    // Register class auto loader
    // Custom modules1
    if ( strpos($class, 'Cleantalk') !== false ) {
        $class      = str_replace('Cleantalk\Common\Antispam\\', DIRECTORY_SEPARATOR, $class);
        $class_file = dirname(__DIR__) . $class . '.php';
        if ( file_exists($class_file) ) {
            require_once($class_file);
        }
        $lib_class_file = dirname(__DIR__) . '\lib\\' . $class . '.php';
        if ( file_exists($lib_class_file) ) {
            require_once($lib_class_file);
        }
    }
});
