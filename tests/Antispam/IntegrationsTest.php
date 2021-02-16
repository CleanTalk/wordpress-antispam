<?php

/*
 * This is a very bad test!
 * An example how have not to write tests.
 * Using more magic inexplicable reflection actions.
 * But the test works...
 */
use PHPUnit\Framework\TestCase;

class IntegrationsTest extends TestCase {

	private $integrations;

	private $method;

	public function setUp()
	{
		$available_integrations = array(
			'ContactBank'          => array( 'hook' => 'contact_bank_frontend_ajax_call', 'ajax' => true ),
			'FluentForm'           => array( 'hook' => 'fluentform_before_insert_submission', 'ajax' => false ),
			'ElfsightContactForm'  => array( 'hook' => 'elfsight_contact_form_mail', 'ajax' => true ),
			'SimpleMembership'     => array( 'hook' => 'swpm_front_end_registration_complete_user_data', 'ajax' => false ),
			'EstimationForm'       => array( 'hook' => 'send_email', 'ajax' => true ),
			'LandingPageBuilder'   => array( 'hook' => 'ulpb_formBuilderEmail_ajax', 'ajax' => true ),
			'WpMembers'            => array( 'hook' => 'wpmem_pre_register_data', 'ajax' => false ),
			'Rafflepress'          => array( 'hook' => 'rafflepress_lite_giveaway_api', 'ajax' => true ),
			'Wpdiscuz'             => array( 'hook' => array( 'wpdAddComment', 'wpdAddInlineComment' ), 'ajax' => true ),
		);
		$class = new \ReflectionClass( '\Cleantalk\Antispam\Integrations' );
		$this->method = $class->getMethod('get_current_integration_triggered');
		$this->method->setAccessible(true);
		$this->integrations = $class->newInstanceWithoutConstructor();
		$property = $class->getProperty('integrations');
		$property->setAccessible(true);
		$property->setValue( $this->integrations , $available_integrations );
	}

	public function testGet_current_integration_triggered_empty() {
		// empty parameter
		$this->assertFalse( $this->method->invoke( $this->integrations, '' ) );
	}

	public function testGet_current_integration_triggered_string() {
		// parameter is string
		$this->assertEquals('Rafflepress', $this->method->invoke( $this->integrations, 'rafflepress_lite_giveaway_api' ) );
	}

	public function testGet_current_integration_triggered_array() {
		// parameter is array
		$this->assertEquals( 'Wpdiscuz', $this->method->invoke( $this->integrations, 'wpdAddComment' ) );

	}

}
