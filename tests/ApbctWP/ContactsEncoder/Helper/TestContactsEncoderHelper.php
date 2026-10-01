<?php

namespace ApbctWP\ContactsEncoder\Helper;

use Cleantalk\Common\ContactsEncoder\Helper\ContactsEncoderHelper;
use PHPUnit\Framework\TestCase;

class TestContactsEncoderHelper extends TestCase
{
    /**
     * @var ContactsEncoderHelper
     */
    private $helper;

    protected function setUp(): void
    {
        $this->helper = new ContactsEncoderHelper();
    }

    public function testHasAttributeExclusionsForInputDataMask()
    {
        $mask = '(999) 999-9999';
        $content = '<input type="tel" class="large" data-mask="' . $mask . '" />';

        $this->assertTrue($this->helper->hasAttributeExclusions($mask, $content));
    }

    public function testHasAttributeExclusionsForInputPlaceholder()
    {
        $email = 'info@example.com';
        $content = '<input type="email" placeholder="' . $email . '" />';

        $this->assertTrue($this->helper->hasAttributeExclusions($email, $content));
    }

    public function testHasAttributeExclusionsReturnsFalseForPlainPhone()
    {
        $phone = '(800) 555-1234';
        $content = 'Call us at ' . $phone;

        $this->assertFalse($this->helper->hasAttributeExclusions($phone, $content));
    }

    public function testHasAttributeExclusionsHonorsAddAttributeNames()
    {
        $this->helper->addAttributeNames(array('data-phone-format'));

        $mask = '(999) 321-1233';
        $content = '<span data-phone-format="' . $mask . '"></span>';

        $this->assertTrue($this->helper->hasAttributeExclusions($mask, $content));
    }

    public function testHasAttributeExclusionsHonorsAddAttributeExclusions()
    {
        $this->helper->addAttributeExclusions('span', array('data-phone-mask'));

        $mask = '(999) 321-1233';
        $content = '<span data-phone-mask="' . $mask . '"></span>';

        $this->assertTrue($this->helper->hasAttributeExclusions($mask, $content));
    }

    public function testHasAttributeExclusionsIgnoresPlainTextAttributeAssignment()
    {
        $this->helper->addAttributeNames(array('data-phone-format'));

        $mask = '(999) 321-1233';
        $content = 'Set data-phone-format="' . $mask . '" in the docs';

        $this->assertFalse($this->helper->hasAttributeExclusions($mask, $content));
    }

    public function testHasAttributeExclusionsReturnsFalseForEmptyMatch()
    {
        $this->assertFalse($this->helper->hasAttributeExclusions('', '<input data-mask="(999) 999-9999" />'));
    }

    public function testIsInsideRawTextTagDetectsStyleAndJsonLd()
    {
        $email = 'user@example.com';

        $style = '<style>/* ' . $email . ' */</style>';
        $this->assertTrue($this->helper->isInsideRawTextTag($email, $style, strpos($style, $email)));

        $json_ld = '<script type="application/ld+json">{"email":"' . $email . '"}</script>';
        $this->assertTrue($this->helper->isInsideRawTextTag($email, $json_ld, strpos($json_ld, $email)));

        $noscript = '<noscript><p>' . $email . '</p></noscript>';
        $this->assertTrue($this->helper->isInsideRawTextTag($email, $noscript, strpos($noscript, $email)));
    }

    public function testIsInsideRawTextTagCanBeRestrictedToGivenTags()
    {
        $email = 'user@example.com';
        $content = '<style>/* ' . $email . ' */</style>';

        $this->assertFalse(
            $this->helper->isInsideRawTextTag($email, $content, strpos($content, $email), array('script'))
        );
    }

    public function testIsInsideScriptTagStillMatchesOnlyScripts()
    {
        $email = 'user@example.com';

        $script = '<script>var e="' . $email . '";</script>';
        $this->assertTrue($this->helper->isInsideScriptTag($email, $script, strpos($script, $email)));

        $style = '<style>/* ' . $email . ' */</style>';
        $this->assertFalse($this->helper->isInsideScriptTag($email, $style, strpos($style, $email)));
    }

    public function testRawTextIndexIsRebuiltWhenContentChanges()
    {
        $email = 'user@example.com';

        $first = '<script>var e="' . $email . '";</script>';
        $this->assertTrue($this->helper->isInsideRawTextTag($email, $first, strpos($first, $email)));

        $second = '<p>Write to ' . $email . ' today</p>';
        $this->assertFalse($this->helper->isInsideRawTextTag($email, $second, strpos($second, $email)));
    }
}
