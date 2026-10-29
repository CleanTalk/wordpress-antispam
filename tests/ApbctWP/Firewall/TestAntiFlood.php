<?php

use Cleantalk\ApbctWP\Firewall\AntiFlood;
use Cleantalk\ApbctWP\Variables\Cookie;
use PHPUnit\Framework\TestCase;

class TestAntiFlood extends TestCase
{
    private $initialApbct;
    private $initialCookie;
    private $initialCookieVariables;
    private $initialHttpUserAgent;

    public function setUp(): void
    {
        global $apbct;

        $this->initialApbct = isset($apbct) ? $apbct : null;
        $this->initialCookie = $_COOKIE;
        $this->initialCookieVariables = Cookie::getInstance()->variables;
        $this->initialHttpUserAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;

        $apbct = (object) array(
            'key_is_ok' => 1,
            'api_key'   => 'test_api_key',
            'data'      => array(
                'key_is_ok'    => 1,
                'cookies_type' => 'native',
            ),
        );

        $_COOKIE = array();
        Cookie::getInstance()->variables = array();
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit-agent';
    }

    public function tearDown(): void
    {
        global $apbct;

        if ($this->initialApbct === null) {
            unset($apbct);
        } else {
            $apbct = $this->initialApbct;
        }

        $_COOKIE = $this->initialCookie;
        Cookie::getInstance()->variables = $this->initialCookieVariables;

        if ($this->initialHttpUserAgent === null) {
            unset($_SERVER['HTTP_USER_AGENT']);
        } else {
            $_SERVER['HTTP_USER_AGENT'] = $this->initialHttpUserAgent;
        }
    }

    public function testPassTokenCanBeConsumedOnlyOnceAndCountsTowardTheLimit(): void
    {
        $prepared_query = '';
        $prepared_vars = array();
        $pass_consumptions = 0;
        $executed_queries = array();

        $db = $this->getMockBuilder(stdClass::class)
            ->setMethods(array('fetchAll', 'prepare', 'getQuery', 'execute'))
            ->getMock();

        $db->method('fetchAll')->willReturnCallback(function ($query) {
            return strpos($query, 'ORDER BY') !== false
                ? array()
                : array(array('total_count' => 1));
        });
        $db->method('prepare')->willReturnCallback(function ($query, $vars) use (&$prepared_query, &$prepared_vars) {
            $prepared_query = $query;
            $prepared_vars = $vars;
        });
        $db->method('getQuery')->willReturnCallback(function () use (&$prepared_query) {
            return $prepared_query;
        });
        $db->method('execute')->willReturnCallback(function ($query) use (
            &$pass_consumptions,
            &$executed_queries,
            &$prepared_query,
            &$prepared_vars
        ) {
            $executed_queries[] = $query;

            if (strpos($query, 'DELETE FROM ac_logs') !== false) {
                $pass_consumptions++;
                return $pass_consumptions === 1 ? 1 : 0;
            }

            return 1;
        });

        $module = new AntiFlood(
            'firewall_log',
            'ac_logs',
            array(
                'api_key'       => 'test_api_key',
                'view_limit'    => 1,
                'chance_to_clean' => 0,
            )
        );
        $module->setDb($db);
        $module->setIpArray(array('real' => '203.0.113.10'));

        $create_token = new ReflectionMethod(AntiFlood::class, 'createPassToken');
        $create_token->setAccessible(true);
        $token = $create_token->invoke($module, '203.0.113.10');

        $this->assertSame(1, preg_match('/^[a-f0-9]{64}$/', $token));
        $this->assertStringContainsString('INSERT INTO ac_logs', $prepared_query);
        $this->assertSame(md5('antiflood_pass:' . $token), $prepared_vars[0]);
        $this->assertSame('203.0.113.10', $prepared_vars[1]);

        $_COOKIE[apbct__get_cookie_prefix() . 'apbct_antiflood_passed'] = $token;

        $first_result = $module->check();
        $this->assertSame('PASS_ANTIFLOOD', $first_result[0]['status']);
        $this->assertSame(1, $pass_consumptions);

        // A new request can replay the browser cookie, but the server-side row is gone.
        Cookie::getInstance()->variables = array();
        $second_result = $module->check();

        $this->assertSame('DENY_ANTIFLOOD', $second_result[0]['status']);
        $this->assertSame(2, $pass_consumptions);
        $this->assertStringContainsString('entries = 1', implode("\n", $executed_queries));
    }
}
