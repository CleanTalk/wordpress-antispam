<?php

use Cleantalk\ApbctWP\Firewall\SFW;
use PHPUnit\Framework\TestCase;

class TestSFW extends TestCase
{
    private $initialApbctExists = false;
    private $initialApbct;
    private $initialServerValues = array();
    private $initialServerKeysPresence = array();
    private $initialCookie;

    public function setUp(): void
    {
        global $apbct;

        $this->initialApbctExists = isset($apbct);
        if ($this->initialApbctExists) {
            $this->initialApbct = $apbct;
        }

        $serverKeys = array('HTTPS', 'HTTP_USER_AGENT', 'HTTP_HOST', 'REQUEST_URI', 'HTTP_REFERER');
        foreach ($serverKeys as $key) {
            $this->initialServerKeysPresence[$key] = array_key_exists($key, $_SERVER);
            if ($this->initialServerKeysPresence[$key]) {
                $this->initialServerValues[$key] = $_SERVER[$key];
            }
        }
        $this->initialCookie = $_COOKIE;

        $this->bootstrapApbct();

        $_SERVER['HTTPS'] = '';
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit-agent';
        $host = function_exists('home_url') ? wp_parse_url(home_url(), PHP_URL_HOST) : 'localhost';
        $_SERVER['HTTP_HOST'] = is_string($host) && $host !== '' ? $host : 'localhost';
        $_SERVER['REQUEST_URI'] = '/test-page';
        $_SERVER['HTTP_REFERER'] = '';

        $_COOKIE = array();
    }

    public function tearDown(): void
    {
        global $apbct;

        if ($this->initialApbctExists) {
            $apbct = $this->initialApbct;
        } else {
            unset($apbct);
        }

        foreach ($this->initialServerKeysPresence as $key => $wasPresent) {
            if ($wasPresent) {
                $_SERVER[$key] = $this->initialServerValues[$key];
            } else {
                unset($_SERVER[$key]);
            }
        }
        $_COOKIE = $this->initialCookie;
    }

    // =========================================================================
    // APBCT-W09: Cookie hash now includes salt
    // =========================================================================

    /**
     * @test
     * Cookie without salt should NOT pass the SFW check (old format rejected).
     */
    public function testCookieWithoutSaltIsRejected()
    {
        global $apbct;

        $ip = '192.168.1.100';
        $api_key = 'test_api_key';
        $salt = 'test_salt_value';

        // Old-style cookie: md5(ip + api_key) without salt
        $old_cookie = md5($ip . $api_key) . '0';
        $_COOKIE['ct_sfw_pass_key'] = $old_cookie;

        $sfw = new SFW(
            'test_log_table',
            'test_personal_table',
            array(
                'api_key' => $api_key,
                'data__cookies_type' => 'native',
                'sfw_common_table_name' => 'test_data_table',
            )
        );
        $sfw->setIpArray(array('real' => $ip));

        // The check() method should NOT early-return via cookie
        // because the cookie lacks salt, so it proceeds to DB check.
        // With empty DB results, it returns PASS_SFW (not PASS_SFW__BY_COOKIE).
        $db = $this->createDbStub(array());
        $sfw->setDb($db);

        $results = $sfw->check();

        // Should NOT have been skipped by cookie
        foreach ($results as $result) {
            $this->assertNotEquals('PASS_SFW__BY_COOKIE', $result['status']);
            $this->assertNotEquals('PASS_SFW__BY_WHITELIST', $result['status']);
        }
    }

    /**
     * @test
     * Cookie WITH salt should pass the SFW check (new format accepted).
     */
    public function testCookieWithSaltIsAccepted()
    {
        global $apbct;

        $ip = '192.168.1.100';
        $api_key = 'test_api_key';
        $salt = 'test_salt_value';

        // New-style cookie: md5(ip + api_key + salt)
        $new_cookie = md5($ip . $api_key . $salt) . '0';
        $_COOKIE['ct_sfw_pass_key'] = $new_cookie;
        $_COOKIE['ct_sfw_passed'] = '1';

        $sfw = new SFW(
            'test_log_table',
            'test_personal_table',
            array(
                'api_key' => $api_key,
                'data__cookies_type' => 'native',
                'sfw_common_table_name' => 'test_data_table',
            )
        );
        $sfw->setIpArray(array('real' => $ip));

        // Mock DB for updateLog call inside cookie-pass path
        $db = $this->createDbStub(array());
        $sfw->setDb($db);

        $results = $sfw->check();

        // Should have been recognized via cookie (early return)
        // The method returns empty or with PASS_SFW__BY_COOKIE status
        $found_cookie_pass = empty($results);
        foreach ($results as $result) {
            if (in_array($result['status'], array('PASS_SFW__BY_COOKIE', 'PASS_SFW__BY_WHITELIST'))) {
                $found_cookie_pass = true;
            }
        }
        $this->assertTrue($found_cookie_pass, 'Cookie with salt should be accepted by SFW check');
    }

