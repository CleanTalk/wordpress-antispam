<?php

use PHPUnit\Framework\TestCase;
use Cleantalk\Antispam\EmailEncoder\Shortcodes\SkipContentFromEncodeSC;

class testEmailEnocderShortcodeSkip extends TestCase
{
    /**
     * @var SkipContentFromEncodeSC
     */
    private $shortcode;

    protected function setUp(): void
    {

        $this->shortcode = new SkipContentFromEncodeSC();
        $this->shortcode->register();
    }

    public function testCallbackWrapsContentInExclusionWrapper()
    {
        $content = 'Test content';
        $result = $this->shortcode->callback([], $content, 'apbct_skip_encoding');

        $this->assertEquals('%%APBCT_SHORT_CODE_EXCLUDE_EE%%Test content%%APBCT_SHORT_CODE_EXCLUDE_EE%%', $result);
    }

    public function testChangeContentBeforeEncoderModifyExecutesCallback()
    {
        $content = 'Some content with [apbct_skip_encoding]Test content[/apbct_skip_encoding]';
        $result = $this->shortcode->changeContentBeforeEncoderModify($content);

        $this->assertStringContainsString('%%APBCT_SHORT_CODE_EXCLUDE_EE%%Test content%%APBCT_SHORT_CODE_EXCLUDE_EE%%', $result);
    }

    public function testChangeContentAfterEncoderModifyRemovesExclusionWrapper()
    {
        $content = '%%APBCT_SHORT_CODE_EXCLUDE_EE%%Test content%%APBCT_SHORT_CODE_EXCLUDE_EE%%';
        $result = $this->shortcode->changeContentAfterEncoderModify($content);

        $this->assertEquals('Test content', $result);
    }

    public function testIsContentExcludedReturnsTrueForExcludedContent()
    {
        $content = '%%APBCT_SHORT_CODE_EXCLUDE_EE%%Test content%%APBCT_SHORT_CODE_EXCLUDE_EE%%';
        $result = $this->shortcode->isContentExcluded($content);

        $this->assertEquals(1, $result);
    }

    public function testIsContentExcludedReturnsFalseForNonExcludedContent()
    {
        $content = 'Test content';
        $result = $this->shortcode->isContentExcluded($content);

        $this->assertEquals(0, $result);
    }

    public function testClearTitleContentFromShortcodeConstruction()
    {
        $content = 'Some content with [apbct_skip_encoding]Test content[/apbct_skip_encoding]';
        $result = $this->shortcode->clearTitleContentFromShortcodeConstruction($content);

        $this->assertEquals('Some content with Test content', $result);
    }
}
