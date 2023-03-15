<?php

use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Error\Notice;

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

    //UpdateVars section

    public function testAutoSaveVars__remote_calls(){
        global $apbct;
        //drop state var
        $apbct->remote_calls = new ArrayObject();
        $apbct->save('remote_calls');
        //drop db option
        update_option('cleantalk_remote_calls',array());
        //run update vars into update actions
        apbct_run_update_actions('6.1','6.2');
        $db_result = get_option('cleantalk_remote_calls')['post_api_key'];
        $this->assertEquals(array ('last_call' => 0,), $db_result);
    }

    public function testAutoSaveVars__settings(){
        global $apbct;
        //drop state var
        $apbct->settings = new ArrayObject();
        $apbct->save('settings');
        //drop db option
        update_option('cleantalk_settings',array());
        //run update vars into update actions
        apbct_run_update_actions('6.1','6.2');
        $db_result = get_option('cleantalk_settings')['forms__registrations_test'];
        $this->assertEquals(1, $db_result);
    }

    public function testAutoSaveVars__data(){
        global $apbct;
        //drop state var
        $apbct->data = new ArrayObject();
        $apbct->save('data');
        //drop db option
        update_option('cleantalk_data',array());
        //run update vars into update actions
        apbct_run_update_actions('6.1','6.2');
        $db_result = get_option('cleantalk_data')['js_key_lifetime'];
        $this->assertEquals(86400, $db_result);
    }

    public function testAutoSaveVars__network_settings(){
        global $apbct;
        //drop state var
        $apbct->network_settings = new ArrayObject();
        $apbct->save('network_settings');
        //drop db option
        update_option('cleantalk_network_settings',array());
        //run update vars into update actions
        apbct_run_update_actions('6.1','6.2');
        $db_result = get_option('cleantalk_network_settings')['multisite__white_label__plugin_name'];
        $this->assertEquals('Anti-Spam by CleanTalk', $db_result);
    }

    public function testAutoSaveVars__network_data(){
        global $apbct;
        //drop state var
        $apbct->network_data = new ArrayObject();
        $apbct->save('network_data');
        //drop db option
        update_option('cleantalk_network_data',array());
        //run update vars into update actions
        apbct_run_update_actions('6.1','6.2');
        $db_result = get_option('cleantalk_network_data')['moderate'];
        $this->assertEquals(0, $db_result);
    }

    public function testAutoSaveVars__stats(){
        global $apbct;
        //set mock object
        $test_stats = array(
            'sfw'            => array(
                'sending_logs__timestamp' => 10000,
                'last_send_time'          => 0,
                'last_send_amount'        => 0,
                'last_update_time'        => 0,
                'last_update_way'         => '',
                'entries'                 => 0,
            ),
            'last_sfw_block' => array(
                'time' => 0,
                'ip'   => '',
            ),
            'last_request'   => array(
                'time'   => 0,
                'server' => '',
            ),
            'requests'       => array(
                '0' => array(
                    'amount'       => 1,
                    'average_time' => 0,
                ),
            ),
            'plugin'         => array(
                'install__timestamp'             => 0,
                'activation__timestamp'          => 0,
                'activation_previous__timestamp' => 0,
                'activation__times'              => 0,
                'plugin_is_being_updated'        => 0,
            ),
            'cron'           => array(
                'last_start' => 0,
            ),
        );
        $test_stats = new ArrayObject($test_stats);
        //set mock db option
        update_option('cleantalk_stats',$test_stats);
        //set new mock state var
        $apbct->stats = $test_stats;
        $apbct->save('stats');
        //run update vars into update actions
        apbct_run_update_actions('6.1','6.2');
        $db_result = get_option('cleantalk_stats')['sfw']['update_period'];
        $this->assertEquals(14400, $db_result);
        $db_result = get_option('cleantalk_stats')['sfw']['sending_logs__timestamp'];
        $this->assertEquals(10000, $db_result);

    }

    public function testAutoSaveVars__fw_stats(){
        global $apbct;
        //drop state var
        $apbct->fw_stats = new ArrayObject();
        $apbct->save('fw_stats');
        //drop db option
        update_option('cleantalk_fw_stats',array());
        //run update vars into update actions
        apbct_run_update_actions('6.1','6.2');
        $db_result = get_option('cleantalk_fw_stats')['firewall_updating_id'];
        $this->assertEquals(null, $db_result);
    }

    public function testAutoSaveVars__fw_stats_await_exception_without_var_updater(){
        global $apbct;
        //drop state var
        $apbct->fw_stats = new ArrayObject();
        $apbct->save('fw_stats');
        //drop db option
        update_option('cleantalk_fw_stats',array());
        //do not run update vars into update actions

        //apbct_run_update_actions('6.1','6.2');

        //await udefined index
        $this->expectException(Notice::class);
        $db_result = get_option('cleantalk_fw_stats')['firewall_updating_id'];
    }
}
