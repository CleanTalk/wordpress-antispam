<?php

namespace Inc;

use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Post;
use PHPUnit\Framework\TestCase;

/**
 * ct_no_cookie_hidden_field sent as an array (hidden field injected into a form several times)
 * must not cause a fatal TypeError in apbct_form__get_no_cookie_data().
 */
class TestNoCookieDataArrayField extends TestCase
{
    /** @var array */
    private $post_backup;

    /** @var mixed */
    private $apbct_backup;

    protected function setUp(): void
    {
        parent::setUp();
        global $apbct;
        $this->apbct_backup = isset($apbct) ? $apbct : null;
        $this->post_backup = $_POST;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        Post::getInstance()->variables = array();
    }

    protected function tearDown(): void
    {
        global $apbct;
        $_POST = $this->post_backup;
        Post::getInstance()->variables = array();
        $apbct = $this->apbct_backup;
        parent::tearDown();
    }

    public function testClearQueryReturnsFalseForNonString()
    {
        $this->assertFalse(
            apbct_clear_query_from_service_fields(array('_ct_no_cookie_data_a', '_ct_no_cookie_data_b'), 'ct_no_cookie_hidden_field')
        );
        $this->assertFalse(apbct_clear_query_from_service_fields(null));
    }

    public function testClearQueryStillClearsStringQuery()
    {
        $this->assertEquals(
            'some_field=some_value',
            apbct_clear_query_from_service_fields('some_field=some_value&ct_no_cookie_hidden_field=xxx', 'ct_no_cookie_hidden_field')
        );
    }

    public function testCheckPostTakesFirstStringFromArrayField()
    {
        $_POST = array(
            'action' => 'tutor_course_filter_ajax',
            'ct_no_cookie_hidden_field' => array('_ct_no_cookie_data_first', '_ct_no_cookie_data_second'),
        );

        $result = apbct_check_post_for_no_cookie_data();

        $this->assertEquals('_ct_no_cookie_data_first', $result['data']);
        $this->assertEquals(array('ct_no_cookie_hidden_field'), $result['mapping']);
    }

    public function testGetNoCookieDataWithArrayFieldDoesNotFatal()
    {
        $_POST = array(
            'action' => 'tutor_course_filter_ajax',
            'ct_bot_detector_event_token' => array('token1', 'token2'),
            'ct_no_cookie_hidden_field' => array('_ct_no_cookie_data_a', '_ct_no_cookie_data_b'),
        );

        apbct_form__get_no_cookie_data();

        $this->assertArrayNotHasKey('ct_no_cookie_hidden_field', $_POST);
        $this->assertEquals('tutor_course_filter_ajax', $_POST['action']);
    }

    public function testGetNoCookieDataWithStringFieldUnchanged()
    {
        $_POST = array(
            'action' => 'some_action',
            'ct_no_cookie_hidden_field' => '_ct_no_cookie_data_a',
        );

        apbct_form__get_no_cookie_data();

        $this->assertArrayNotHasKey('ct_no_cookie_hidden_field', $_POST);
        $this->assertEquals('some_action', $_POST['action']);
    }
}
