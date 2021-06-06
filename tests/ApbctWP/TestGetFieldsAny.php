<?php

//For Local testing
if( file_exists( '../inc/cleantalk-common.php' ) ) {
	require_once '../inc/cleantalk-common.php';
}

// For Travis CI
if( file_exists( 'inc/cleantalk-common.php' ) ) {
	require_once 'inc/cleantalk-common.php';
}

use Cleantalk\ApbctWP\GetFieldsAny;
use PHPUnit\Framework\TestCase;

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
			'ct_checkjs_cf7' => '150095430',
		);
		$this->gfa = new GetFieldsAny( $this->post );
	}

	public function testgetFields()
	{
		$result = $this->gfa->getFields();
		$this->assertIsArray( $result );
		$this->arrayHasKey( 'email', $result );
		$this->arrayHasKey( 'nickname', $result );
		$this->arrayHasKey( 'subject', $result );
		$this->arrayHasKey( 'contact', $result );
		$this->arrayHasKey( 'message', $result );
		$this->assertEquals($result['nickname'], $this->post['your-name']);
		$this->assertEquals($result['email'], $this->post['your-email']);
	}

	public function testgetFieldsAnyWithArguments()
	{
		$result = $this->gfa->getFields( 'another_email@example.com', 'SuperbNickname' );
		$this->assertIsArray( $result );
		$this->arrayHasKey( 'email', $result );
		$this->arrayHasKey( 'nickname', $result );
		$this->arrayHasKey( 'subject', $result );
		$this->arrayHasKey( 'contact', $result );
		$this->arrayHasKey( 'message', $result );
		$this->assertEquals($result['nickname'],'SuperbNickname');
		$this->assertEquals($result['email'], 'another_email@example.com');
	}

}
