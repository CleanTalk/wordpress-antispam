<?php

use Cleantalk\ApbctWP\ServerRequirementsChecker\ServerRequirementsChecker;
use PHPUnit\Framework\TestCase;

class TestRequirementsChecker extends TestCase
{
    public function testCheckRequirementsWithValidConfiguration()
    {
        $checker = $this->getMockBuilder(ServerRequirementsChecker::class)
                        ->setMethods(['getRequiredParameterValue'])
                        ->getMock();

        $checker->method('getRequiredParameterValue')
                ->willReturnCallback(function($param) {
                    switch ($param) {
                        case 'php_version': return '7.4.0';
                        case 'curl_support': return true;
                        case 'allow_url_fopen': return true;
                        case 'memory_limit': return '256M';
                        case 'max_execution_time': return '30';
                        case 'curl_multi_exec': return true;
                        default: return null;
                    }
                });

        $warnings = $checker->checkRequirements();
        $this->assertNull($warnings, 'No warnings should be returned for valid configuration.');
    }

    public function testCheckRequirementsWithUnlimitedResources()
    {
        $checker = $this->getMockBuilder(ServerRequirementsChecker::class)
                        ->setMethods(['getRequiredParameterValue'])
                        ->getMock();

        $checker->method('getRequiredParameterValue')
                ->willReturnCallback(function($param) {
                    switch ($param) {
                        case 'php_version': return '7.4.0';
                        case 'curl_support': return true;
                        case 'allow_url_fopen': return true;
                        case 'memory_limit': return '-1'; // unlimited memory
                        case 'max_execution_time': return '0'; // unlimited time
                        case 'curl_multi_exec': return true;
                        default: return null;
                    }
                });

        $warnings = $checker->checkRequirements();
        $this->assertNull($warnings, 'No warnings should be returned for unlimited configuration.');
    }

    public function testCheckRequirementsWithLowMemoryLimit()
    {
        $checker = $this->getMockBuilder(ServerRequirementsChecker::class)
                        ->setMethods(['getRequiredParameterValue'])
                        ->getMock();

        $checker->method('getRequiredParameterValue')
                ->willReturnCallback(function($param) {
                    switch ($param) {
                        case 'php_version': return '7.4.0';
                        case 'curl_support': return true;
                        case 'allow_url_fopen': return true;
                        case 'memory_limit': return '64M'; // low memory
                        case 'max_execution_time': return '60';
                        case 'curl_multi_exec': return true;
                        default: return null;
                    }
                });

        $warnings = $checker->checkRequirements();

        $this->assertNotEmpty($warnings, 'Warnings should be returned for low memory limit.');
        $this->assertStringContainsString('PHP memory_limit must be at least', $warnings[0]);
        $this->assertStringContainsString('ini_get() returns 64M', $warnings[0]);
    }

    public function testCheckRequirementsWithLowExecutionTime()
    {
        $checker = $this->getMockBuilder(ServerRequirementsChecker::class)
                        ->setMethods(['getRequiredParameterValue'])
                        ->getMock();

        $checker->method('getRequiredParameterValue')
                ->willReturnCallback(function($param) {
                    switch ($param) {
                        case 'php_version': return '7.4.0';
                        case 'curl_support': return true;
                        case 'allow_url_fopen': return true;
                        case 'memory_limit': return '256M';
                        case 'max_execution_time': return '10'; // low execution time
                        case 'curl_multi_exec': return true;
                        default: return null;
                    }
                });

        $warnings = $checker->checkRequirements();

        $this->assertNotEmpty($warnings, 'Warnings should be returned for low execution time.');
        $this->assertStringContainsString('max_execution_time must be at least', $warnings[0]);
        $this->assertStringContainsString('ini_get() returns 10', $warnings[0]);
    }

    public function testCheckRequirementsWithOldPhpVersion()
    {
        $checker = $this->getMockBuilder(ServerRequirementsChecker::class)
                        ->setMethods(['getRequiredParameterValue'])
                        ->getMock();

        $checker->method('getRequiredParameterValue')
                ->willReturnCallback(function($param) {
                    switch ($param) {
                        case 'php_version': return '5.5.0'; // old PHP version
                        case 'curl_support': return true;
                        case 'allow_url_fopen': return true;
                        case 'memory_limit': return '256M';
                        case 'max_execution_time': return '60';
                        case 'curl_multi_exec': return true;
                        default: return null;
                    }
                });

        $warnings = $checker->checkRequirements();

        $this->assertNotEmpty($warnings, 'Warnings should be returned for old PHP version.');
        $this->assertStringContainsString('PHP version must be at least 5.6', $warnings[0]);
    }

    public function testCheckRequirementsWithoutCurlSupport()
    {
        $checker = $this->getMockBuilder(ServerRequirementsChecker::class)
                        ->setMethods(['getRequiredParameterValue'])
                        ->getMock();

        $checker->method('getRequiredParameterValue')
                ->willReturnCallback(function($param) {
                    switch ($param) {
                        case 'php_version': return '7.4.0';
                        case 'curl_support': return false; // no cURL
                        case 'allow_url_fopen': return false; // no allow_url_fopen
                        case 'memory_limit': return '256M';
                        case 'max_execution_time': return '60';
                        case 'curl_multi_exec': return true;
                        default: return null;
                    }
                });

        $warnings = $checker->checkRequirements();

        $this->assertNotEmpty($warnings, 'Warnings should be returned for missing cURL and allow_url_fopen.');
        $this->assertStringContainsString('cURL support is required', $warnings[0]);
        $this->assertStringContainsString('allow_url_fopen must be enabled if cURL is not available', $warnings[1]);
    }

    public function testCheckRequirementsWithoutCurlMultiExec()
    {
        $checker = $this->getMockBuilder(ServerRequirementsChecker::class)
                        ->setMethods(['getRequiredParameterValue'])
                        ->getMock();

        $checker->method('getRequiredParameterValue')
                ->willReturnCallback(function($param) {
                    switch ($param) {
                        case 'php_version': return '7.4.0';
                        case 'curl_support': return true;
                        case 'allow_url_fopen': return true;
                        case 'memory_limit': return '256M';
                        case 'max_execution_time': return '60';
                        case 'curl_multi_exec': return false; // no curl_multi_exec
                        default: return null;
                    }
                });

        $warnings = $checker->checkRequirements();

        $this->assertNotEmpty($warnings, 'Warnings should be returned for missing curl_multi_exec.');
        $this->assertStringContainsString("function 'curl_multi_exec' is not available", $warnings[0]);
    }

    public function testCheckRequirementsWithMultipleIssues()
    {
        $checker = $this->getMockBuilder(ServerRequirementsChecker::class)
                        ->setMethods(['getRequiredParameterValue'])
                        ->getMock();

        $checker->method('getRequiredParameterValue')
                ->willReturnCallback(function($param) {
                    switch ($param) {
                        case 'php_version': return '5.5.0'; // multiple issues
                        case 'curl_support': return false;
                        case 'allow_url_fopen': return false;
                        case 'memory_limit': return '64M';
                        case 'max_execution_time': return '10';
                        case 'curl_multi_exec': return false;
                        default: return null;
                    }
                });

        $warnings = $checker->checkRequirements();

        $this->assertCount(6, $warnings, 'Should return warnings for all issues.');
    }
}
