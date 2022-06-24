<?php

namespace Cleantalk\Common;

use PHPUnit\Framework\TestCase;

class ValidateTest extends TestCase
{
    public function testIsUrl()
    {
        $this->assertTrue(\Cleantalk\ApbctWP\Validate::isUrl('https://cleantalk.org'));
        $this->assertTrue(\Cleantalk\ApbctWP\Validate::isUrl('https://cleantalk.org'));
        $this->assertTrue(\Cleantalk\ApbctWP\Validate::isUrl('https://cleantalk.org/'));
        $this->assertTrue(\Cleantalk\ApbctWP\Validate::isUrl('https://cleantalk.org/some-path'));
        $this->assertTrue(\Cleantalk\ApbctWP\Validate::isUrl('https://cleantalk.org/some-path/'));
        $this->assertTrue(\Cleantalk\ApbctWP\Validate::isUrl('https://cleantalk.org/some-path?with_parameter=1'));
        $this->assertTrue(\Cleantalk\ApbctWP\Validate::isUrl('https://cleantalk.org/some-path/?with_parameter=1'));
        $this->assertFalse(\Cleantalk\ApbctWP\Validate::isUrl('https://cleantalk.org/ some-path/ with_parameter=1'));
        $this->assertFalse(\Cleantalk\ApbctWP\Validate::isUrl('https://cleantalk.org/some-path/with_parameter=😭'));
        $this->assertFalse(\Cleantalk\ApbctWP\Validate::isUrl('ftp://cleantalk.org'));
        $this->assertFalse(\Cleantalk\ApbctWP\Validate::isUrl('cleantalk.org'));
    }
}
