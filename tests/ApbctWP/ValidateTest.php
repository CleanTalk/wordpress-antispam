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
        $this->assertFalse(\Cleantalk\ApbctWP\Validate::isUrl('javascript:alert(1)'));
        $this->assertFalse(\Cleantalk\ApbctWP\Validate::isUrl('javascript:alert(String.fromCharCode(80,83,67,45,88,83,83));void 0'));
        $this->assertFalse(\Cleantalk\ApbctWP\Validate::isUrl('javascript://https://evil.com%0aalert(1)'));
        $this->assertFalse(\Cleantalk\ApbctWP\Validate::isUrl("javascript:alert('http://x')"));
        $this->assertFalse(\Cleantalk\ApbctWP\Validate::isUrl('data:text/html,<script>alert(1)</script>'));
        $this->assertFalse(\Cleantalk\ApbctWP\Validate::isUrl('vbscript:msgbox(1)'));
        $this->assertTrue(\Cleantalk\ApbctWP\Validate::isUrl('http://45.137.81.184/'));
    }
}
