<?php

namespace ApbctWP\Firewall;

use Cleantalk\ApbctWP\Firewall\FirewallBypass;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Request;
use ReflectionClass;

/**
 * Tests for the emergency firewall bypass.
 *
 * @see FirewallBypass
 */
class FirewallBypassTest extends \ApbctTestCase
{
    /**
     * @var string Transient key holding the emailed one-time token.
     */
    private $ready_token_key;

    /**
     * @var string Transient key holding the hash of the granted secret.
     */
    private $user_token_key;

    /**
     * @var string Request parameter carrying the one-time token.
     */
    private $request_param;

    /**
     * @var string Name of the cookie holding the granted secret.
     */
    private $cookie_name;

    /**
     * @var bool Original value of the remote call flag.
     */
    private $original_rc_running;

    /**
     * @var string Original value of the plugin's cookie mode setting.
     */
    private $original_cookies_type;

    /**
     * @var \wpdb
     */
    private $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        global $apbct, $wpdb;

        $this->wpdb = $wpdb;

        $reflection            = new ReflectionClass(FirewallBypass::class);
        $constants             = $reflection->getConstants();
        $this->ready_token_key = $constants['GENERATION_TOKEN_TRANSIENT_KEY'];
        $this->user_token_key  = $constants['USER_TOKEN_TRANSIENT_KEY'];
        $this->request_param   = $constants['GENERATION_REQUEST_PARAM'];
        $this->cookie_name     = $constants['USER_COOKIE_NAME'];

        $this->original_rc_running = $apbct->rc_running;
        $apbct->rc_running          = false;

        $this->original_cookies_type = $apbct->data['cookies_type'];

