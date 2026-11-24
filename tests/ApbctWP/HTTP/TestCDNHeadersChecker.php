<?php

namespace Cleantalk\ApbctWP\HTTP;

use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

class TestCDNHeadersChecker extends TestCase
{
    private $apbct_copy;
    private $server_copy;

    public function setUp(): void
    {
        global $apbct;
        $this->apbct_copy  = $apbct;
        $this->server_copy = $_SERVER;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $this->resetHelperCache();
    }

    protected function tearDown(): void
    {
        global $apbct;
        $apbct   = $this->apbct_copy;
        $_SERVER = $this->server_copy;
        $this->resetHelperCache();

        parent::tearDown();
    }

    private function resetHelperCache()
    {
        \Cleantalk\ApbctWP\Variables\Server::getInstance()->variables = array();
        foreach ( array(\Cleantalk\Common\Helper::getInstance(), Helper::getInstance()) as $instance ) {
            foreach ( array('ips_stored', 'headers') as $property ) {
                $reflection = new \ReflectionProperty(\Cleantalk\Common\Helper::class, $property);
                $reflection->setAccessible(true);
                $reflection->setValue($instance, array());
            }
        }
    }

    private function setRequest($remote_addr, $headers)
    {
        $_SERVER['REMOTE_ADDR'] = $remote_addr;
        foreach ( $headers as $name => $value ) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $this->resetHelperCache();
    }

    public function testRemoteAddrSourceIgnoresForgedHeaders()
    {
        global $apbct;
        $apbct->settings['misc__get_ip'] = Helper::IP_SOURCE_REMOTE_ADDR;
        $this->setRequest('10.0.0.1', array(
            'X-Sucuri-Clientip' => '1.2.3.4',
            'Cf-Connecting-Ip'  => '5.6.7.8',
            'Cf-Ray'            => 'test',
        ));

        $this->assertEquals('10.0.0.1', Helper::ipGet('real'));
        $this->assertEquals('10.0.0.1', Helper::ipGet());
    }

    public function testSelectedHeaderSourceIsUsedAndOthersIgnored()
    {
        global $apbct;
        $apbct->settings['misc__get_ip'] = Helper::getIpHeaderIDBySlug('cloud_flare');
        $this->setRequest('10.0.0.1', array(
            'X-Sucuri-Clientip' => '1.2.3.4',
            'Cf-Connecting-Ip'  => '5.6.7.8',
            'Cf-Ray'            => 'test',
        ));

        $this->assertEquals('5.6.7.8', Helper::ipGet('real'));
    }

    public function testMissingSelectedHeaderFallsBackToRemoteAddr()
    {
        global $apbct;
        $apbct->settings['misc__get_ip'] = Helper::getIpHeaderIDBySlug('cloud_flare');
        $this->setRequest('10.0.0.1', array('X-Sucuri-Clientip' => '1.2.3.4'));

        $this->assertEquals('10.0.0.1', Helper::ipGet('real'));
    }

    public function testExplicitIpTypeIsNotRemapped()
    {
        global $apbct;
        $apbct->settings['misc__get_ip'] = Helper::IP_SOURCE_REMOTE_ADDR;
        $this->setRequest('10.0.0.1', array('X-Sucuri-Clientip' => '1.2.3.4'));

        $this->assertEquals('1.2.3.4', Helper::ipGet('sucury'));
    }

    public function testGetHeadersWithIp()
    {
        $result = CDNHeadersChecker::getHeadersWithIp(array(
            'Cf-Connecting-Ip' => '5.6.7.8',
            'X-Forwarded-For'  => '1.1.1.1, 2.2.2.2',
            'Cf-Ray'           => 'abc',
            'Remote-Ip'        => 'not an ip',
        ));

        $this->assertEquals(array('Cf-Connecting-Ip', 'X-Forwarded-For'), $result);
    }

    public function testFindKnownCDNHeaderSlugs()
    {
        $result = CDNHeadersChecker::findKnownCDNHeaderSlugs(
            array('cf-connecting-ip', 'X-Forwarded-For', 'X-Cf-Connecting-Ip-Fake'),
            Helper::getKnownCDNHeadersNames()
        );

        $this->assertEquals(array('cloud_flare', 'x_forwarded_for'), $result);
    }

    public function testGetWorkHeaderSlugPrefersCDNSpecificHeaders()
    {
        $this->assertEquals('cloud_flare', CDNHeadersChecker::getWorkHeaderSlug(array('x_forwarded_for', 'cloud_flare')));
        $this->assertEquals('x_forwarded_for', CDNHeadersChecker::getWorkHeaderSlug(array('x_forwarded_for', 'x_real_ip')));
        $this->assertNull(CDNHeadersChecker::getWorkHeaderSlug(array()));
    }

    public function testGetNewHeaderIdForSettings()
    {
        $cloud_flare_id = Helper::getIpHeaderIDBySlug('cloud_flare');

        $this->assertEquals(Helper::IP_SOURCE_REMOTE_ADDR, CDNHeadersChecker::getNewHeaderIdForSettings(1, array()));
        $this->assertNull(CDNHeadersChecker::getNewHeaderIdForSettings(Helper::IP_SOURCE_REMOTE_ADDR, array()));
        $this->assertEquals($cloud_flare_id, CDNHeadersChecker::getNewHeaderIdForSettings(1, array('cloud_flare')));
        $this->assertNull(CDNHeadersChecker::getNewHeaderIdForSettings($cloud_flare_id, array('cloud_flare')));
    }

    public function testStoredDataIsLimited()
    {
        global $apbct;
        $apbct->data['cdn_checker_results'] = array();

        for ( $i = 0; $i < CDNHeadersChecker::RECORDS_LIMIT + 3; $i++ ) {
            CDNHeadersChecker::updateStoredCDNHeadersData(array('cloud_flare'), null);
        }

        $this->assertCount(CDNHeadersChecker::RECORDS_LIMIT, CDNHeadersChecker::loadStoredCDNHeadersData());
    }
}
