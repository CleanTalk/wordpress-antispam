<?php

use PHPUnit\Framework\TestCase;

class DebugMethodTest extends TestCase
{
    public function testDebugMethodNeverCalled()
    {
        // Get all PHP files in the plugin directory
        $pluginDirectory = dirname(__DIR__, 2);
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pluginDirectory),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $phpFiles = new RegexIterator($files, '/\.php$/');

        // Iterate through each file and check for the debug method call
        foreach ($phpFiles as $file) {
            $filePath = $file->getRealPath();

            // Skip files in the vendor directory
            if (
                strpos($filePath, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false ||
                strpos($filePath, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR) !== false
            ) {
                continue;
            }

            $content = @file_get_contents($filePath);
            if (strpos($content, '->debug(') === false) {
                continue; // No debug call found, continue to next file
            }

            // Assert that the debug method is not called
            $this->assertTrue(false, "The debug method is called in file: {$filePath}");
        }
        $this->assertTrue(true, "The debug method is never called.");
    }
}
