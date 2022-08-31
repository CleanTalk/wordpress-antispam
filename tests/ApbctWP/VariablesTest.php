<?php

use Cleantalk\ApbctWP\DB;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Cookie;
use PHPUnit\Framework\TestCase;

require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-updater.php');

class VariablesTest extends TestCase
{
    private $db;

    public function setUp()
    {
        global $apbct;
        $apbct->data['cookies_type'] = 'none';
        $apbct->saveData();
        apbct_run_update_actions('5.182','5.182');

    }

    public function test_NoCookie_set_to_prop()
    {
        Cookie::set('test_name_prop','test_value_prop',0,'','',null,false, 'Lax', false);
    }

    public function test_NoCookie_set_to_db()
    {
        Cookie::set('test_name_db','test_value_db',0,'','',null,false, 'Lax', true);
    }

    public function test_NoCookie_get_from_prop()
    {
        $this->assertIsString(Cookie::get('test_name_prop'));
        $this->assertEquals('test_value_prop', Cookie::get('test_name_prop'));
    }

    public function test_NoCookie_get_from_db()
    {
        $this->assertIsString(Cookie::get('test_name_db'));
        $this->assertEquals('test_value_db', Cookie::get('test_name_db'));
    }
}
