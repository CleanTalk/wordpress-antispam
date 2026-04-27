<?php

use Cleantalk\ApbctWP\Firewall\AntiCrawler;
use PHPUnit\Framework\TestCase;

class TestAntiCrawler extends TestCase
{
    private $initialHttpCode;

    public function setUp(): void
    {
        $this->initialHttpCode = http_response_code();
        $this->bootstrapApbct();

        $_SERVER['HTTPS'] = '';
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit-agent';
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $_SERVER['HTTP_HOST'] = is_string($host) && $host !== '' ? $host : 'localhost';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_REFERER'] = '';

        $_COOKIE = array();
    }

    public function tearDown(): void
    {
        http_response_code($this->initialHttpCode ?: 200);
    }

    public function testCheckReturnsDefaultResultsWhenApiKeyInvalid(): void
    {
        global $apbct;
        $apbct->key_is_ok = 0;
        $apbct->api_key = '';

        $module = new AntiCrawler('', '');
        $module->setIpArray(array('real' => '127.0.0.1'));

        $this->assertSame(array(), $module->check());
    }

    public function testCheckReturnsPassWhenRequestIsRedirected(): void
    {
        $module = new AntiCrawler('', '');
        $module->setIpArray(array('real' => '203.0.113.10'));
        $module->setDb($this->createDbStub(array(), null));

        http_response_code(301);
        $result = $module->check();
        http_response_code(200);

        $this->assertCount(1, $result);
        $this->assertSame('203.0.113.10', $result[0]['ip']);
        $this->assertSame('PASS_ANTICRAWLER', $result[0]['status']);
        $this->assertFalse($result[0]['is_personal']);
    }

    public function testCheckReturnsDenyWhenIpFoundInLogsWithoutCookies(): void
    {
        $module = new AntiCrawler('', 'ac_logs');
        $module->setIpArray(array('real' => '198.51.100.15'));
        $module->setDb(
            $this->createDbStub(
                array(), // no UA blacklist entries
                array('ip' => '198.51.100.15') // known IP in AC logs
            )
        );

        $result = $module->check();

        $this->assertCount(1, $result);
        $this->assertSame('198.51.100.15', $result[0]['ip']);
        $this->assertSame('DENY_ANTICRAWLER', $result[0]['status']);
        $this->assertFalse($result[0]['is_personal']);
    }

    private function bootstrapApbct(): void
    {
        global $apbct;

        $apbct = (object) array(
            'key_is_ok' => 1,
            'api_key'   => 'test_api_key',
            'data'      => array(
                'salt'         => 'test_salt',
                'cookies_type' => 'native',
                'key_is_ok'    => 1,
            ),
            'service_constants' => (object) array(
                'skip_anticrawler_on_rss_feed' => new class {
                    public $allowed_public_names = array();

                    public function isDefined()
                    {
                        return false;
                    }
                },
            ),
        );
    }

    private function createDbStub(array $uaRows, $ipFetchResult)
    {
        $db = $this->getMockBuilder(stdClass::class)
            ->setMethods(array('fetchAll', 'fetch'))
            ->getMock();

        $db->method('fetchAll')->willReturn($uaRows);
        $db->method('fetch')->willReturn($ipFetchResult);

        return $db;
    }
}