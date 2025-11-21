<?php

use PHPUnit\Framework\TestCase;

class SfwUpdateInitTest extends TestCase
{
    private $apbctBackup;

    protected function setUp(): void
    {
        global $apbct;

        $this->apbctBackup = $apbct;

        $apbct = new \Cleantalk\ApbctWP\State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));

        // Mock Sentinel
        $apbct->sfw_update_sentinel = $this
            ->getMockBuilder(\Cleantalk\ApbctWP\Firewall\SFWUpdateSentinel::class)
            ->disableOriginalConstructor()
            ->setMethods(['seekId', 'getWatchDogCronPeriod', 'clearSentinelData'])
            ->getMock();

        $apbct->sfw_update_sentinel
            ->method('seekId')
            ->willReturn(true);

        $apbct->sfw_update_sentinel
            ->method('getWatchDogCronPeriod')
            ->willReturn(60);

        $apbct->sfw_update_sentinel
            ->method('clearSentinelData')
            ->willReturn(null);

        $apbct->api_key = getenv("CLEANTALK_TEST_API_KEY");
        $apbct->data['key_is_ok'] = 1;
    }

    protected function tearDown(): void
    {
        global $apbct;
        $apbct = $this->apbctBackup;
    }

    public function test_sfw_update_init_covered()
    {
        global $apbct;
        // main call
        $result = apbct_sfw_update__init(0);

        // check
        $this->assertNotFalse($result);
    }
}
