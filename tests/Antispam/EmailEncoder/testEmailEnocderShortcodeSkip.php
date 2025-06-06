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

        $this->assertEquals('##SCE_0##', $result);
    }

    public function testChangeContentBeforeEncoderModifyExecutesCallback()
    {
        $content = 'Some content with [apbct_skip_encoding]Test content[/apbct_skip_encoding]';
        $result = $this->shortcode->changeContentBeforeEncoderModify($content);

        $this->assertStringContainsString('Some content with ##SCE_0##', $result);
    }

    public function testClearTitleContentFromShortcodeConstruction()
    {
        $content = 'Some content with [apbct_skip_encoding]Test content[/apbct_skip_encoding]';
        $result = $this->shortcode->clearTitleContentFromShortcodeConstruction($content);

        $this->assertEquals('Some content with Test content', $result);
    }

    public function testClearTitleContentFromShortcodeConstructionSkippingEmail()
    {
        $content = 'Hah, there is email example@exmple.com and some content with [apbct_skip_encoding]Test content[/apbct_skip_encoding]';
        $result = $this->shortcode->clearTitleContentFromShortcodeConstruction($content);

        $this->assertEquals('Hah, there is email example@exmple.com and some content with Test content', $result);
    }

    public function testTitleContentWithEmailSkippedAndUnskipped()
    {
        $origin_content = 'Hah, there is email example@exmple.com and some content with [apbct_skip_encoding]Test content[/apbct_skip_encoding]';
        $content = $origin_content;
        //emulate hook before
        $content = $this->shortcode->changeContentBeforeEncoderModify($content);
        //do common modifying
        $content = \Cleantalk\ApbctWP\Antispam\EmailEncoder::getInstance()->modifyContent($content);
        //emulate hook after
        $content = $this->shortcode->changeContentAfterEncoderModify($content);

        $this->assertStringNotContainsString( 'example@exmple.com', $content);
        $this->assertStringNotContainsString( 'apbct_skip_encoding', $content);
        $this->assertStringContainsString( 'with Test content', $content);
    }
}
