<?php


class TestRCServiceTemplateGet extends PHPUnit\Framework\TestCase
{
    private $template_id;
    private $api_response_empty;
    private $api_response;
    private $api_response_custom;
    private $api_key;
    private $expected_result;

    protected function setUp()
    {
        $this->template_id = '1234';
        $this->api_response_empty = array();
        $this->api_response = array (
            0 =>
                array (
                    'template_id' => 1234,
                    'product_id' => 1,
                    'name' => 'wp59.loc',
                    'options_cloud' => '{"response_lang":"en","stop_list_enable":0,"move_to_spam_enable":1,"allow_links_enable":1,"send_log_to_email":1,"server_response":null,"server_response_combine":0,"logging_restriction":0}',
                    'options_site' => '{"sfw__enabled":"1","sfw__anti_flood":0,"sfw__anti_flood__view_limit":20,"sfw__anti_crawler":0,"sfw__use_delete_to_clear_table":"0","sfw__random_get":"-1","forms__registrations_test":"1","forms__comments_test":"1","forms__contact_forms_test":"1","forms__general_contact_forms_test":"1","forms__wc_checkout_test":"1","forms__wc_register_from_order":"1","forms__wc_add_to_cart":"0","forms__search_test":"1","forms__check_external":"0","forms__check_external__capture_buffer":0,"forms__check_internal":"0","comments__disable_comments__all":"0","comments__disable_comments__posts":"0","comments__disable_comments__pages":"0","comments__disable_comments__media":"0","comments__bp_private_messages":"1","comments__check_comments_number":"1","comments__remove_old_spam":"0","comments__remove_comments_links":"0","comments__show_check_links":"1","comments__manage_comments_on_public_page":"0","comments__hide_website_field":0,"data__protect_logged_in":"1","data__use_ajax":"1","data__use_static_js_key":"-1","data__general_postdata_test":"0","data__set_cookies":"0","data__ssl_on":"0","data__pixel":"3","data__email_check_before_post":"1","data__honeypot_field":"1","data__email_decoder":"1","exclusions__log_excluded_requests":"0","exclusions__urls":"","exclusions__urls__use_regexp":"1","exclusions__fields":"","exclusions__fields__use_regexp":0,"exclusions__roles":{"0":"Administrator"},"admin_bar__show":"1","admin_bar__all_time_counter":"0","admin_bar__daily_counter":"0","admin_bar__sfw_counter":"0","gdpr__enabled":0,"gdpr__text":"","misc__send_connection_reports":0,"misc__async_js":0,"misc__store_urls":"1","misc__complete_deactivation":0,"wp__use_builtin_http_api":"1","wp__comment_notify":"1","wp__comment_notify__roles":{},"wp__dashboard_widget__show":"1"}',
                    'created' => '2022-10-05 11:15:43',
                    'updated' => '2022-10-05 11:15:43',
                    'service_id_last_used' => NULL,
                    'user_id' => 351994,
                    'set_as_default' => 0,
                ),
        );
        $this->api_response_custom = $this->api_response;
        $this->api_key = getenv("CLEANTALK_TEST_API_KEY");
        $this->expected_result = array (
            'template_name' => 'wp59.loc',
            'options_site' =>
                array (
                    'sfw__enabled' => '1',
                    'sfw__anti_flood' => 0,
                    'sfw__anti_flood__view_limit' => 20,
                    'sfw__anti_crawler' => 0,
                    'sfw__use_delete_to_clear_table' => '0',
                    'sfw__random_get' => '-1',
                    'forms__registrations_test' => '1',
                    'forms__comments_test' => '1',
                    'forms__contact_forms_test' => '1',
                    'forms__general_contact_forms_test' => '1',
                    'forms__wc_checkout_test' => '1',
                    'forms__wc_register_from_order' => '1',
                    'forms__wc_add_to_cart' => '0',
                    'forms__search_test' => '1',
                    'forms__check_external' => '0',
                    'forms__check_external__capture_buffer' => 0,
                    'forms__check_internal' => '0',
                    'comments__disable_comments__all' => '0',
                    'comments__disable_comments__posts' => '0',
                    'comments__disable_comments__pages' => '0',
                    'comments__disable_comments__media' => '0',
                    'comments__bp_private_messages' => '1',
                    'comments__check_comments_number' => '1',
                    'comments__remove_old_spam' => '0',
                    'comments__remove_comments_links' => '0',
                    'comments__show_check_links' => '1',
                    'comments__manage_comments_on_public_page' => '0',
                    'comments__hide_website_field' => 0,
                    'data__protect_logged_in' => '1',
                    'data__use_ajax' => '1',
                    'data__use_static_js_key' => '-1',
                    'data__general_postdata_test' => '0',
                    'data__set_cookies' => '0',
                    'data__ssl_on' => '0',
                    'data__pixel' => '3',
                    'data__email_check_before_post' => '1',
                    'data__honeypot_field' => '1',
                    'data__email_decoder' => '1',
                    'exclusions__log_excluded_requests' => '0',
                    'exclusions__urls' => '',
                    'exclusions__urls__use_regexp' => '1',
                    'exclusions__fields' => '',
                    'exclusions__fields__use_regexp' => 0,
                    'exclusions__roles' =>
                        array (
                            0 => 'Administrator',
                        ),
                    'admin_bar__show' => '1',
                    'admin_bar__all_time_counter' => '0',
                    'admin_bar__daily_counter' => '0',
                    'admin_bar__sfw_counter' => '0',
                    'gdpr__enabled' => 0,
                    'gdpr__text' => '',
                    'misc__send_connection_reports' => 0,
                    'misc__async_js' => 0,
                    'misc__store_urls' => '1',
                    'misc__complete_deactivation' => 0,
                    'wp__use_builtin_http_api' => '1',
                    'wp__comment_notify' => '1',
                    'wp__comment_notify__roles' =>
                        array (
                        ),
                    'wp__dashboard_widget__show' => '1',
                ),
        );
    }

