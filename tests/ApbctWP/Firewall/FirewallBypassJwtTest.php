<?php

namespace ApbctWP\Firewall;

use Cleantalk\ApbctWP\Firewall\FirewallBypass;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;
use ReflectionClass;

/**
 * Tests for the emergency firewall bypass by the signed token from the control panel.
 *
 * @see FirewallBypass::processJwtRequest()
 */
class FirewallBypassJwtTest extends \ApbctTestCase
{
    private const KEY = 'test_access_key';

    private const VISITOR_IP = '203.0.113.10';

    /**
     * @var string Original access key.
     */
    private $original_api_key;

    /**
     * @var string Transient key holding the hash of the granted secret.
     */
    private $user_token_key;

    /**
     * @var string Name of the cookie holding the granted secret.
     */
    private $cookie_name;

    protected function setUp(): void
    {
        parent::setUp();

        global $apbct;

        $constants            = (new ReflectionClass(FirewallBypass::class))->getConstants();
        $this->user_token_key = $constants['USER_TOKEN_TRANSIENT_KEY'];
        $this->cookie_name    = $constants['USER_COOKIE_NAME'];

        $this->original_api_key = $apbct->api_key;
        $apbct->api_key         = self::KEY;

        $this->resetEnvironment();
    }

    protected function tearDown(): void
    {
        global $apbct;

        $apbct->api_key = $this->original_api_key;

        $this->resetEnvironment();
        FirewallBypass::$last_error = null;

        parent::tearDown();
    }

    /**
     * @return void
     */
    private function resetEnvironment()
    {
        global $wpdb;

        delete_transient($this->user_token_key);
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'apbct\\_fw\\_bypass\\_jti\\_%'");
        unset($_POST['ct_fw_bypass_jwt'], $_COOKIE[$this->cookie_name]);
        $_SERVER['REMOTE_ADDR'] = self::VISITOR_IP;
        $this->resetRequestCache();
    }

    /**
     * The variable handlers and the IP helper cache the values, drop the cache between the request emulations.
     *
     * @return void
     */
    private function resetRequestCache()
    {
        Post::getInstance()->variables   = array();
        Server::getInstance()->variables = array();
        Cookie::getInstance()->variables = array();

        $ips_stored = new \ReflectionProperty(\Cleantalk\Common\Helper::class, 'ips_stored');
        $ips_stored->setAccessible(true);
        $ips_stored->setValue(Helper::getInstance(), array());
    }

    /**
     * @param array  $payload_overrides Null value removes the claim.
     * @param string $key
     * @param array  $header
     *
     * @return string
     */
    private function makeToken($payload_overrides = array(), $key = self::KEY, $header = array('alg' => 'HS256', 'typ' => 'JWT'))
    {
        $payload = array_merge(
            array(
                'svc' => 'antispam',
                'aud' => parse_url(home_url(), PHP_URL_HOST),
                'ip'  => self::VISITOR_IP,
                'exp' => time() + 300,
                'jti' => bin2hex(random_bytes(16)),
            ),
            $payload_overrides
        );
        $payload = array_filter($payload, function ($value) {
            return $value !== null;
        });

        $signing_input = $this->encode(json_encode($header)) . '.' . $this->encode(json_encode($payload));

        return $signing_input . '.' . $this->encode(hash_hmac('sha256', $signing_input, $key, true));
    }

    /**
     * @param string $data
     *
     * @return string
     */
    private function encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @param string $token
     *
     * @return string|null
     */
    private function postToken($token)
    {
        $this->resetRequestCache();
        $_POST['ct_fw_bypass_jwt'] = $token;

        return FirewallBypass::processJwtRequest();
    }

    /**
     * @param string|null $result
     * @param string      $expected_error
     *
     * @return void
     */
    private function assertRejected($result, $expected_error)
    {
        $this->assertSame(home_url('/'), $result);
        $this->assertSame($expected_error, FirewallBypass::$last_error);
        $this->assertFalse(get_transient($this->user_token_key), 'The bypass must not be granted.');
    }

    public function testValidTokenGrantsBypass()
    {
        $result = $this->postToken($this->makeToken());

        $this->assertSame(admin_url(), $result, (string)FirewallBypass::$last_error);
        $this->assertTrue(FirewallBypass::bypassByUserToken());
    }

    public function testTokenIsSingleUse()
    {
        $token = $this->makeToken();
        $this->postToken($token);
        delete_transient($this->user_token_key);

        $this->assertRejected($this->postToken($token), 'The token has already been used.');
    }

    public function testClaimedTokenIdIsRejected()
    {
        global $wpdb;

        $jti = bin2hex(random_bytes(16));
        $wpdb->insert(
            $wpdb->options,
            array('option_name' => 'apbct_fw_bypass_jti_' . hash('sha256', $jti), 'option_value' => (string)(time() + 300), 'autoload' => 'no')
        );

        $this->assertRejected($this->postToken($this->makeToken(array('jti' => $jti))), 'The token has already been used.');
    }

    public function testExpiredClaimsAreDropped()
    {
        global $wpdb;

        $expired_claim = 'apbct_fw_bypass_jti_' . hash('sha256', 'expired');
        $wpdb->insert(
            $wpdb->options,
            array('option_name' => $expired_claim, 'option_value' => (string)(time() - 1), 'autoload' => 'no')
        );

        $this->assertSame(admin_url(), $this->postToken($this->makeToken()));

        $claims = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'apbct\\_fw\\_bypass\\_jti\\_%'");
        $this->assertNotContains($expired_claim, $claims);
        $this->assertCount(1, $claims, 'Only the claim of the just used token must stay.');
    }

