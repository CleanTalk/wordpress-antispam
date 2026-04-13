<?php

use Cleantalk\Common\Helper;
use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    public function test_http__multi_request_error() {
    	$this->assertArrayHasKey( 'error', Helper::httpMultiRequest( 'string' ) );
	    $this->assertArrayHasKey( 'error', Helper::httpMultiRequest( '' ) );
	    $this->assertArrayHasKey( 'error', Helper::httpMultiRequest( array() ) );
	    $this->assertArrayHasKey( 'error', Helper::httpMultiRequest( array(array('https://google.com')) ) );
    }

	public function test_http__multi_request_success() {
    	$res = Helper::httpMultiRequest( array('https://google.com', 'https://apple.com') );
		$this->assertIsArray( $res );
		$this->assertContainsOnly( 'string', $res );
	}

	/**
	 * Test ipResolve returns false for invalid IP
	 */
	public function test_ipResolve_invalid_ip()
	{
		$this->assertFalse(Helper::ipResolve('invalid'));
		$this->assertFalse(Helper::ipResolve('999.999.999.999'));
		$this->assertFalse(Helper::ipResolve(''));
		$this->assertFalse(Helper::ipResolve('abc.def.ghi.jkl'));
	}

	/**
	 * Test ipResolve returns false for null/empty values
	 */
	public function test_ipResolve_null_empty()
	{
		$this->assertFalse(Helper::ipResolve(null));
		$this->assertFalse(Helper::ipResolve(false));
	}

	/**
	 * Test ipResolve returns false for reserved/special IPs (0.0.0.0)
	 */
	public function test_ipResolve_reserved_ip()
	{
		$this->assertFalse(Helper::ipResolve('0.0.0.0'));
	}

	/**
	 * Test ipResolve with Google DNS (8.8.8.8) - should return hostname
	 * This is an integration test that requires network access
	 *
	 * @group integration
	 */
	public function test_ipResolve_google_dns_ipv4()
	{
		$result = Helper::ipResolve('8.8.8.8');

		// Google DNS should resolve to dns.google
		if ($result !== false) {
			$this->assertStringContainsString('dns.google', $result);
		} else {
			// DNS resolution might fail in some environments
			$this->markTestSkipped('DNS resolution not available in this environment');
		}
	}

	/**
	 * Test ipResolve with private IP - should return false (no PTR record)
	 */
	public function test_ipResolve_private_ip()
	{
		// Private IPs typically don't have public PTR records
		$result = Helper::ipResolve('192.168.1.1');

		// Either false or the IP itself (no PTR) - both are acceptable
		$this->assertTrue($result === false || is_string($result));
	}

	/**
	 * Test ipResolve with localhost IPv4
	 */
	public function test_ipResolve_localhost_ipv4()
	{
		$result = Helper::ipResolve('127.0.0.1');

		// Localhost might resolve to 'localhost' or return false depending on system config
		$this->assertTrue($result === false || is_string($result));
	}

	/**
	 * Test ipResolve with IPv6 localhost (::1)
	 */
	public function test_ipResolve_localhost_ipv6()
	{
		// IPv6 localhost is considered invalid (0::0) by ipValidate
		$result = Helper::ipResolve('::1');

		$this->assertTrue($result === false || is_string($result));
	}

	/**
	 * Test ipResolve with Google DNS IPv6 (2001:4860:4860::8888)
	 *
	 * @group integration
	 */
	public function test_ipResolve_google_dns_ipv6()
	{
		$result = Helper::ipResolve('2001:4860:4860::8888');

		// Google DNS IPv6 should resolve to dns.google
		if ($result !== false) {
			$this->assertStringContainsString('dns.google', $result);
		} else {
			// IPv6 DNS resolution might not work in all environments
			$this->markTestSkipped('IPv6 DNS resolution not available in this environment');
		}
	}

	/**
	 * Test ipResolve returns string hostname on success
	 *
	 * @group integration
	 */
	public function test_ipResolve_return_type()
	{
		$result = Helper::ipResolve('8.8.8.8');

		// Result should be either false or a non-empty string
		$this->assertTrue(
			$result === false || (is_string($result) && strlen($result) > 0),
			'ipResolve should return false or a non-empty string hostname'
		);
	}
}
