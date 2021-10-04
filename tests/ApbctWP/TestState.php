<?php

use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

class TestApbctState extends TestCase
{
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
}
