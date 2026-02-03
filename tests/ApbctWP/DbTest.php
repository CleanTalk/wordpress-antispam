<?php

use Cleantalk\ApbctWP\DB;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\UpdatePlugin\DbAnalyzer;
use PHPUnit\Framework\TestCase;

class DbTest extends TestCase
{
    private $db;

    public function setUp(): void
    {
        global $apbct;
        $apbct = new State( 'cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats') );

        $this->db = DB::getInstance();

    }

    public function testIsTableExists()
    {
        $this->assertFalse($this->db->isTableExists('unknown'));
        $this->assertTrue($this->db->isTableExists('wptests_options'));
    }

    public function testLogSchemaErrorsAddsNewMessage()
    {
        global $apbct;
        $apbct->data['sql_schema_errors'] = array();

        DbAnalyzer::logSchemaErrors(array('Test message'), 'Test function');

        $this->assertCount(1, $apbct->data['sql_schema_errors']);
        $this->assertStringContainsString('Test message', $apbct->data['sql_schema_errors'][0]['message']);
        $this->assertEquals('Test function', $apbct->data['sql_schema_errors'][0]['function']);
    }

    public function testLogSchemaErrorsRemovesOldestEntries()
    {
        global $apbct;
        $apbct->data['sql_schema_errors'] = array_fill(0, 10, array('message' => 'old message', 'function' => 'old function'));

        DbAnalyzer::logSchemaErrors(array('Test message'), 'New function');

        $this->assertCount(10, $apbct->data['sql_schema_errors']);
        $this->assertStringContainsString('Test message', $apbct->data['sql_schema_errors'][9]['message']);
        $this->assertEquals('New function', $apbct->data['sql_schema_errors'][9]['function']);
        $this->assertEquals('old message', $apbct->data['sql_schema_errors'][0]['message']);
    }

    public function testLogSchemaErrorsHandlesEmptyLog()
    {
        global $apbct;
        if (isset($apbct->data['sql_schema_errors'])) {
            unset($apbct->data['sql_schema_errors']);
        }

        DbAnalyzer::logSchemaErrors(array('Test message'), 'Test function');

        $this->assertArrayHasKey('sql_schema_errors', $apbct->data);
        $this->assertCount(1, $apbct->data['sql_schema_errors']);
        $this->assertStringContainsString('Test message', $apbct->data['sql_schema_errors'][0]['message']);
        $this->assertEquals('Test function', $apbct->data['sql_schema_errors'][0]['function']);
    }

    public function testLogSchemaErrorsHandlesNonArrayLog()
    {
        global $apbct;
        $apbct->data['sql_schema_errors'] = 'invalid log';

        DbAnalyzer::logSchemaErrors(array('Test message'), 'Test function');

        $this->assertInstanceOf('ArrayObject', $apbct->data['sql_schema_errors']);
        $this->assertCount(1, $apbct->data['sql_schema_errors']);
        $this->assertStringContainsString('Test message', $apbct->data['sql_schema_errors'][0]['message']);
        $this->assertEquals('Test function', $apbct->data['sql_schema_errors'][0]['function']);
    }
}
