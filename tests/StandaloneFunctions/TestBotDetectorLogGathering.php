<?php

use Cleantalk\ApbctWP\Variables\AltSessions;
use Cleantalk\ApbctWP\Variables\NoCookie;
use Cleantalk\ApbctWP\Variables\Post;

class TestBotDetectorLogGathering extends ApbctTestCase
{
    private function getBrowserState($log = '[["success"],[true]]')
    {
        return json_encode(array(
            'frontend_data_log' => $log,
            'botd_logic_loaded' => 1,
            'botd_wrapper_loaded' => 1,
        ));
    }

    private function getExpected($status, $error_msg, $state)
    {
        return json_encode(array(
            'plugin_status' => $status,
            'error_msg' => $error_msg,
            'apbct_browser_state' => $state,
        ));
    }

    private function getDefaultState()
    {
        return array(
            'frontend_data_log' => '',
            'botd_logic_loaded' => 0,
            'botd_wrapper_loaded' => 0,
        );
    }

    protected function setUp(): void
    {
        global $apbct;
        $apbct->data['cookies_type'] = 'alternative';
        $apbct->data['bot_detector_enabled'] = '1';
        $this->resetTransports();
    }

    protected function tearDown(): void
    {
        $this->resetTransports();
    }

    /**
     * Every transport keeps its own state between the calls:
     * Post caches each variable in the singleton, NoCookie holds a static array,
     * AltSessions store the values in the DB. Without the full reset the tests
     * would depend on the execution order.
     */
    private function resetTransports()
    {
        unset($_POST['apbct_browser_state'], $_POST['data']);
        Post::getInstance()->variables = array();
        NoCookie::$no_cookies_data = array();
        AltSessions::wipe();
    }

    public function test_returnsBrowserStateFromAltSessions()
    {
        AltSessions::set('apbct_browser_state', $this->getBrowserState());

        $expected = $this->getExpected('OK', '', array(
            'frontend_data_log' => '[["success"],[true]]',
            'botd_logic_loaded' => 1,
            'botd_wrapper_loaded' => 1,
        ));

        $this->assertEquals($expected, apbct__bot_detector_get_fd_log());
    }

    public function test_returnsBrowserStateFromNoCookie()
    {
        global $apbct;
        $apbct->data['cookies_type'] = 'none';
        NoCookie::set('apbct_browser_state', $this->getBrowserState());

        $expected = $this->getExpected('OK', '', array(
            'frontend_data_log' => '[["success"],[true]]',
            'botd_logic_loaded' => 1,
            'botd_wrapper_loaded' => 1,
        ));

        $this->assertEquals($expected, apbct__bot_detector_get_fd_log());
    }

    public function test_returnsBrowserStateFromPost()
    {
        $_POST['apbct_browser_state'] = $this->getBrowserState();

        $result = json_decode(apbct__bot_detector_get_fd_log(), true);

        $this->assertEquals('OK', $result['plugin_status']);
        $this->assertEquals('[["success"],[true]]', $result['apbct_browser_state']['frontend_data_log']);
        $this->assertEquals(1, $result['apbct_browser_state']['botd_logic_loaded']);
        $this->assertEquals(1, $result['apbct_browser_state']['botd_wrapper_loaded']);
    }

    public function test_returnsBrowserStateFromPostDataArray()
    {
        $_POST['data'] = array('apbct_browser_state' => $this->getBrowserState());

        $result = json_decode(apbct__bot_detector_get_fd_log(), true);

        $this->assertEquals('OK', $result['plugin_status']);
        $this->assertEquals('[["success"],[true]]', $result['apbct_browser_state']['frontend_data_log']);
        $this->assertEquals(1, $result['apbct_browser_state']['botd_logic_loaded']);
        $this->assertEquals(1, $result['apbct_browser_state']['botd_wrapper_loaded']);
    }

    public function test_returnsErrorWhenDisabled()
    {
        global $apbct;
        $apbct->data['bot_detector_enabled'] = '0';
        AltSessions::set('apbct_browser_state', $this->getBrowserState());

        $expected = $this->getExpected(
            'ERROR',
            'bot detector library usage is disabled',
            $this->getDefaultState()
        );

        $this->assertEquals($expected, apbct__bot_detector_get_fd_log());
    }

    public function test_returnsErrorWhenNoStateProvided()
    {
        $expected = $this->getExpected(
            'ERROR',
            'no browser state provided by the transport',
            $this->getDefaultState()
        );

        $this->assertEquals($expected, apbct__bot_detector_get_fd_log());
    }

    public function test_returnsErrorWhenStateNotJson()
    {
        AltSessions::set('apbct_browser_state', 'invalid json');

        $expected = $this->getExpected(
            'ERROR',
            'no browser state provided by the transport',
            $this->getDefaultState()
        );

        $this->assertEquals($expected, apbct__bot_detector_get_fd_log());
    }

    public function test_returnsEmptyLogWhenBotDetectorDidNotLoad()
    {
        AltSessions::set('apbct_browser_state', json_encode(array(
            'frontend_data_log' => '',
            'botd_logic_loaded' => 0,
            'botd_wrapper_loaded' => 1,
        )));

        $result = json_decode(apbct__bot_detector_get_fd_log(), true);

        $this->assertEquals('OK', $result['plugin_status']);
        $this->assertEquals('', $result['apbct_browser_state']['frontend_data_log']);
        $this->assertEquals(0, $result['apbct_browser_state']['botd_logic_loaded']);
        $this->assertEquals(1, $result['apbct_browser_state']['botd_wrapper_loaded']);
    }

    public function test_partialStateIsFilledWithDefaults()
    {
        AltSessions::set('apbct_browser_state', json_encode(array(
            'botd_wrapper_loaded' => 1,
        )));

        $expected = $this->getExpected('OK', '', array(
            'frontend_data_log' => '',
            'botd_logic_loaded' => 0,
            'botd_wrapper_loaded' => 1,
        ));

        $this->assertEquals($expected, apbct__bot_detector_get_fd_log());
    }
}
