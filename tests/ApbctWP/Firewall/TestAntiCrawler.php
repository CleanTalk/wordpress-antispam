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

    public function testUpdateAcLogWritesQueryForEachIp(): void
    {
        $module = new AntiCrawler('', 'ac_logs');
        $module->setIpArray(
            array(
                'real' => '198.51.100.15',
                'alt'  => '203.0.113.10',
            )
        );

        $db = $this->getMockBuilder(stdClass::class)
            ->setMethods(array('execute'))
            ->getMock();

        $db->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                array(
                    $this->callback(function ($query) {
                        $this->assertStringContainsString('INSERT INTO ac_logs SET', $query);
                        $this->assertStringContainsString("ip = '198.51.100.15'", $query);
                        $this->assertSame(1, preg_match("/ua = '[a-f0-9]{32}'/", $query));
                        $this->assertStringContainsString('entries = 1', $query);
                        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $query);
                        return true;
                    }),
                ),
                array(
                    $this->callback(function ($query) {
                        $this->assertStringContainsString('INSERT INTO ac_logs SET', $query);
                        $this->assertStringContainsString("ip = '203.0.113.10'", $query);
                        $this->assertSame(1, preg_match("/ua = '[a-f0-9]{32}'/", $query));
                        $this->assertStringContainsString('entries = entries + 1', $query);
                        return true;
                    }),
                )
            );

        $module->setDb($db);
        $module->updateAcLog();
    }

    public function testDiePageRegistersInitHookAndBuildsHtml(): void
    {
        global $apbct;

        $apbct->settings = array(
            'forms__check_external' => '0',
            'forms__check_internal' => '0',
        );
        $apbct->stats = array(
            'sfw' => array('entries' => 7),
        );
        $apbct->data['wl_brandname'] = 'CleanTalk';
        $apbct->data['wl_url'] = 'https://cleantalk.org';
        $apbct->data['service_id'] = 'service-id';

        $module = new AntiCrawler('', '');
        $module->diePage(array('ip' => '203.0.113.10'));

        $this->assertNotFalse(has_action('init', array($module, 'printDiePage')));

        $property = new ReflectionProperty(AntiCrawler::class, 'sfw_die_page');
        $property->setAccessible(true);
        $html = (string) $property->getValue($module);

        $this->assertNotSame('', $html);
        $this->assertStringContainsString('203.0.113.10', $html);
        $this->assertStringContainsString(hash('sha256', $apbct->api_key . $apbct->data['salt']), $html);
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
            ->setMethods(array('fetchAll', 'fetch', 'execute'))
            ->getMock();

        $db->method('fetchAll')->willReturn($uaRows);
        $db->method('fetch')->willReturn($ipFetchResult);

        return $db;
    }
}