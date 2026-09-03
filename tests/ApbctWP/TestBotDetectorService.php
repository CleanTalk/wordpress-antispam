<?php

use Cleantalk\ApbctWP\BotDetectorService;
use Cleantalk\ApbctWP\Variables\AltSessions;
use Cleantalk\ApbctWP\Variables\NoCookie;
use Cleantalk\ApbctWP\Variables\Post;

/**
 * Tests for BotDetectorService: pure getBrowserState() logic, exclusion helpers
 * and the full getFrontendDataLog() pipeline over real transports
 * (AltSessions/NoCookie/POST).
 */
class TestBotDetectorService extends ApbctTestCase
{
    protected function setUp(): void
    {
        global $apbct;
        $apbct->data['cookies_type'] = 'alternative';
        $apbct->data['bot_detector_enabled'] = '1';
        $apbct->settings['exclusions__bot_detector'] = 0;
        $apbct->settings['exclusions__bot_detector__form_attributes'] = '';
        $apbct->settings['exclusions__bot_detector__form_children_attributes'] = '';
        $apbct->settings['exclusions__bot_detector__form_parent_attributes'] = '';
        $this->resetTransports();
    }

    protected function tearDown(): void
    {
        $this->resetTransports();
    }

    /**
     * Every transport keeps its own state between calls:
     * Post caches each variable in the singleton, NoCookie holds a static array,
     * AltSessions store the values in the DB. Without the full reset the tests
     * would depend on the execution order.
     */
    private function resetTransports()
    {
        $_POST = array();
        Post::getInstance()->variables = array();
        NoCookie::$no_cookies_data = array();
        AltSessions::wipe();
    }

    private function makeRawBrowserState($log = '[["success"],[true]]')
    {
        return json_encode(array(
            'frontend_data_log' => $log,
            'botd_logic_loaded' => 1,
            'botd_wrapper_loaded' => 1,
        ));
    }

    private function assertSuccessfulLogResult($json)
    {
        $result = json_decode($json, true);
        $this->assertSame('OK', $result['plugin_status']);
        $this->assertSame('', $result['error_msg']);
        $this->assertSame(array(array('success'), array(true)), $result['apbct_browser_state']['frontend_data_log']);
        $this->assertSame(1, $result['apbct_browser_state']['botd_logic_loaded']);
        $this->assertSame(1, $result['apbct_browser_state']['botd_wrapper_loaded']);
    }

    // ------------------------------------------------------------------
    // getWrapperUrl()
    // ------------------------------------------------------------------

    public function test_getWrapperUrl_returnsNonEmptyString()
    {
        $url = BotDetectorService::getWrapperUrl();
        $this->assertIsString($url);
        $this->assertNotEmpty($url);
    }

    // ------------------------------------------------------------------
    // getBrowserState() — pure function, no globals involved
    // ------------------------------------------------------------------

    public function test_getBrowserState_returnsEmptyWhenNoSources()
    {
        $this->assertSame(array(), BotDetectorService::getBrowserState(array()));
    }

    public function test_getBrowserState_returnsEmptyWhenAllSourcesEmpty()
    {
        $this->assertSame(array(), BotDetectorService::getBrowserState(array(
            'request_parameters' => '',
            'post_browser_state' => null,
            'post_data' => array(),
        )));
    }

    public function test_getBrowserState_parsesJsonFromRequestParameters()
    {
        $raw = json_encode(array(
            'frontend_data_log' => '[["success"],[true]]',
            'botd_logic_loaded' => 1,
            'botd_wrapper_loaded' => 1,
        ));

        $state = BotDetectorService::getBrowserState(array(
            'request_parameters' => $raw,
        ));

        $this->assertSame(array(array('success'), array(true)), $state['frontend_data_log']);
        $this->assertSame(1, $state['botd_logic_loaded']);
        $this->assertSame(1, $state['botd_wrapper_loaded']);
    }

    public function test_getBrowserState_requestParametersWinsOverPost()
    {
        $winner = json_encode(array('botd_wrapper_loaded' => 1));
        $loser  = json_encode(array('botd_wrapper_loaded' => 0));

        $state = BotDetectorService::getBrowserState(array(
            'request_parameters' => $winner,
            'post_browser_state' => $loser,
            'post_data' => array('apbct_browser_state' => $loser),
        ));

        $this->assertSame(1, $state['botd_wrapper_loaded']);
    }

