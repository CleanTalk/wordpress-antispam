<?php

namespace Inc;

use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Server;
use PHPUnit\Framework\TestCase;

/**
 * Background whitelisting of the admin IP in SpamFireWall (task 60535):
 * apbct_admin_ip_whitelist__is_needed(), apbct_admin_ip_whitelist__add(), apbct_admin_ip_whitelist__ajax().
 */
class TestAdminIpWhitelist extends TestCase
{
    const IP = '203.0.113.10';
    const API_KEY = 'testapikey12345';

    /** @var mixed Global $apbct before the test, restored as is */
    private $apbct_backup;

    /** @var array */
    private $server_backup;

    /** @var array */
    private $cookie_backup;

    /** @var array */
    private $request_backup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server_backup  = $_SERVER;
        $this->cookie_backup  = $_COOKIE;
        $this->request_backup = $_REQUEST;

        global $apbct;
        $this->apbct_backup = isset($apbct) ? $apbct : null;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $apbct->settings['sfw__enabled'] = 1;
        $apbct->settings['apikey']       = self::API_KEY;
        $apbct->api_key                  = self::API_KEY;
        $apbct->key_is_ok                = 1;
        $apbct->data['key_is_ok']        = 1;
        $apbct->data['service_id']       = 1234;
        $apbct->data['cookies_type']     = 'native';

        $_SERVER['REMOTE_ADDR'] = self::IP;
        unset($_COOKIE['ct_sfw_ip_wl']);
        $this->resetVariablesCache();

        delete_transient(apbct_admin_ip_whitelist__retry_key(self::IP));
        wp_set_current_user(1); // administrator created by the WP test install
    }

    protected function tearDown(): void
    {
        delete_transient(apbct_admin_ip_whitelist__retry_key(self::IP));
        wp_set_current_user(0);

        $_SERVER  = $this->server_backup;
        $_COOKIE  = $this->cookie_backup;
        $_REQUEST = $this->request_backup;
        $this->resetVariablesCache();

        global $apbct;
        $apbct = $this->apbct_backup;

        parent::tearDown();
    }

    private function resetVariablesCache()
    {
        Server::getInstance()->variables = array();
        Cookie::getInstance()->variables = array();
    }

    private function setWhitelistCookie($ip)
    {
        $_COOKIE['ct_sfw_ip_wl'] = md5($ip . self::API_KEY);
        Cookie::getInstance()->variables = array();
    }

    public function testNeededForAdminWithoutCookie()
    {
        $this->assertTrue(apbct_admin_ip_whitelist__is_needed());
    }

    public function testNotNeededWhenSfwIsDisabled()
    {
        global $apbct;
        $apbct->settings['sfw__enabled'] = 0;

        $this->assertFalse(apbct_admin_ip_whitelist__is_needed());
    }

    public function testNotNeededWhenKeyIsNotOk()
    {
        global $apbct;
        $apbct->key_is_ok = 0;

        $this->assertFalse(apbct_admin_ip_whitelist__is_needed());
    }

    public function testNotNeededWithoutApiKey()
    {
        global $apbct;
        $apbct->api_key = '';

        $this->assertFalse(apbct_admin_ip_whitelist__is_needed());
    }

    public function testNotNeededWithoutServiceId()
    {
        global $apbct;
        $apbct->data['service_id'] = 0;

        $this->assertFalse(apbct_admin_ip_whitelist__is_needed());
    }

    public function testNotNeededForNotAdmin()
    {
        wp_set_current_user(0);

        $this->assertFalse(apbct_admin_ip_whitelist__is_needed());
    }

    public function testNotNeededWhenCookieMatchesCurrentIp()
    {
        $this->setWhitelistCookie(self::IP);

        $this->assertFalse(apbct_admin_ip_whitelist__is_needed());
    }

    public function testNeededWhenCookieIsForAnotherIp()
    {
        $this->setWhitelistCookie('198.51.100.1');

        $this->assertTrue(apbct_admin_ip_whitelist__is_needed());
    }

    public function testNotNeededDuringRetryPause()
    {
        set_transient(apbct_admin_ip_whitelist__retry_key(self::IP), 1, HOUR_IN_SECONDS);

        $this->assertFalse(apbct_admin_ip_whitelist__is_needed());
    }

    public function testRetryKeyDependsOnIp()
    {
        $this->assertNotSame(
            apbct_admin_ip_whitelist__retry_key(self::IP),
            apbct_admin_ip_whitelist__retry_key('198.51.100.1')
        );
    }

    public function testAddSuccessPassesIpToCloudAndDoesNotPauseRetries()
    {
        $passed_ip = null;
        $cloud_add = function ($ip) use (&$passed_ip) {
            $passed_ip = $ip;
            return true;
        };

        $this->assertTrue(apbct_admin_ip_whitelist__add(self::IP, $cloud_add));
        $this->assertSame(self::IP, $passed_ip);
        $this->assertFalse(get_transient(apbct_admin_ip_whitelist__retry_key(self::IP)));
    }

    public function testAddFailurePausesRetriesForAnHour()
    {
        $cloud_add = function ($_ip) {
            return false;
        };

        $this->assertFalse(apbct_admin_ip_whitelist__add(self::IP, $cloud_add));
        $this->assertNotEmpty(get_transient(apbct_admin_ip_whitelist__retry_key(self::IP)));
        $this->assertFalse(apbct_admin_ip_whitelist__is_needed());
    }

    public function testAjaxSkipsWhenWhitelistingIsNotNeeded()
    {
        global $apbct;
        $this->setWhitelistCookie(self::IP);
        $_REQUEST['_ajax_nonce'] = $apbct->ajax_service->getAdminNonce();

        $output = $this->runAjaxHandler();

        $response = json_decode($output, true);
        $this->assertIsArray($response, 'Response is not JSON: ' . $output);
        $this->assertTrue($response['success']);
        $this->assertTrue($response['data']['skipped']);
    }

    public function testAjaxRejectsRequestWithoutNonce()
    {
        unset($_REQUEST['_ajax_nonce']);

        $output = $this->runAjaxHandler();

        $this->assertStringNotContainsString('"success":true', $output);
    }

    /**
     * Runs the AJAX handler and returns what it printed before wp_die().
     *
     * @return string
     */
    private function runAjaxHandler()
    {
        add_filter('wp_doing_ajax', '__return_true');
        $die_handler = function () {
            return function ($message) {
                throw new \WPDieException(is_scalar($message) ? (string) $message : '');
            };
        };
        add_filter('wp_die_ajax_handler', $die_handler, 1);

        ob_start();
        try {
            apbct_admin_ip_whitelist__ajax();
        } catch (\WPDieException $e) {
            // wp_send_json_*() and check_ajax_referer() end with wp_die()
        }
        $output = (string) ob_get_clean();

        remove_filter('wp_die_ajax_handler', $die_handler, 1);
        remove_filter('wp_doing_ajax', '__return_true');

        return $output;
    }
}
