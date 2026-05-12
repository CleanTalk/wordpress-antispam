<?php

namespace ApbctWP\ContactsEncoder;

use Cleantalk\ApbctWP\ContactsEncoder\Shortcodes\EncodeContentSC;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use PHPUnit\Framework\TestCase;

class testEmailEncoderShortCodeEncode extends TestCase
{
    /**
     * @var EncodeContentSC
     */
    private $shortcode;

    protected function setUp(): void
    {
        /**
         * @var  \Cleantalk\ApbctWP\State $apbct
         */
        global $apbct;
        $apbct->api_key              = 'tetskey';
        $apbct->data['cookies_type'] = 'native';
        $apbct->saveData();
        $params = new Params();
        $params->api_key = $apbct->api_key;
        $this->shortcode = new EncodeContentSC($params);
        $this->shortcode->register();
    }

    public function testCallbackEncodesContent()
    {
        $_COOKIE['apbct_email_encoder_passed'] = apbct_get_email_encoder_pass_key();
        Cookie::set('apbct_email_encoder_passed', apbct_get_email_encoder_pass_key());
        $cookie  = Cookie::get('apbct_email_encoder_passed');
        $content = 'Test content';
        $result  = $this->shortcode->callback([], $content, 'apbct_encode_data');

        $this->assertEquals('Test content', $result);
    }

    public function testCallbackReturnsOriginalContentIfCookieSet()
    {
        $_COOKIE['apbct_email_encoder_passed'] = apbct_get_email_encoder_pass_key();
        $content                               = 'Test content';

        $result = $this->shortcode->callback([], $content, 'apbct_encode_data');

        $this->assertEquals('Test content', $result);
    }

    public function testChangeContentBeforeEncoderModifyReplacesShortcodesWithPlaceholders()
    {
        $content = 'Some content with [apbct_encode_data]Test content[/apbct_encode_data]';
        $result  = $this->shortcode->changeContentBeforeEncoderModify($content);

        $this->assertStringContainsString('%%APBCT_SHORT_CODE_INCLUDE_EE_0%%', $result);
        $this->assertArrayHasKey('%%APBCT_SHORT_CODE_INCLUDE_EE_0%%', $this->shortcode->shortcode_replacements);
    }

    public function testChangeContentAfterEncoderModifyRestoresShortcodes()
    {
        $this->shortcode->shortcode_replacements = [
            '%%APBCT_SHORT_CODE_INCLUDE_EE_0%%' => '[apbct_encode_data]Test content[/apbct_encode_data]'
        ];
        $content                                 = '%%APBCT_SHORT_CODE_INCLUDE_EE_0%%';
        $result                                  = $this->shortcode->changeContentAfterEncoderModify($content);

        $this->assertEquals('Test content', $result);
    }

    public function testShortcodeInsideHtmlAttributeIsNotProcessed()
    {
        $content = '<a title="[apbct_encode_data]test[/apbct_encode_data]">X</a>';

        $result = $this->shortcode->changeContentBeforeEncoderModify($content);

        // shortcode should NOT be replaced because it's inside HTML tag
        $this->assertEquals($content, $result);
    }

    public function testShortcodeOutsideHtmlIsProcessed()
    {
        $content = '[apbct_encode_data]Test content[/apbct_encode_data]';

        $result = $this->shortcode->changeContentBeforeEncoderModify($content);

        $this->assertStringContainsString(
            '%%APBCT_SHORT_CODE_INCLUDE_EE_0%%',
            $result
        );

        $this->assertNotEquals($content, $result);
    }

    public function testMultipleShortcodesAreHandled()
    {
        $content =
            '[apbct_encode_data]A[/apbct_encode_data]' .
            ' middle ' .
            '[apbct_encode_data]B[/apbct_encode_data]';

        $result = $this->shortcode->changeContentBeforeEncoderModify($content);

        $this->assertStringContainsString('%%APBCT_SHORT_CODE_INCLUDE_EE_0%%', $result);
        $this->assertStringContainsString('%%APBCT_SHORT_CODE_INCLUDE_EE_1%%', $result);
    }