    public function test_getBrowserState_fallsBackToPostBrowserState()
    {
        $raw = json_encode(array('botd_logic_loaded' => 1));

        $state = BotDetectorService::getBrowserState(array(
            'request_parameters' => '',
            'post_browser_state' => $raw,
        ));

        $this->assertSame(1, $state['botd_logic_loaded']);
    }

    public function test_getBrowserState_fallsBackToPostDataArray()
    {
        $raw = json_encode(array('botd_wrapper_loaded' => 1));

        $state = BotDetectorService::getBrowserState(array(
            'post_data' => array('apbct_browser_state' => $raw),
        ));

        $this->assertSame(1, $state['botd_wrapper_loaded']);
    }

    public function test_getBrowserState_acceptsArraySource()
    {
        $state = BotDetectorService::getBrowserState(array(
            'request_parameters' => array(
                'frontend_data_log' => array(array('success')),
                'botd_logic_loaded' => 1,
                'botd_wrapper_loaded' => 1,
            ),
        ));

        $this->assertSame(array(array('success')), $state['frontend_data_log']);
        $this->assertSame(1, $state['botd_logic_loaded']);
        $this->assertSame(1, $state['botd_wrapper_loaded']);
    }

    public function test_getBrowserState_handlesEscapedJson()
    {
        $raw = addslashes(json_encode(array(
            'frontend_data_log' => '[["ok"]]',
            'botd_logic_loaded' => 1,
            'botd_wrapper_loaded' => 1,
        )));

        $state = BotDetectorService::getBrowserState(array(
            'request_parameters' => $raw,
        ));

        $this->assertSame(array(array('ok')), $state['frontend_data_log']);
        $this->assertSame(1, $state['botd_logic_loaded']);
    }

    public function test_getBrowserState_returnsEmptyOnInvalidJson()
    {
        $this->assertSame(array(), BotDetectorService::getBrowserState(array(
            'request_parameters' => 'not a json',
        )));
    }

    public function test_getBrowserState_partialStateIsFilledWithDefaults()
    {
        $state = BotDetectorService::getBrowserState(array(
            'request_parameters' => json_encode(array('botd_wrapper_loaded' => 1)),
        ));

        $this->assertSame(array(), $state['frontend_data_log']);
        $this->assertSame(0, $state['botd_logic_loaded']);
        $this->assertSame(1, $state['botd_wrapper_loaded']);
    }

    public function test_getBrowserState_frontendDataLogAsInvalidStringBecomesEmptyArray()
    {
        $state = BotDetectorService::getBrowserState(array(
            'request_parameters' => json_encode(array(
                'frontend_data_log' => 'not-a-json',
                'botd_logic_loaded' => 1,
                'botd_wrapper_loaded' => 1,
            )),
        ));

        $this->assertSame(array(), $state['frontend_data_log']);
    }

    public function test_getBrowserState_ignoresPostDataWithoutBrowserStateKey()
    {
        $state = BotDetectorService::getBrowserState(array(
            'post_data' => array('something_else' => 'value'),
        ));

        $this->assertSame(array(), $state);
    }

    // ------------------------------------------------------------------
    // getCustomExclusionsFromStateSettings()
    // ------------------------------------------------------------------

    public function test_getCustomExclusions_returnsEmptyWhenFeatureDisabled()
    {
        global $apbct;
        $apbct->settings['exclusions__bot_detector'] = 0;
        $apbct->settings['exclusions__bot_detector__form_attributes'] = 'foo';

        $this->assertSame(array(), BotDetectorService::getCustomExclusionsFromStateSettings());
    }

    public function test_getCustomExclusions_returnsEmptyWhenAllSignsAreEmpty()
    {
        global $apbct;
        $apbct->settings['exclusions__bot_detector'] = 1;

        $this->assertSame(array(), BotDetectorService::getCustomExclusionsFromStateSettings());
    }