    public function testTokenForOtherPluginIsSkipped()
    {
        // Signed with another key: the signature must not even be checked.
        $result = $this->postToken($this->makeToken(array('svc' => 'security'), 'other_plugin_key'));

        $this->assertNull($result);
        $this->assertNull(FirewallBypass::$last_error);
        $this->assertFalse(get_transient($this->user_token_key));
    }

    public function testUnknownServiceIsRejected()
    {
        $this->assertRejected($this->postToken($this->makeToken(array('svc' => 'other'))), 'The token service is unknown.');
        $this->assertRejected($this->postToken($this->makeToken(array('svc' => null))), 'The token service is unknown.');
    }

    public function testWrongSignatureIsRejected()
    {
        $this->assertRejected($this->postToken($this->makeToken(array(), 'wrong_key')), 'The token signature is invalid.');
    }

    public function testTamperedPayloadIsRejected()
    {
        $segments    = explode('.', $this->makeToken());
        $segments[1] = $this->encode(json_encode(array(
            'svc' => 'antispam',
            'aud' => parse_url(home_url(), PHP_URL_HOST),
            'ip'  => self::VISITOR_IP,
            'exp' => time() + 3600,
            'jti' => bin2hex(random_bytes(16)),
        )));

        $this->assertRejected($this->postToken(implode('.', $segments)), 'The token signature is invalid.');
    }

    public function testUnsupportedHeaderIsRejected()
    {
        $this->assertRejected(
            $this->postToken($this->makeToken(array(), self::KEY, array('alg' => 'none', 'typ' => 'JWT'))),
            'The token header is not supported.'
        );
        $this->assertRejected(
            $this->postToken($this->makeToken(array(), self::KEY, array('alg' => 'HS512', 'typ' => 'JWT'))),
            'The token header is not supported.'
        );
    }

    public function testEmptyAccessKeyIsRejected()
    {
        global $apbct;
        $apbct->api_key = '';

        $this->assertRejected($this->postToken($this->makeToken(array(), '')), 'The access key is not set.');
    }

    public function testExpirationIsChecked()
    {
        $this->assertRejected(
            $this->postToken($this->makeToken(array('exp' => time() - 61))),
            'The token is expired or its lifetime is too long.'
        );
        $this->assertRejected(
            $this->postToken($this->makeToken(array('exp' => time() + 361))),
            'The token is expired or its lifetime is too long.'
        );
        $this->assertRejected(
            $this->postToken($this->makeToken(array('exp' => (string)(time() + 300)))),
            'The token is expired or its lifetime is too long.'
        );

        // Clock skew tolerance.
        $this->assertSame(admin_url(), $this->postToken($this->makeToken(array('exp' => time() - 30))));
    }

    public function testSiteIsChecked()
    {
        $this->assertRejected(
            $this->postToken($this->makeToken(array('aud' => 'another-site.com'))),
            'The token is issued for another site.'
        );

        // Case and "www." do not matter.
        $aud = 'WWW.' . strtoupper(parse_url(home_url(), PHP_URL_HOST));
        $this->assertSame(admin_url(), $this->postToken($this->makeToken(array('aud' => $aud))));
    }

    public function testIpIsChecked()
    {
        $this->assertRejected(
            $this->postToken($this->makeToken(array('ip' => '203.0.113.11'))),
            'The token is issued for another IP.'
        );
        $this->assertRejected(
            $this->postToken($this->makeToken(array('ip' => null))),
            'The token is issued for another IP.'
        );
    }

    /**
     * @dataProvider equivalentIpProvider
     */
    public function testEquivalentIpNotationsMatch($request_ip, $token_ip)
    {
        $_SERVER['REMOTE_ADDR'] = $request_ip;

        $this->assertSame(admin_url(), $this->postToken($this->makeToken(array('ip' => $token_ip))), (string)FirewallBypass::$last_error);
    }

    public function equivalentIpProvider()
    {
        return array(
            'expanded IPv6'     => array('2001:db8::1', '2001:0db8:0000:0000:0000:0000:0000:0001'),
            'upper case IPv6'   => array('2001:db8::1', '2001:0DB8::1'),
            'compressed token'  => array('2001:0DB8:0:0:0:0:0:1', '2001:db8::1'),
            'IPv4-mapped token' => array(self::VISITOR_IP, '::ffff:' . self::VISITOR_IP),
        );
    }

    public function testMalformedTokenIsRejected()
    {
        $this->assertRejected($this->postToken(''), 'The token is missing or too long.');
        $this->assertRejected($this->postToken(str_repeat('a', 2049)), 'The token is missing or too long.');
        $this->assertRejected($this->postToken('a.b'), 'The token is malformed.');
        $this->assertRejected($this->postToken('a.b.c.d'), 'The token is malformed.');
        $this->assertRejected($this->postToken('a.!!!.c'), 'The token service is unknown.');
        $this->assertRejected(
            $this->postToken($this->makeToken(array('jti' => 'not-a-hex-id'))),
            'The token ID is malformed.'
        );
    }
}
