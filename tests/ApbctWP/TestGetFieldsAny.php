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
}