    public function test_getCustomExclusions_buildsExclusionsFromFormAttributes()
    {
        global $apbct;
        $apbct->settings['exclusions__bot_detector'] = 1;
        $apbct->settings['exclusions__bot_detector__form_attributes'] = 'foo,bar';

        $exclusions = BotDetectorService::getCustomExclusionsFromStateSettings();

        $this->assertCount(2, $exclusions);
        $this->assertSame('exclusion_0', $exclusions[0]['exclusion_id']);
        $this->assertSame('foo', $exclusions[0]['signs_to_check']['form_attributes']);
        $this->assertSame('', $exclusions[0]['signs_to_check']['form_children_attributes']);
        $this->assertSame('', $exclusions[0]['signs_to_check']['form_parent_attributes']);
        $this->assertSame('exclusion_1', $exclusions[1]['exclusion_id']);
        $this->assertSame('bar', $exclusions[1]['signs_to_check']['form_attributes']);
    }

    public function test_getCustomExclusions_buildsAcrossAllSignTypes()
    {
        global $apbct;
        $apbct->settings['exclusions__bot_detector'] = 1;
        $apbct->settings['exclusions__bot_detector__form_attributes'] = 'a';
        $apbct->settings['exclusions__bot_detector__form_children_attributes'] = 'b';
        $apbct->settings['exclusions__bot_detector__form_parent_attributes'] = 'c';

        $exclusions = BotDetectorService::getCustomExclusionsFromStateSettings();

        $this->assertCount(3, $exclusions);
        $this->assertSame('a', $exclusions[0]['signs_to_check']['form_attributes']);
        $this->assertSame('b', $exclusions[1]['signs_to_check']['form_children_attributes']);
        $this->assertSame('c', $exclusions[2]['signs_to_check']['form_parent_attributes']);
    }

    // ------------------------------------------------------------------
    // getPreparedExclusions()
    // ------------------------------------------------------------------

    public function test_getPreparedExclusions_returnsEmptyJsonArrayWhenNothingConfigured()
    {
        $this->assertSame('[]', BotDetectorService::getPreparedExclusions());
    }

    public function test_getPreparedExclusions_returnsValidExclusionsAsJson()
    {
        global $apbct;
        $apbct->settings['exclusions__bot_detector'] = 1;
        $apbct->settings['exclusions__bot_detector__form_attributes'] = 'foo';

        $json = BotDetectorService::getPreparedExclusions();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertSame('exclusion_0', $decoded[0]['exclusion_id']);
        $this->assertSame('foo', $decoded[0]['signs_to_check']['form_attributes']);
    }

    // ------------------------------------------------------------------
    // getFrontendDataLog() — integration over real transports
    // ------------------------------------------------------------------

    public function test_getFrontendDataLog_readsFromAltSessions()
    {
        AltSessions::set('apbct_browser_state', $this->makeRawBrowserState());

        $this->assertSuccessfulLogResult(BotDetectorService::getFrontendDataLog());
    }

    public function test_getFrontendDataLog_readsFromNoCookie()
    {
        global $apbct;
        $apbct->data['cookies_type'] = 'none';
        NoCookie::set('apbct_browser_state', $this->makeRawBrowserState());

        $this->assertSuccessfulLogResult(BotDetectorService::getFrontendDataLog());
    }

    public function test_getFrontendDataLog_readsFromPost()
    {
        $_POST['apbct_browser_state'] = $this->makeRawBrowserState();

        $this->assertSuccessfulLogResult(BotDetectorService::getFrontendDataLog());
    }

    public function test_getFrontendDataLog_readsFromPostDataArray()
    {
        $_POST['data'] = array('apbct_browser_state' => $this->makeRawBrowserState());

        $this->assertSuccessfulLogResult(BotDetectorService::getFrontendDataLog());
    }

    public function test_getFrontendDataLog_returnsErrorWhenDisabled()
    {
        global $apbct;
        $apbct->data['bot_detector_enabled'] = '0';
        AltSessions::set('apbct_browser_state', $this->makeRawBrowserState());

        $result = json_decode(BotDetectorService::getFrontendDataLog(), true);

        $this->assertSame('ERROR', $result['plugin_status']);
        $this->assertSame('bot detector library usage is disabled', $result['error_msg']);
    }

    public function test_getFrontendDataLog_returnsErrorWhenNoStateProvided()
    {
        $result = json_decode(BotDetectorService::getFrontendDataLog(), true);

        $this->assertSame('ERROR', $result['plugin_status']);
        $this->assertSame('no browser state provided by the transport', $result['error_msg']);
    }
}
