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

    public function testIsEmailInRecipients()
    {
        $this->assertTrue(\Cleantalk\ApbctWP\Validate::isEmailInRecipients('visitor@example.com', 'visitor@example.com'));
        $this->assertTrue(\Cleantalk\ApbctWP\Validate::isEmailInRecipients('Visitor <Visitor@Example.com>', 'visitor@example.com'));
        $this->assertTrue(\Cleantalk\ApbctWP\Validate::isEmailInRecipients('admin@example.com, "Visitor" <visitor@example.com>', 'visitor@example.com'));
        $this->assertTrue(\Cleantalk\ApbctWP\Validate::isEmailInRecipients(array('admin@example.com', 'visitor@example.com'), 'visitor@example.com'));
        $this->assertFalse(\Cleantalk\ApbctWP\Validate::isEmailInRecipients('admin@example.com', 'min@example.com'));
        $this->assertFalse(\Cleantalk\ApbctWP\Validate::isEmailInRecipients('Admin <admin@example.com>; boss@example.com', 'min@example.com'));
        // Apostrophe in the local part, its unrelated suffix
        $this->assertTrue(\Cleantalk\ApbctWP\Validate::isEmailInRecipients("o'connor@example.com", "o'connor@example.com"));
        $this->assertTrue(\Cleantalk\ApbctWP\Validate::isEmailInRecipients("O'Connor <o'connor@example.com>", "o'connor@example.com"));
        $this->assertFalse(\Cleantalk\ApbctWP\Validate::isEmailInRecipients("o'connor@example.com", 'connor@example.com'));
        // Email-like display name is not a recipient
        $this->assertFalse(\Cleantalk\ApbctWP\Validate::isEmailInRecipients('"visitor@example.com" <admin@example.com>', 'visitor@example.com'));
        $this->assertTrue(\Cleantalk\ApbctWP\Validate::isEmailInRecipients('"visitor@example.com" <admin@example.com>', 'admin@example.com'));
        // Separator inside a quoted display name
        $this->assertFalse(\Cleantalk\ApbctWP\Validate::isEmailInRecipients('"Doe, visitor@example.com" <admin@example.com>', 'visitor@example.com'));
        $this->assertTrue(\Cleantalk\ApbctWP\Validate::isEmailInRecipients('"Doe, John" <john@example.com>, admin@example.com', 'john@example.com'));
        // Escaped quote inside a later display name
        $this->assertTrue(\Cleantalk\ApbctWP\Validate::isEmailInRecipients('visitor@example.com, "The 6\" Shop" <admin@example.com>', 'visitor@example.com'));
        $this->assertTrue(\Cleantalk\ApbctWP\Validate::isEmailInRecipients('visitor@example.com, "The 6\" Shop" <admin@example.com>', 'admin@example.com'));
        $this->assertFalse(\Cleantalk\ApbctWP\Validate::isEmailInRecipients('"The 6\", visitor@example.com" <admin@example.com>', 'visitor@example.com'));
        $this->assertFalse(\Cleantalk\ApbctWP\Validate::isEmailInRecipients('admin@example.com', ''));
        $this->assertFalse(\Cleantalk\ApbctWP\Validate::isEmailInRecipients('', 'visitor@example.com'));
    }
}
