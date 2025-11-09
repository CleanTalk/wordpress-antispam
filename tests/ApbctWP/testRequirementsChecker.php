<?php

use Cleantalk\ApbctWP\ServerRequirementsChecker\ServerRequirementsChecker;
use PHPUnit\Framework\TestCase;

class ServerRequirementsCheckerTest extends TestCase
{
    private $originalMemoryLimit;
    private $originalMaxExecutionTime;
    public function setUp()
    {
        $this->originalMemoryLimit = ini_get('memory_limit');
        $this->originalMaxExecutionTime = ini_get('max_execution_time');
    }
    public function testCheckRequirementsWithValidConfiguration()
    {
        $checker = new ServerRequirementsChecker();
        // Mock PHP environment
        ini_set('memory_limit', '256M');
        ini_set('max_execution_time', '30');

        $warnings = $checker->checkRequirements();

        $this->assertEmpty($warnings, 'No warnings should be returned for valid configuration.');

        ini_set('max_execution_time', '0');

        $warnings = $checker->checkRequirements();

        $this->assertEmpty($warnings, 'No warnings should be returned for valid configuration.');

        ini_set('memory_limit', '-1');

        $warnings = $checker->checkRequirements();

        $this->assertEmpty($warnings, 'No warnings should be returned for valid configuration.');
    }

    public function testCheckRequirementsWithLowMemoryLimit()
    {
        /*$checker = new ServerRequirementsChecker();

        // Mock PHP environment
        ini_set('memory_limit', '64M');

        $warnings = $checker->checkRequirements();

        $this->assertNotEmpty($warnings, 'Warnings should be returned for low memory limit.');
        $this->assertStringContainsString('PHP memory_limit must be at least', $warnings[0]);
        $this->assertStringContainsString('ini_get() returns ' . ini_get('memory_limit'), $warnings[0]);*/
    }

    public function testCheckRequirementsWithLowExecutionTime()
    {
        $checker = new ServerRequirementsChecker();

        // Mock PHP environment
        ini_set('max_execution_time', '10');

        $warnings = $checker->checkRequirements();

        $this->assertNotEmpty($warnings, 'Warnings should be returned for low execution time.');
        $this->assertStringContainsString('max_execution_time must be at least', $warnings[0]);
        $this->assertStringContainsString('ini_get() returns ' . ini_get('max_execution_time'), $warnings[0]);
    }

    public function tearDown()
    {
        // Reset PHP environment settings after tests
        ini_set('memory_limit', $this->originalMemoryLimit);
        ini_set('max_execution_time', $this->originalMaxExecutionTime);
    }
}
