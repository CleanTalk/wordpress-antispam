<?php

use Cleantalk\ApbctWP\DB;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Cookie;
use PHPUnit\Framework\TestCase;

require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-updater.php');

class VariablesTest extends TestCase
{
    private $tests = array();

    public function setUp()
    {
        global $apbct;
        $apbct->data['cookies_type'] = 'none';
        $apbct->saveData();
        apbct_run_update_actions('5.182','5.182');

        $this->tests['no_cookie']['set']['good'] = array(
            'int' => 1,
            'bool' => true,
            'test_name_db' => 'test_value_db',

        );

        $this->tests['no_cookie']['set']['bad'] = array(
            false => 'bool',
            null => null,
            'array' => array('1'=>1, '2'=>'2')
        );

    }

    public function test_NoCookie_correct_statement()
    {
        global $apbct;
        $this->assertEquals('none', $apbct->data['cookies_type']);
    }

    public function test_NoCookie_set()
    {
        global $apbct;
        $apbct->data['key_is_ok'] = true;
        $apbct->saveData();

        //on prop
        foreach($this->tests['no_cookie']['set']['good'] as $key=>$value){
            //on prop
            $result = Cookie::set($key,$value,0,'','',null,false, 'Lax', false);
            $this->assertNotFalse($result, $key . '=>'. var_export($value,1));
            //on db
            $result = Cookie::set($key,$value,0,'','',null,false, 'Lax', true);
            $this->assertNotFalse($result, $key . '=>'. var_export($value,1));
        }

        foreach($this->tests['no_cookie']['set']['bad'] as $key=>$value){
            //on prop
            $result = Cookie::set($key,$value,0,'','',null,false, 'Lax', false);
            $this->assertFalse($result, $key . '=>'. var_export($value,1));
            //on db
            $result = Cookie::set($key,$value,0,'','',null,false, 'Lax', true);
            $this->assertFalse($result, $key . '=>'. var_export($value,1));
        }
    }

    public function test_NoCookie_get()
    {
        //$this->setUp();
        $this->assertIsString(Cookie::get('test_name_prop'));
        $this->assertEquals('', Cookie::get('test_name_prop'));

        $this->assertIsString(Cookie::get('test_name_db'));
        $this->assertEquals('test_value_db', Cookie::get('test_name_db'));

        $this->assertEquals('', Cookie::get(true));
        $this->assertEquals('', Cookie::get(11));
        //$this->assertEquals('', Cookie::get(new ArrayObject()));

    }

}