    public function testHtmlAttributeBreakPayloadDoesNotExplode()
    {
        $content = '<a href="http://x" title="[/apbct_encode_data]">Test</a>';

        $result = $this->shortcode->changeContentBeforeEncoderModify($content);

        // must remain stable, no corruption, no placeholder injection inside tag
        $this->assertStringContainsString('<a', $result);
        $this->assertStringContainsString('</a>', $result);
    }

    public function testOffsetDetectionInsideHtmlTag()
    {
        $content = '<a title="[apbct_encode_data]">X</a>';

        $pos = strpos($content, '[apbct_encode_data]');

        $this->assertTrue(
            $this->shortcode->isOffsetInsideHtmlTag($content, $pos)
        );
    }

    public function testOffsetDetectionOutsideHtmlTag()
    {
        $content = '[apbct_encode_data]test[/apbct_encode_data]';

        $pos = strpos($content, '[apbct_encode_data]');

        $this->assertFalse(
            $this->shortcode->isOffsetInsideHtmlTag($content, $pos)
        );
    }

    public function testShortcodeWithAttributesIsProcessed()
    {
        $content = '[apbct_encode_data mode="blur"]Test[/apbct_encode_data]';

        $result = $this->shortcode->changeContentBeforeEncoderModify($content);

        $this->assertStringContainsString(
            '%%APBCT_SHORT_CODE_INCLUDE_EE_0%%',
            $result
        );

        $this->assertNotEquals($content, $result);
    }

    public function testShortcodeWithAttributesIsDetectedInHtmlContext()
    {
        $content = '<a title="[apbct_encode_data mode=\"blur\"]test[/apbct_encode_data]">X</a>';

        $result = $this->shortcode->changeContentBeforeEncoderModify($content);

        // must be blocked due to HTML attribute context
        $this->assertEquals($content, $result);
    }

    public function testMixedShortcodesSafeAndUnsafe()
    {
        $content =
            '[apbct_encode_data]SAFE[/apbct_encode_data]' .
            '<a title="[apbct_encode_data]BAD[/apbct_encode_data]">' .
            'X</a>';

        $result = $this->shortcode->changeContentBeforeEncoderModify($content);

        // because of current design: full block is skipped if ANY HTML-unsafe shortcode exists
        $this->assertEquals($content, $result);
    }

    public function testPlaceholderNeverAppearsInsideHtmlAttribute()
    {
        $content = '<a title="[apbct_encode_data]Test[/apbct_encode_data]">X</a>';

        $result = $this->shortcode->changeContentBeforeEncoderModify($content);

        $this->assertStringNotContainsString('%%APBCT_SHORT_CODE_INCLUDE_EE_0%%', $result);
    }

    public function testCallbackEscapesReplacingText()
    {
        $result = $this->shortcode->callback(
            ['replacing_text' => '<script>alert(1)</script>'],
            'content',
            'apbct_encode_data'
        );

        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testRestoreIntegrityWithMultiplePlaceholders()
    {
        $this->shortcode->shortcode_replacements = [
            '%%APBCT_SHORT_CODE_INCLUDE_EE_0%%' => '[apbct_encode_data]A[/apbct_encode_data]',
            '%%APBCT_SHORT_CODE_INCLUDE_EE_1%%' => '[apbct_encode_data]B[/apbct_encode_data]',
        ];

        $content = '%%APBCT_SHORT_CODE_INCLUDE_EE_0%% and %%APBCT_SHORT_CODE_INCLUDE_EE_1%%';

        $result = $this->shortcode->changeContentAfterEncoderModify($content);

        $this->assertStringContainsString('A', $result);
        $this->assertStringContainsString('B', $result);
    }

}