        $this->resetEnvironment();
        reset_phpmailer_instance();
    }

    protected function tearDown(): void
    {
        global $apbct;

        $apbct->rc_running           = $this->original_rc_running;
        $apbct->data['cookies_type'] = $this->original_cookies_type;

        $this->resetEnvironment();
        reset_phpmailer_instance();
        FirewallBypass::$last_error = null;

        parent::tearDown();
    }

    /**
     * Brings the request and the storage to the state of a clean anonymous visit.
     *
     * @return void
     */
    private function resetEnvironment()
    {
        delete_transient($this->ready_token_key);
        delete_transient($this->user_token_key);

        unset($_GET[$this->request_param], $_REQUEST[$this->request_param], $_COOKIE[$this->cookie_name]);

        $this->resetRequestCache();
    }

    /**
     * The variable handlers cache the values, drop the cache between the request emulations.
     *
     * @return void
     */
    private function resetRequestCache()
    {
        Request::getInstance()->variables = array();
        Get::getInstance()->variables     = array();
        Cookie::getInstance()->variables  = array();
    }

    /**
     * Emulates the cookie sent by the browser. Null removes the cookie.
     *
     * @param string|array|null $value
     *
     * @return void
     */
    private function emulateCookie($value = null)
    {
        if ( $value === null ) {
            unset($_COOKIE[$this->cookie_name]);
        } else {
            $_COOKIE[$this->cookie_name] = $value;
        }

        $this->resetRequestCache();
    }

    /**
     * Emulates a request carrying the bypass parameter.
     *
     * @param string $token
     *
     * @return void
     */
    private function emulateRequestWithToken($token)
    {
        $this->resetRequestCache();

        $_GET[$this->request_param] = $token;
    }

    /**
     * Runs the generation remote call in the verified remote call context.
     *
     * @return bool
     */
    private function runGenerationRemoteCall()
    {
        global $apbct;

        $apbct->rc_running = true;
        $result           = FirewallBypass::processGenerationRemoteCall();
        $apbct->rc_running = false;

        return $result;
    }

    /**
     * Extracts the one-time token from the last sent email.
     *
     * @return string
     */
    private function getTokenFromSentEmail()
    {
        $mailer = tests_retrieve_phpmailer_instance();
        $sent   = $mailer->get_sent();

        $this->assertNotFalse($sent, 'The notification email has not been sent.');
        $this->assertRegExp('/' . preg_quote($this->request_param, '/') . '=[a-f0-9]{64}/', $sent->body);

        preg_match('/' . preg_quote($this->request_param, '/') . '=([a-f0-9]{64})/', $sent->body, $matches);

        return $matches[1];
    }

    /**
     * The happy path of the generation: the token is stored and the link is emailed.
     */
    public function testGenerationStoresTokenAndSendsEmail()
    {
        $this->assertTrue($this->runGenerationRemoteCall());

        $stored_token = get_transient($this->ready_token_key);
        $this->assertIsString($stored_token);
        $this->assertSame($stored_token, $this->getTokenFromSentEmail(), 'The emailed token must match the stored one.');
    }

    /**
     * The token must be a 256 bit random value and must differ from call to call.
     */
    public function testGeneratedTokensAreStrongAndUnique()
    {
        $this->runGenerationRemoteCall();
        $first_token = get_transient($this->ready_token_key);

        $this->runGenerationRemoteCall();
        $second_token = get_transient($this->ready_token_key);

        $this->assertRegExp('/^[a-f0-9]{64}$/', $first_token);
        $this->assertRegExp('/^[a-f0-9]{64}$/', $second_token);
        $this->assertNotSame($first_token, $second_token);
    }

    /**
     * A token that could not be delivered must not stay usable.
     */
    public function testTokenIsDroppedWhenEmailFails()
    {
        $fail_mail = static function () {
            return false;
        };

        add_filter('pre_wp_mail', $fail_mail);
        $result = $this->runGenerationRemoteCall();
        remove_filter('pre_wp_mail', $fail_mail);

        $this->assertFalse($result);
        $this->assertFalse(get_transient($this->ready_token_key));
        $this->assertSame('Failed to send admin email.', FirewallBypass::$last_error);
    }

    /**
     * A regular request without the parameter must not consume the token nor grant anything.
     */
    public function testRegularRequestDoesNotConsumeToken()
    {
        $this->runGenerationRemoteCall();
        $token = get_transient($this->ready_token_key);

        FirewallBypass::maybeSetUserToken();

        $this->assertSame($token, get_transient($this->ready_token_key));
        $this->assertArrayNotHasKey($this->cookie_name, $_COOKIE);
        $this->assertFalse(FirewallBypass::bypassByUserToken());
    }

    /**
     * A wrong token must neither grant the bypass nor invalidate the pending one.
     */
    public function testWrongTokenIsRejected()
    {
        $this->runGenerationRemoteCall();
        $token = get_transient($this->ready_token_key);

        $this->emulateRequestWithToken(str_repeat('0', 64));
        FirewallBypass::maybeSetUserToken();

        $this->assertArrayNotHasKey($this->cookie_name, $_COOKIE);
        $this->assertFalse(FirewallBypass::bypassByUserToken());
        $this->assertSame($token, get_transient($this->ready_token_key), 'A wrong guess must not burn the token.');
    }

    /**
     * The valid link grants the bypass to the browser that has opened it.
     */
    public function testValidTokenGrantsBypass()
    {
        $this->runGenerationRemoteCall();
        $token = get_transient($this->ready_token_key);

        $this->emulateRequestWithToken($token);
        FirewallBypass::maybeSetUserToken();

        $this->assertArrayHasKey($this->cookie_name, $_COOKIE);
        $this->assertRegExp('/^[a-f0-9]{64}$/', $_COOKIE[$this->cookie_name]);
        $this->assertTrue(FirewallBypass::bypassByUserToken(), 'The bypass must work within the activating request.');
    }

    /**
     * The bypass must not depend on the plugin's cookie mode setting: reading and granting
     * the secret must work identically whether the admin picked "native", "alternative" or
     * "none" cookies mode.
     *
     * @dataProvider cookiesTypeProvider
     */
    public function testValidTokenGrantsBypassRegardlessOfCookiesType($cookies_type)
    {
        global $apbct;
        $apbct->data['cookies_type'] = $cookies_type;

        $this->runGenerationRemoteCall();
        $token = get_transient($this->ready_token_key);

        $this->emulateRequestWithToken($token);
        FirewallBypass::maybeSetUserToken();

        $this->assertArrayHasKey(
            $this->cookie_name,
            $_COOKIE,
            "The bypass cookie must be set even when cookies_type is '{$cookies_type}'."
        );
        $this->assertRegExp('/^[a-f0-9]{64}$/', $_COOKIE[$this->cookie_name]);
        $this->assertTrue(
            FirewallBypass::bypassByUserToken(),
            "The bypass must work within the activating request when cookies_type is '{$cookies_type}'."
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function cookiesTypeProvider()
    {
        return array(
            'native'      => array('native'),
            'alternative' => array('alternative'),
            'none'        => array('none'),
        );
    }

    /**
     * The secret itself must never be persisted, only its hash.
     */
    public function testOnlyHashOfSecretIsStored()
    {
        $this->runGenerationRemoteCall();
        $this->emulateRequestWithToken(get_transient($this->ready_token_key));
        FirewallBypass::maybeSetUserToken();

        $secret      = $_COOKIE[$this->cookie_name];
        $stored_hash = get_transient($this->user_token_key);

        $this->assertNotSame($secret, $stored_hash);
        $this->assertSame(hash('sha256', $secret), $stored_hash);
    }

    /**
     * The link is single use, a replay must not grant a second bypass.
     */
    public function testReadyTokenIsSingleUse()
    {
        $this->runGenerationRemoteCall();
        $token = get_transient($this->ready_token_key);

        $this->emulateRequestWithToken($token);
        FirewallBypass::maybeSetUserToken();

        $this->assertFalse(get_transient($this->ready_token_key), 'The token must be consumed on the first use.');

        // An attacker replays the very same link from another browser.
        $this->emulateCookie(null);
        $this->emulateRequestWithToken($token);
        FirewallBypass::maybeSetUserToken();

        $this->assertArrayNotHasKey($this->cookie_name, $_COOKIE);
        $this->assertFalse(FirewallBypass::bypassByUserToken());
    }

    /**
     * A visitor without the secret must not be bypassed even while a bypass is active.
     */
    public function testVisitorWithoutCookieIsDenied()
    {
        $this->runGenerationRemoteCall();
        $this->emulateRequestWithToken(get_transient($this->ready_token_key));
        FirewallBypass::maybeSetUserToken();

        $this->emulateCookie(null);

        $this->assertFalse(FirewallBypass::bypassByUserToken());
    }

    /**
     * Forged and malformed cookies must be rejected.
     *
     * @dataProvider invalidCookieProvider
     *
     * @param string $cookie_value
     */
    public function testInvalidCookieIsDenied($cookie_value)
    {
        $this->runGenerationRemoteCall();
        $this->emulateRequestWithToken(get_transient($this->ready_token_key));
        FirewallBypass::maybeSetUserToken();

        $this->emulateCookie($cookie_value);

        $this->assertFalse(FirewallBypass::bypassByUserToken());
    }

    /**
     * @return array[]
     */
    public function invalidCookieProvider()
    {
        return array(
            'well formed but wrong' => array(str_repeat('a', 64)),
            'empty'                 => array(''),
            'too short'             => array('deadbeef'),
            'uppercase hex'         => array(str_repeat('A', 64)),
            'path traversal'        => array('../../../etc/passwd'),
            'sql injection'         => array("' OR 1=1 -- "),
            'wildcard'              => array('%'),
        );
    }

    /**
     * An array cookie must not break the check.
     */
    public function testArrayCookieIsDenied()
    {
        $this->runGenerationRemoteCall();
        $this->emulateRequestWithToken(get_transient($this->ready_token_key));
        FirewallBypass::maybeSetUserToken();

        $this->emulateCookie(array('injected'));

        $this->assertFalse(FirewallBypass::bypassByUserToken());
    }

    /**
     * The bypass must stop working as soon as the server side record is gone.
     */
    public function testBypassStopsWhenServerRecordExpires()
    {
        $this->runGenerationRemoteCall();
        $this->emulateRequestWithToken(get_transient($this->ready_token_key));
        FirewallBypass::maybeSetUserToken();

        $this->assertTrue(FirewallBypass::bypassByUserToken());

        // Emulates the expiration of the granted bypass.
        delete_transient($this->user_token_key);

        $this->assertFalse(FirewallBypass::bypassByUserToken());
    }

    /**
     * A newly granted bypass must revoke the previous one, only one bypass per site is allowed.
     */
    public function testNewBypassRevokesThePreviousOne()
    {
        $this->runGenerationRemoteCall();
        $this->emulateRequestWithToken(get_transient($this->ready_token_key));
        FirewallBypass::maybeSetUserToken();
        $first_secret = $_COOKIE[$this->cookie_name];

        $this->emulateCookie(null);
        $this->runGenerationRemoteCall();
        $this->emulateRequestWithToken(get_transient($this->ready_token_key));
        FirewallBypass::maybeSetUserToken();
        $second_secret = $_COOKIE[$this->cookie_name];

        $this->assertNotSame($first_secret, $second_secret);

        $this->emulateCookie($first_secret);
        $this->assertFalse(FirewallBypass::bypassByUserToken(), 'The replaced secret must not work anymore.');

        $this->emulateCookie($second_secret);
        $this->assertTrue(FirewallBypass::bypassByUserToken());
    }
}
