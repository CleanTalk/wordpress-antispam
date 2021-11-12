<?php

use Cleantalk\ApbctWP\DB;
use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

class DbTest extends TestCase
{
    private $db;

    public function setUp()
    {
        global $apbct;
        $apbct = new State( 'cleantalk', array('settings', 'data', 'debug', 'errors', 'remote_calls', 'stats', 'fw_stats') );

        $this->db = DB::getInstance();

    }

    public function testIsTableExists()
    {
        $this->assertFalse($this->db->isTableExists('unknown'));
        $this->assertTrue($this->db->isTableExists('wptests_options'));
    }
}
