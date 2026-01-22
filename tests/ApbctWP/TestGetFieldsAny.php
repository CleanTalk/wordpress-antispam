<?php

//For Local testing
if( file_exists( '../inc/cleantalk-common.php' ) ) {
	require_once '../inc/cleantalk-common.php';
}

// For Travis CI
if( file_exists( 'inc/cleantalk-common.php' ) ) {
	require_once 'inc/cleantalk-common.php';
}

use Cleantalk\ApbctWP\DTO\GetFieldsAnyDTO;
use Cleantalk\ApbctWP\GetFieldsAny;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Post;
use PHPUnit\Framework\TestCase;

/**
 * State class placeholder
 */
global $apbct;
$apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
$apbct->settings['data__set_cookies'] = 1;
$apbct->saveSettings();

class TestGetFieldsAny extends TestCase {

	/**
	 * Fake $_POST
	 * @var array
	 */
	private $post;

	/**
	 * @var GetFieldsAny
	 */
	private $gfa;

	public function setUp()
	{
		$this->post = array (
			'_wpcf7' => '100',
			'_wpcf7_version' => '5.4',
			'_wpcf7_locale' => 'en_US',
			'_wpcf7_unit_tag' => 'wpcf7-f100-p101-o1',
			'_wpcf7_container_post' => '101',
			'_wpcf7_posted_data_hash' => '',
			'your-name' => 'Your Name',
			'your-email' => 'good@cleantalk.org',
			'your-subject' => 'Subject',
			'your-message' => 'Your Message',
			//'ct_checkjs_cf7' => '150095430',
		);
		$this->gfa = new GetFieldsAny( $this->post );
	}

