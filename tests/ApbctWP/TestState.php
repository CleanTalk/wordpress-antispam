<?php

use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

class TestApbctState extends TestCase
{

    public function getState(){
        return new State( 'cleantalk', array('settings', 'data', 'debug', 'errors', 'remote_calls', 'stats', 'fw_stats') );
    }

    public function testIsHaveErrors_noErrors()
    {
        $apbct = new State( 'cleantalk', array('settings', 'data', 'debug', 'errors', 'remote_calls', 'stats', 'fw_stats') );
        $this->assertFalse( $apbct->isHaveErrors() );
    }

    public function testIsHaveErrors_haveErrors()
    {
        update_option( 'cleantalk_errors', array( 'error_type' => 'Error text' ) );
        $apbct = new State( 'cleantalk', array('settings', 'data', 'debug', 'errors', 'remote_calls', 'stats', 'fw_stats') );
        $this->assertTrue( $apbct->isHaveErrors() );
    }

    public function testIsHaveErrors_emptyErrors()
    {
        update_option( 'cleantalk_errors', array() );
        $apbct = new State( 'cleantalk', array('settings', 'data', 'debug', 'errors', 'remote_calls', 'stats', 'fw_stats') );
        $this->assertFalse( $apbct->isHaveErrors() );
    }

    public function testIsHaveErrors_emptyInnerErrors()
    {
        update_option( 'cleantalk_errors', array( 'error_type' => array() ) );
        $apbct = new State( 'cleantalk', array('settings', 'data', 'debug', 'errors', 'remote_calls', 'stats', 'fw_stats') );
        $this->assertFalse( $apbct->isHaveErrors() );
    }

    public function testIsHaveErrors_filledInnerErrors()
    {
        update_option( 'cleantalk_errors', array( 'error_type' => array( 'error_text' => 'Error text' ) ) );
        $apbct = new State( 'cleantalk', array('settings', 'data', 'debug', 'errors', 'remote_calls', 'stats', 'fw_stats') );
        $this->assertTrue( $apbct->isHaveErrors() );
    }

    public function testAutoUpdateVars__remote_calls(){
        update_option('cleantalk_remote_calls',array());
        apbct_run_update_actions('6.1','6.2');
        $db_result = get_option('cleantalk_remote_calls')['post_api_key'];
        $this->assertEquals(array ('last_call' => 0,), $db_result);
    }

    public function testAutoUpdateVars__settings(){
        global $apbct;
        update_option('cleantalk_settings',array());
        apbct_run_update_actions('6.1','6.2');
        $db_result = get_option('cleantalk_settings')['forms__registrations_test'];
        $this->assertEquals(1, $db_result);

        error_log('CTDEBUG: [' . __FUNCTION__ . '] []: ' . var_export($apbct->storage['data']['auto_update_vars__call'],true));
    }

    public function testAutoUpdateVars__data(){
        update_option('cleantalk_data',array());
        apbct_run_update_actions('6.1','6.2');
        $db_result = get_option('cleantalk_data')['js_key_lifetime'];
        $this->assertEquals(86400, $db_result);
    }
}
