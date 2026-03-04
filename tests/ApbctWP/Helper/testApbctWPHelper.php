<?php

namespace Cleantalk\ApbctWP;
use PHPUnit\Framework\TestCase;

class testApbctWPHelper extends TestCase
{
    private $apbct_copy;
    public function setUp(): void
    {
        global $apbct;
        $this->apbct_copy = $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
    }

    protected function tearDown(): void
    {
        global $apbct;
        $apbct = $this->apbct_copy;

        parent::tearDown();
    }
    public function testHttpGetDataFromRemoteGzAndParseCsv()
    {
        $result = Helper::httpGetDataFromRemoteGzAndParseCsv('');
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('error', $result);
    }

}
