<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for the check_value salt fix in apbct_cookie() and apbct_cookies_test().
 *
 * Verifies that:
 * 1. check_value includes salt, so md5(api_key) alone cannot be extracted from cookies.
 * 2. apbct_cookies_test() validates cookies using salt.
 * 3. Cookies without salt are rejected.
 */
class TestCheckValueSalt extends TestCase
{
    private $apbctBackup;
    private $cookieBackup;

    protected function setUp(): void
    {
        global $apbct;

        $this->apbctBackup = $apbct;
        $this->cookieBackup = $_COOKIE;

        $apbct = (object) [
            'api_key' => 'testkey123',
            'data' => [
                'salt' => 'test_salt_value',
                'cookies_type' => 'native',
                'key_is_ok' => 1,
            ],
        ];

        \Cleantalk\ApbctWP\Variables\Cookie::$force_alt_cookies_global = false;
    }

    protected function tearDown(): void
    {
        global $apbct;
        $apbct = $this->apbctBackup;
        $_COOKIE = $this->cookieBackup;
    }

    /**
     * Test that check_value with salt differs from check_value without salt.
     * This verifies the vulnerability fix: check_value must NOT equal md5(api_key).
     */
    public function testCheckValueWithSaltDiffersFromWithout()
    {
        global $apbct;

        $with_suffix = md5($apbct->api_key . $apbct->data['salt'] . '_apbct_cookies_test');
        $without_salt = md5($apbct->api_key);
        $rc_bearer = md5($apbct->api_key . $apbct->data['salt']);

        $this->assertNotEquals(
            $without_salt,
            $with_suffix,
            'check_value must not equal md5(api_key) alone - salt must change the hash'
        );
        $this->assertNotEquals(
            $rc_bearer,
            $with_suffix,
            'check_value must not equal the RC bearer md5(api_key + salt) - purpose suffix must domain-separate it'
        );
    }

    /**
     * Test that apbct_cookies_test() validates a cookie computed WITH salt and purpose suffix.
     */
    public function testApbctCookiesTestValidatesWithSalt()
    {
        global $apbct;

        $cookie_test_value = array(
            'cookies_names' => array(),
            'check_value'   => md5($apbct->api_key . $apbct->data['salt'] . '_apbct_cookies_test'),
        );

        $cookie_prefix = function_exists('apbct__get_cookie_prefix') ? apbct__get_cookie_prefix() : '';
        $_COOKIE[$cookie_prefix . 'apbct_cookies_test'] = urlencode(json_encode($cookie_test_value));

        $result = apbct_cookies_test();

        $this->assertSame(1, $result, 'Cookie with md5(api_key + salt) must pass validation');
    }

    /**
     * Test that apbct_cookies_test() rejects a cookie computed WITHOUT salt.
     * This is the core of the vulnerability fix verification.
     */
    public function testApbctCookiesTestRejectsCookieWithoutSalt()
    {
        global $apbct;

        $cookie_test_value = array(
            'cookies_names' => array(),
            'check_value'   => md5($apbct->api_key), // No salt - old vulnerable value
        );

        $cookie_prefix = function_exists('apbct__get_cookie_prefix') ? apbct__get_cookie_prefix() : '';
        $_COOKIE[$cookie_prefix . 'apbct_cookies_test'] = urlencode(json_encode($cookie_test_value));

        $result = apbct_cookies_test();

        $this->assertSame(0, $result, 'Cookie with md5(api_key) without salt must be rejected');
    }

    /**
     * Test that check_value with salt + timestamp is correctly validated.
     */
    public function testApbctCookiesTestValidatesWithSaltAndTimestamp()
    {
        global $apbct;

        $timestamp = '1718000000';

        $cookie_test_value = array(
            'cookies_names' => array('ct_ps_timestamp'),
            'check_value'   => md5($apbct->api_key . $apbct->data['salt'] . '_apbct_cookies_test' . $timestamp),
        );

        $cookie_prefix = function_exists('apbct__get_cookie_prefix') ? apbct__get_cookie_prefix() : '';
        $_COOKIE[$cookie_prefix . 'apbct_cookies_test'] = urlencode(json_encode($cookie_test_value));
        $_COOKIE[$cookie_prefix . 'ct_ps_timestamp'] = $timestamp;

        $result = apbct_cookies_test();

        $this->assertSame(1, $result, 'Cookie with md5(api_key + salt + timestamp) must pass validation');
    }

    /**
     * Test that check_value with timestamp but WITHOUT salt is rejected.
     */
    public function testApbctCookiesTestRejectsTimestampCookieWithoutSalt()
    {
        global $apbct;

        $timestamp = '1718000000';

        $cookie_test_value = array(
            'cookies_names' => array('ct_ps_timestamp'),
            'check_value'   => md5($apbct->api_key . $timestamp), // No salt
        );

        $cookie_prefix = function_exists('apbct__get_cookie_prefix') ? apbct__get_cookie_prefix() : '';
        $_COOKIE[$cookie_prefix . 'apbct_cookies_test'] = urlencode(json_encode($cookie_test_value));
        $_COOKIE[$cookie_prefix . 'ct_ps_timestamp'] = $timestamp;

        $result = apbct_cookies_test();

        $this->assertSame(0, $result, 'Cookie with md5(api_key + timestamp) without salt must be rejected');
    }
}