    public function testEmptyDTO()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No go!');
        new GetFieldsAnyDTO(array());
    }

	public function testgetFields()
	{
		$result = $this->gfa->getFields();
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'email', $result );
		$this->assertArrayHasKey( 'nickname', $result );
		$this->assertArrayHasKey( 'subject', $result );
		$this->assertArrayHasKey( 'contact', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertArrayHasKey( 'emails_array', $result );
		$this->assertEquals($result['nickname'], $this->post['your-name']);
		$this->assertEquals($result['email'], $this->post['your-email']);
	}

	public function testgetFieldsAnyWithArguments()
	{
		$result = $this->gfa->getFields( 'another_email@example.com', 'SuperbNickname' );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'email', $result );
		$this->assertArrayHasKey( 'nickname', $result );
		$this->assertArrayHasKey( 'subject', $result );
		$this->assertArrayHasKey( 'contact', $result );
		$this->assertArrayHasKey( 'message', $result );
        $this->assertArrayHasKey( 'emails_array', $result );
		$this->assertEquals($result['nickname'],'SuperbNickname');
		$this->assertEquals($result['email'], 'another_email@example.com');
	}

    public function testCTGFAStandaloneAsArrayResponse()
    {
        $result = ct_gfa($this->post);
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'email', $result );
        $this->assertArrayHasKey( 'nickname', $result );
        $this->assertArrayHasKey( 'subject', $result );
        $this->assertArrayHasKey( 'contact', $result );
        $this->assertArrayHasKey( 'message', $result );
        $this->assertArrayHasKey( 'emails_array', $result );
        $this->assertEquals($this->post['your-name'], $result['nickname']);
        $this->assertEquals($this->post['your-email'], $result['email']);
        $this->assertEquals($this->post['your-message'], $result['message']['your-message']);
        $this->assertEquals($this->post['your-subject'], $result['subject']);
        $this->assertTrue($result['contact']);
    }

    public function testCTGFAStandaloneAsDTOResponse()
    {
        $result = ct_gfa_dto($this->post,'', '');
        $this->assertIsObject( $result );
        $this->assertInstanceOf(GetFieldsAnyDTO::class, $result);
        $this->assertEquals($this->post['your-name'], $result->nickname);
        $this->assertEquals($this->post['your-email'], $result->email);
        $this->assertEquals($this->post['your-subject'], $result->subject);
        $this->assertEquals($this->post['your-message'], $result->message['your-message']);
    }

    public function testIsNotContactForm()
    {
        $this->post['ct_checkjs_cf7'] = '150095430';
        $result = ct_gfa_dto($this->post,'', '');
        $this->assertIsObject( $result );
        $this->assertInstanceOf(GetFieldsAnyDTO::class, $result);
        $this->assertFalse($result->contact);
    }

    public function testEmptyPreprocessedEmailsArray()
    {
        $result = ct_gfa_dto(array(),'', '');
        $this->assertIsObject( $result );
        $this->assertInstanceOf(GetFieldsAnyDTO::class, $result);
        $this->assertEmpty($result->emails_array);
    }

    public function testValidPreprocessedEmailsArray()
    {
        $emails_array = array(
            's@cleantalk.org',
            's2@cleantalk.org',
            's3@cleantalk.org',
        );
        $result = ct_gfa_dto(array(),'', '', $emails_array);
        $this->assertIsObject( $result );
        $this->assertInstanceOf(GetFieldsAnyDTO::class, $result);
        $this->assertNotEmpty($result->emails_array);
        $this->assertEquals($emails_array, $result->emails_array);
    }

    public function testNotArrayPreprocessedEmailsArray()
    {
        $emails_array = 'string';
        $result = ct_gfa_dto(array(),'', '', $emails_array);
        $this->assertIsObject( $result );
        $this->assertInstanceOf(GetFieldsAnyDTO::class, $result);
        $this->assertEmpty($result->emails_array);
    }

    public function testPartiallyInvalidPreprocessedEmailsArray()
    {
        $emails_array = array(
            's@cleantalk.org',
            's2@cleantalk.org',
            array(),
            new StdClass(),
            ''
        );
        $expected = array(
            's@cleantalk.org',
            's2@cleantalk.org',
            'invalid_preprocessed_email',
            'invalid_preprocessed_email',
            'invalid_preprocessed_email',
        );
        $result = ct_gfa_dto(array(),'', '', $emails_array);
        $this->assertIsObject( $result );
        $this->assertInstanceOf(GetFieldsAnyDTO::class, $result);
        $this->assertNotEmpty($result->emails_array);
        $this->assertEquals($expected, $result->emails_array);
    }

    public function testTotalInvalidPreprocessedEmailsArray()
    {
        $emails_array = array(
            array(),
            new StdClass(),
        );
        $expected = array(
            'invalid_preprocessed_email',
            'invalid_preprocessed_email',
        );
        $result = ct_gfa_dto(array(),'', '', $emails_array);
        $this->assertIsObject( $result );
        $this->assertInstanceOf(GetFieldsAnyDTO::class, $result);
        $this->assertNotEmpty($result->emails_array);
        $this->assertEquals($expected, $result->emails_array);
    }

    public function testGetVisibleFieldsData_empty()
    {
        $result = GetFieldsAny::getVisibleFieldsData();
        $this->assertEmpty( $result );
    }

    public function testGetVisibleFieldsData_customArray()
    {
        $custom_array = array (
            '__fluent_form_embded_post_id' => '111',
            '_fluentform_1_fluentformnonce' => 'e7c5739c9f',
            '_wp_http_referer' => '/ff-contact/',
            'names' =>
                array (
                    'first_name' => 'Alex',
                    'last_name' => 'Gull',
                ),
            'email' => 's@cleantalk.org',
            'subject' => 'asd',
            'message' => 'asd',
            'apbct_visible_fields' => 'eyIwIjp7InZpc2libGVfZmllbGRzIjoibmFtZXNbZmlyc3RfbmFtZV0gbmFtZXNbbGFzdF9uYW1lXSBlbWFpbCBzdWJqZWN0IG1lc3NhZ2UiLCJ2aXNpYmxlX2ZpZWxkc19jb3VudCI6NSwiaW52aXNpYmxlX2ZpZWxkcyI6Il9fZmx1ZW50X2Zvcm1fZW1iZGVkX3Bvc3RfaWQgX2ZsdWVudGZvcm1fMV9mbHVlbnRmb3Jtbm9uY2UgX3dwX2h0dHBfcmVmZXJlciBjdF9ib3RfZGV0ZWN0b3JfZXZlbnRfdG9rZW4iLCJpbnZpc2libGVfZmllbGRzX2NvdW50Ijo0fX0=',
        );
        $result = GetFieldsAny::getVisibleFieldsData($custom_array);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('visible_fields', $result);
        $this->assertEquals('names[first_name] names[last_name] email subject message', $result['visible_fields']);
    }

    public function testGetVisibleFieldsData_customArrayIgnoreCompare()
    {
        $custom_array = array (
            '__fluent_form_embded_post_id' => '111',
            '_fluentform_1_fluentformnonce' => 'e7c5739c9f',
            '_wp_http_referer' => '/ff-contact/',
            'names' =>
                array (
                    'first_name' => 'Alex',
                    'last_name' => 'Gull',
                ),
            'email' => 's@cleantalk.org',
            'subject' => 'asd',
            'message' => 'asd',
            'apbct_visible_fields' => 'eyIwIjp7InZpc2libGVfZmllbGRzIjoibmFtZXNbZmlyc3RfbmFtZV0gbmFtZXNbbGFzdF9uYW1lXSBlbWFpbCBzdWJqZWN0IG1lc3NhZ2UiLCJ2aXNpYmxlX2ZpZWxkc19jb3VudCI6NSwiaW52aXNpYmxlX2ZpZWxkcyI6Il9fZmx1ZW50X2Zvcm1fZW1iZGVkX3Bvc3RfaWQgX2ZsdWVudGZvcm1fMV9mbHVlbnRmb3Jtbm9uY2UgX3dwX2h0dHBfcmVmZXJlciBjdF9ib3RfZGV0ZWN0b3JfZXZlbnRfdG9rZW4iLCJpbnZpc2libGVfZmllbGRzX2NvdW50Ijo0fX0=',
        );
        $result = GetFieldsAny::getVisibleFieldsData($custom_array, true);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('visible_fields', $result);
        $this->assertEquals('names[first_name] names[last_name] email subject message', $result['visible_fields']);
    }

    public function testGetVisibleFieldsData_customArrayIgnoreCompare_doWrong()
    {
        $custom_array = array (
            'apbct_visible_fields' => 'eyIwIjp7InZpc2libGVfZmllbGRzIjoibmFtZXNbZmlyc3RfbmFtZV0gbmFtZXNbbGFzdF9uYW1lXSBlbWFpbCBzdWJqZWN0IG1lc3NhZ2UiLCJ2aXNpYmxlX2ZpZWxkc19jb3VudCI6NSwiaW52aXNpYmxlX2ZpZWxkcyI6Il9fZmx1ZW50X2Zvcm1fZW1iZGVkX3Bvc3RfaWQgX2ZsdWVudGZvcm1fMV9mbHVlbnRmb3Jtbm9uY2UgX3dwX2h0dHBfcmVmZXJlciBjdF9ib3RfZGV0ZWN0b3JfZXZlbnRfdG9rZW4iLCJpbnZpc2libGVfZmllbGRzX2NvdW50Ijo0fX0=',
        );
        $result = GetFieldsAny::getVisibleFieldsData($custom_array, true);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('visible_fields', $result);
        $this->assertEquals('names[first_name] names[last_name] email subject message', $result['visible_fields']);

        $result = GetFieldsAny::getVisibleFieldsData($custom_array, false);
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('visible_fields', $result);

    }

    public function testGetVisibleFieldsData_fromPost_positive()
    {
        Post::getInstance()->variables = [];

        $_POST = array (
        '__fluent_form_embded_post_id' => '111',
        '_fluentform_1_fluentformnonce' => 'e7c5739c9f',
        '_wp_http_referer' => '/ff-contact/',
        'names' =>
            array (
                'first_name' => 'Alex',
                'last_name' => 'Gull',
            ),
        'email' => 's@cleantalk.org',
        'subject' => 'asd',
        'message' => 'asd',
            'apbct_visible_fields' => 'eyIwIjp7InZpc2libGVfZmllbGRzIjoibmFtZXNbZmlyc3RfbmFtZV0gbmFtZXNbbGFzdF9uYW1lXSBlbWFpbCBzdWJqZWN0IG1lc3NhZ2UiLCJ2aXNpYmxlX2ZpZWxkc19jb3VudCI6NSwiaW52aXNpYmxlX2ZpZWxkcyI6Il9fZmx1ZW50X2Zvcm1fZW1iZGVkX3Bvc3RfaWQgX2ZsdWVudGZvcm1fMV9mbHVlbnRmb3Jtbm9uY2UgX3dwX2h0dHBfcmVmZXJlciBjdF9ib3RfZGV0ZWN0b3JfZXZlbnRfdG9rZW4iLCJpbnZpc2libGVfZmllbGRzX2NvdW50Ijo0fX0=',
        );
        $result = GetFieldsAny::getVisibleFieldsData();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('visible_fields', $result);
        $this->assertEquals('names[first_name] names[last_name] email subject message', $result['visible_fields']);
    }

    public function testGetVisibleFieldsData_fromPost_negative()
    {
        Post::getInstance()->variables = [];
        $_POST = array (
            '__fluent_form_embded_post_id' => '111',
            '_fluentform_1_fluentformnonce' => 'e7c5739c9f',
            '_wp_http_referer' => '/ff-contact/',
            'names' =>
                array (
                    'first_name' => 'Alex',
                    'last_name' => 'Gull',
                ),
            'email' => 's@cleantalk.org',
            'subject' => 'asd',
            'message' => 'asd',
            //'apbct_visible_fields' => 'eyIwIjp7InZpc2libGVfZmllbGRzIjoibmFtZXNbZmlyc3RfbmFtZV0gbmFtZXNbbGFzdF9uYW1lXSBlbWFpbCBzdWJqZWN0IG1lc3NhZ2UiLCJ2aXNpYmxlX2ZpZWxkc19jb3VudCI6NSwiaW52aXNpYmxlX2ZpZWxkcyI6Il9fZmx1ZW50X2Zvcm1fZW1iZGVkX3Bvc3RfaWQgX2ZsdWVudGZvcm1fMV9mbHVlbnRmb3Jtbm9uY2UgX3dwX2h0dHBfcmVmZXJlciBjdF9ib3RfZGV0ZWN0b3JfZXZlbnRfdG9rZW4iLCJpbnZpc2libGVfZmllbGRzX2NvdW50Ijo0fX0=',
        );
        $result = GetFieldsAny::getVisibleFieldsData();
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('visible_fields', $result);

        Post::getInstance()->variables = [];
        $_POST = array (
            '__fluent_form_embded_post_id' => '111',
            '_fluentform_1_fluentformnonce' => 'e7c5739c9f',
            '_wp_http_referer' => '/ff-contact/',
            'names' =>
                array (
                    'first_name' => 'Alex',
                    'last_name' => 'Gull',
                ),
            'email' => 's@cleantalk.org',
            'subject' => 'asd',
            'message' => 'asd',
            'apbct_visible_fields' => '',
        );
        $result = GetFieldsAny::getVisibleFieldsData();
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('visible_fields', $result);

        Post::getInstance()->variables = [];
        $_POST = array (
            '__fluent_form_embded_post_id' => '111',
            '_fluentform_1_fluentformnonce' => 'e7c5739c9f',
            '_wp_http_referer' => '/ff-contact/',
//            'names' =>
//                array (
//                    'first_name' => 'Alex',
//                    'last_name' => 'Gull',
//                ),
//            'email' => 's@cleantalk.org',
//            'subject' => 'asd',
//            'message' => 'asd',
            'apbct_visible_fields' => 'eyIwIjp7InZpc2libGVfZmllbGRzIjoibmFtZXNbZmlyc3RfbmFtZV0gbmFtZXNbbGFzdF9uYW1lXSBlbWFpbCBzdWJqZWN0IG1lc3NhZ2UiLCJ2aXNpYmxlX2ZpZWxkc19jb3VudCI6NSwiaW52aXNpYmxlX2ZpZWxkcyI6Il9fZmx1ZW50X2Zvcm1fZW1iZGVkX3Bvc3RfaWQgX2ZsdWVudGZvcm1fMV9mbHVlbnRmb3Jtbm9uY2UgX3dwX2h0dHBfcmVmZXJlciBjdF9ib3RfZGV0ZWN0b3JfZXZlbnRfdG9rZW4iLCJpbnZpc2libGVfZmllbGRzX2NvdW50Ijo0fX0',
        );
        $result = GetFieldsAny::getVisibleFieldsData();
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('visible_fields', $result);
    }
}