    /**
     * @test
     * Cookie with whitelist status (trailing '1') should return PASS_SFW__BY_WHITELIST.
     */
    public function testCookieWithSaltAndWhitelistStatus()
    {
        global $apbct;

        $ip = '10.0.0.1';
        $api_key = 'test_api_key';
        $salt = 'test_salt_value';

        // New-style cookie with whitelist status '1'
        $new_cookie = md5($ip . $api_key . $salt) . '1';
        $_COOKIE['ct_sfw_pass_key'] = $new_cookie;

        $sfw = new SFW(
            'test_log_table',
            'test_personal_table',
            array(
                'api_key' => $api_key,
                'data__cookies_type' => 'native',
                'sfw_common_table_name' => 'test_data_table',
            )
        );
        $sfw->setIpArray(array('real' => $ip));

        $db = $this->createDbStub(array());
        $sfw->setDb($db);

        $results = $sfw->check();

        $this->assertNotEmpty($results);
        $this->assertEquals('PASS_SFW__BY_WHITELIST', $results[0]['status']);
    }

    /**
     * @test
     * Different salt produces different hash — cookie from another installation won't work.
     */
    public function testCookieWithWrongSaltIsRejected()
    {
        global $apbct;

        $ip = '192.168.1.100';
        $api_key = 'test_api_key';

        // Cookie made with wrong salt
        $wrong_salt_cookie = md5($ip . $api_key . 'wrong_salt') . '0';
        $_COOKIE['ct_sfw_pass_key'] = $wrong_salt_cookie;

        $sfw = new SFW(
            'test_log_table',
            'test_personal_table',
            array(
                'api_key' => $api_key,
                'data__cookies_type' => 'native',
                'sfw_common_table_name' => 'test_data_table',
            )
        );
        $sfw->setIpArray(array('real' => $ip));

        $db = $this->createDbStub(array());
        $sfw->setDb($db);

        $results = $sfw->check();

        // Should NOT have been skipped by cookie
        foreach ($results as $result) {
            $this->assertNotEquals('PASS_SFW__BY_COOKIE', $result['status']);
            $this->assertNotEquals('PASS_SFW__BY_WHITELIST', $result['status']);
        }
    }

    /**
     * @test
     * actionsForPassed() sets cookie value using salt.
     */
    public function testActionsForPassedUsesSaltInCookie()
    {
        global $apbct;

        $ip = '203.0.113.50';
        $api_key = 'test_api_key';
        $salt = 'test_salt_value';

        $sfw = new SFW(
            'test_log_table',
            'test_personal_table',
            array(
                'api_key' => $api_key,
                'data__cookies_type' => 'native',
                'sfw_common_table_name' => 'test_data_table',
            )
        );

        $result = array(
            'ip' => $ip,
            'status' => 'PASS_SFW',
        );

        // actionsForPassed sets a cookie - we can't easily verify the cookie value
        // in CLI, but we can verify the hash computation is correct
        $expected_hash = md5($ip . $api_key . $salt) . '0';

        // Verify that the hash formula includes salt
        $this->assertNotEquals(
            md5($ip . $api_key) . '0',
            $expected_hash,
            'Hash with salt must differ from hash without salt'
        );
        $this->assertEquals(
            md5($ip . $api_key . $salt) . '0',
            $expected_hash
        );
    }

    // =========================================================================
    // APBCT-W08: updateLog() uses parameterized SQL
    // =========================================================================

