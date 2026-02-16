<?php

namespace Cleantalk\ApbctWP;
use PHPUnit\Framework\TestCase;

class testApbctWPHelper extends TestCase
{
    public function testHttpGetDataFromRemoteGzAndParseCsv()
    {
        $result = Helper::httpGetDataFromRemoteGzAndParseCsv('');
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('error', $result);
    }

}
