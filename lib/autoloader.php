<?php

/**
 * Autoloader for \Cleantalk\* classes
 *
 * @param string $class
 *
 * @return void
 */

spl_autoload_register(function ($class) {
    // Register class auto loader
    // Custom modules1
    if ( strpos($class, 'Cleantalk') !== false ) {
        $class      = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $class_file = __DIR__ . DIRECTORY_SEPARATOR . $class . '.php';
        if ( file_exists($class_file) ) {
            require_once($class_file);
        }
    }
});