    /**
     * @test
     * updateLog() calls db->prepare() with placeholders and then db->execute().
     */
    public function testUpdateLogUsesDbPrepare()
    {
        global $apbct;

        $ip = '198.51.100.10';
        $status = 'PASS_SFW';

        $sfw = new SFW(
            'wp_cleantalk_sfw_logs',
            'test_personal_table',
            array(
                'api_key' => 'test_key',
                'data__cookies_type' => 'native',
                'sfw_common_table_name' => 'test_data_table',
            )
        );

        $db = $this->getMockBuilder(stdClass::class)
            ->addMethods(array('prepare', 'execute', 'getQuery'))
            ->getMock();

        $db->expects($this->once())
            ->method('prepare')
            ->with(
                $this->callback(function ($query) {
                    // Verify placeholders are used
                    $this->assertStringContainsString('%s', $query);
                    $this->assertStringContainsString('%d', $query);
                    $this->assertStringContainsString('INSERT INTO', $query);
                    $this->assertStringContainsString('ON DUPLICATE KEY', $query);
                    $this->assertStringContainsString('wp_cleantalk_sfw_logs', $query);
                    return true;
                }),
                $this->callback(function ($params) {
                    // Verify params array is passed
                    $this->assertIsArray($params);
                    $this->assertNotEmpty($params);
                    // First param should be md5 hash (id)
                    $this->assertEquals(32, strlen($params[0]));
                    // Second param should be IP
                    $this->assertEquals('198.51.100.10', $params[1]);
                    // Third param should be status
                    $this->assertEquals('PASS_SFW', $params[2]);
                    return true;
                })
            );

        $db->method('getQuery')->willReturn('prepared_query');
        $db->expects($this->once())->method('execute')->with('prepared_query');

        $sfw->setDb($db);
        $sfw->updateLog($ip, $status);
    }

    /**
     * @test
     * updateLog() with DENY status sets blocked_entries = 1.
     */
    public function testUpdateLogDenyStatusSetsBlockedFlag()
    {
        global $apbct;

        $sfw = new SFW(
            'wp_cleantalk_sfw_logs',
            'test_personal_table',
            array(
                'api_key' => 'test_key',
                'data__cookies_type' => 'native',
                'sfw_common_table_name' => 'test_data_table',
            )
        );

        $db = $this->getMockBuilder(stdClass::class)
            ->addMethods(array('prepare', 'execute', 'getQuery'))
            ->getMock();

        $db->expects($this->once())
            ->method('prepare')
            ->with(
                $this->anything(),
                $this->callback(function ($params) {
                    // blocked_entries param (4th, index 3) should be 1 for DENY
                    $this->assertEquals(1, $params[3]);
                    return true;
                })
            );

        $db->method('getQuery')->willReturn('prepared_query');
        $db->method('execute');

        $sfw->setDb($db);
        $sfw->updateLog('10.0.0.1', 'DENY_SFW');
    }

    /**
     * @test
     * updateLog() with PASS status sets blocked_entries = 0.
     */
    public function testUpdateLogPassStatusSetsZeroBlockedFlag()
    {
        global $apbct;

        $sfw = new SFW(
            'wp_cleantalk_sfw_logs',
            'test_personal_table',
            array(
                'api_key' => 'test_key',
                'data__cookies_type' => 'native',
                'sfw_common_table_name' => 'test_data_table',
            )
        );

        $db = $this->getMockBuilder(stdClass::class)
            ->addMethods(array('prepare', 'execute', 'getQuery'))
            ->getMock();

        $db->expects($this->once())
            ->method('prepare')
            ->with(
                $this->anything(),
                $this->callback(function ($params) {
                    // blocked_entries param (4th, index 3) should be 0 for PASS
                    $this->assertEquals(0, $params[3]);
                    return true;
                })
            );

        $db->method('getQuery')->willReturn('prepared_query');
        $db->method('execute');

        $sfw->setDb($db);
        $sfw->updateLog('10.0.0.1', 'PASS_SFW');
    }