    public function testValidateGoodAPIResponse()
    {
        $got = apbct_validate_api_response__service_template_get($this->template_id,$this->api_response);
        $this->assertEquals($this->expected_result,$got);
    }

    public function testValidateBadAPIResponseEmptyTemplate()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('API error');
        apbct_validate_api_response__service_template_get($this->template_id,$this->api_response_empty);
    }

    public function testValidateBadAPIResponseNoSuchTemplateId()
    {
        $this->api_response_custom = $this->api_response;
        $this->api_response_custom[0]['template_id'] = 'asdsa';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no such template_id');
        apbct_validate_api_response__service_template_get($this->template_id,$this->api_response_custom);
    }

    public function testValidateBadAPIResponseOptionsIsEmpty()
    {
        $this->api_response_custom = $this->api_response;
        $this->api_response_custom[0]['options_site'] = '';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('options_site is empty');
        apbct_validate_api_response__service_template_get($this->template_id,$this->api_response_custom);
    }

    public function testValidateBadAPIResponseOptionsIsNotAString()
    {
        $this->api_response_custom = $this->api_response;
        $this->api_response_custom[0]['options_site'] = array('somethings');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a string');
        apbct_validate_api_response__service_template_get($this->template_id,$this->api_response_custom);
    }

    public function testValidateBadAPIResponseOptionsIsNotJSON()
    {
        $this->api_response_custom = $this->api_response;
        $this->api_response_custom[0]['options_site'] = 's{{[}::sd{]}}';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON decode error');
        apbct_validate_api_response__service_template_get($this->template_id,$this->api_response_custom);
    }

    public function testValidateBadAPIResponseHasAPIError()
    {
        $this->api_response_custom = $this->api_response;
        $this->api_response_custom = array("error"=>"some_error");
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('some_error');
        apbct_validate_api_response__service_template_get($this->template_id,$this->api_response_custom);
    }

    public function testNoSuchTemplateId()
    {
        $this->expectExceptionMessage('no such template_id found');
        apbct_rc__service_template_set('1122',
            apbct_validate_api_response__service_template_get('1122',$this->api_response),
            $this->api_key);
    }

    public function testGoodRequest()
    {
        $result = apbct_rc__service_template_set('1234',
            apbct_validate_api_response__service_template_get('1234',$this->api_response),
            $this->api_key);
        $this->assertEquals($result,'{"OK":"Settings updated"}');
    }

    public function testBadRequest()
    {
        $this->expectExceptionMessage('wrong services_templates_get response');
        apbct_rc__service_template_set('1234',
            apbct_validate_api_response__service_template_get('1234',$this->api_response_empty),
            $this->api_key);
    }
}