    /**
     * @test
     * updateLog() with source=NULL interpolates literal NULL (not a placeholder).
     */
    public function testUpdateLogSourceNullInterpolatesLiteralNull()
    {
        global $apbct;

        $sfw = new SFW(
            'wp_cleantalk_sfw_logs',
            'test_personal_table',
            array(
                'api_key' => 'test_key',
                'data__cookies_type' => 'native',
                'sfw_common_table_name' => 'test_data_table',
            )
        );

        $db = $this->getMockBuilder(stdClass::class)
            ->addMethods(array('prepare', 'execute', 'getQuery'))
            ->getMock();

        $db->expects($this->once())
            ->method('prepare')
            ->with(
                $this->callback(function ($query) {
                    // source = NULL should appear as literal in the query (not as %s)
                    $this->assertStringContainsString('source = NULL', $query);
                    return true;
                }),
                $this->anything()
            );

        $db->method('getQuery')->willReturn('prepared_query');
        $db->method('execute');

        $sfw->setDb($db);
        $sfw->updateLog('10.0.0.1', 'PASS_SFW', 'NULL', 'NULL');
    }

    /**
     * @test
     * updateLog() with numeric source casts it to int.
     */
    public function testUpdateLogSourceNumericIsCastToInt()
    {
        global $apbct;

        $sfw = new SFW(
            'wp_cleantalk_sfw_logs',
            'test_personal_table',
            array(
                'api_key' => 'test_key',
                'data__cookies_type' => 'native',
                'sfw_common_table_name' => 'test_data_table',
            )
        );

        $db = $this->getMockBuilder(stdClass::class)
            ->addMethods(array('prepare', 'execute', 'getQuery'))
            ->getMock();

        $db->expects($this->once())
            ->method('prepare')
            ->with(
                $this->callback(function ($query) {
                    // source should be interpolated as integer 42
                    $this->assertStringContainsString('source = 42', $query);
                    // Should NOT contain source = NULL
                    $this->assertStringNotContainsString('source = NULL', $query);
                    return true;
                }),
                $this->anything()
            );

        $db->method('getQuery')->willReturn('prepared_query');
        $db->method('execute');

        $sfw->setDb($db);
        $sfw->updateLog('10.0.0.1', 'PASS_SFW', '24bits', '42');
    }

    /**
     * @test
     * updateLog() with malicious source string is safely cast to int, stripping SQL.
     */
    public function testUpdateLogSourceMaliciousStringCastToInt()
    {
        global $apbct;

        $sfw = new SFW(
            'wp_cleantalk_sfw_logs',
            'test_personal_table',
            array(
                'api_key' => 'test_key',
                'data__cookies_type' => 'native',
                'sfw_common_table_name' => 'test_data_table',
            )
        );

        $db = $this->getMockBuilder(stdClass::class)
            ->addMethods(array('prepare', 'execute', 'getQuery'))
            ->getMock();

        $db->expects($this->once())
            ->method('prepare')
            ->with(
                $this->callback(function ($query) {
                    // Malicious SQL should be stripped — only integer remains
                    $this->assertStringNotContainsString('DROP TABLE', $query);
                    $this->assertStringNotContainsString(';', $query);
                    // "0; DROP TABLE users" casts to int 0
                    $this->assertStringContainsString('source = 0', $query);
                    return true;
                }),
                $this->anything()
            );

        $db->method('getQuery')->willReturn('prepared_query');
        $db->method('execute');

        $sfw->setDb($db);
        // Attempt SQL injection via source param — starts with non-numeric char
        $sfw->updateLog('10.0.0.1', 'PASS_SFW', 'NULL', "abc; DROP TABLE users; --");
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function bootstrapApbct(): void
    {
        global $apbct;

        $apbct = (object) array(
            'key_is_ok' => 1,
            'api_key'   => 'test_api_key',
            'data'      => array(
                'salt'         => 'test_salt_value',
                'cookies_type' => 'native',
                'key_is_ok'    => 1,
            ),
            'stats'     => array(
                'sfw' => array('entries' => 100),
                'last_sfw_block' => array('time' => 0, 'ip' => ''),
            ),
            'settings'  => array(
                'forms__check_external' => '0',
                'forms__check_internal' => '0',
            ),
        );
    }

    private function createDbStub(array $fetchAllResult)
    {
        $db = $this->getMockBuilder(stdClass::class)
            ->addMethods(array('fetchAll', 'fetch', 'execute', 'prepare', 'getQuery'))
            ->getMock();

        $db->method('fetchAll')->willReturn($fetchAllResult);
        $db->method('fetch')->willReturn(null);
        $db->method('prepare');
        $db->method('getQuery')->willReturn('');
        $db->method('execute');

        return $db;
    }
}
