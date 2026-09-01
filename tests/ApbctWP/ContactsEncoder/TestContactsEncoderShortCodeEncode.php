<?php

namespace ApbctWP\ContactsEncoder;

use Cleantalk\ApbctWP\ContactsEncoder\Shortcodes\EncodeContentSC;
use Cleantalk\ApbctWP\ContactsEncoder\Shortcodes\ShortCodesService;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use PHPUnit\Framework\TestCase;

class testEmailEncoderShortCodeEncode extends TestCase
{
    /**
     * @var EncodeContentSC
     */
    private $shortcode;

    /**
     * @var ShortCodesService
     */
    private $shortcodes_service;

    protected function setUp(): void
    {
        /**
         * @var  \Cleantalk\ApbctWP\State $apbct
         */
        global $apbct;
        $apbct->api_key              = 'tetskey';
        $apbct->data['key_is_ok']    = true;
        $apbct->data['cookies_type'] = 'native';
        $apbct->saveData();
        $params = new Params();
        $params->api_key = $apbct->api_key;
        $this->shortcode = new EncodeContentSC($params);
        $this->shortcodes_service = new ShortCodesService($params);
        $this->shortcode->register();
        $this->clearDecoderPassedCookie();
    }

    protected function tearDown(): void
    {
        $this->clearDecoderPassedCookie();
        parent::tearDown();
    }

    private function clearDecoderPassedCookie(): void
    {
        $cookie_name = apbct__get_cookie_prefix() . 'apbct_email_encoder_passed';
        unset($_COOKIE[$cookie_name]);

        $cookie_instance = Cookie::getInstance();
        $ref = new \ReflectionClass($cookie_instance);
        while ($ref) {
            if ($ref->hasProperty('variables')) {
                $prop = $ref->getProperty('variables');
                $prop->setAccessible(true);
                $variables = $prop->getValue($cookie_instance);
                unset($variables[$cookie_name]);
                $prop->setValue($cookie_instance, $variables);
                break;
            }
            $ref = $ref->getParentClass();
        }
    }

    private function setDecoderPassedCookie(): void
    {
        global $apbct;
        $apbct->data['key_is_ok'] = true;
        $apbct->data['cookies_type'] = 'native';
        $pass_key = apbct_get_email_encoder_pass_key();
        $cookie_name = apbct__get_cookie_prefix() . 'apbct_email_encoder_passed';
        $_COOKIE[$cookie_name] = $pass_key;
        $this->clearDecoderPassedCookie();
        $_COOKIE[$cookie_name] = $pass_key;
        Cookie::set('apbct_email_encoder_passed', $pass_key);
    }

    public function testCallbackEncodesContent()
    {
        $this->setDecoderPassedCookie();
        $cookie  = Cookie::get('apbct_email_encoder_passed');
        $content = 'Test content';
        $result  = $this->shortcode->callback([], $content, 'apbct_encode_data');

        $this->assertEquals('Test content', $result);
    }

    public function testCallbackReturnsOriginalContentIfCookieSet()
    {
        $this->setDecoderPassedCookie();
        $content = 'Test content';

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

    public function testChangeContentBeforeEncoderModifyUsesPlaceholdersWhenDecoderCookieSet()
    {
        $this->setDecoderPassedCookie();

        $content = 'Some content with [apbct_encode_data]Test content[/apbct_encode_data]';
        $result  = $this->shortcode->changeContentBeforeEncoderModify($content);

        $this->assertStringContainsString('%%APBCT_SHORT_CODE_INCLUDE_EE_0%%', $result);
        $this->assertArrayHasKey('%%APBCT_SHORT_CODE_INCLUDE_EE_0%%', $this->shortcode->shortcode_replacements);
    }

    public function testChangeContentBeforeEncoderModifyUsesPlaceholdersWhenGlobalEmailEncodingDisabled()
    {
        $params = new Params();
        $params->api_key = 'testkey';
        $params->do_encode_emails = 0;
        $params->do_encode_phones = 0;
        $shortcode = new EncodeContentSC($params);

        $content = '<p>[apbct_encode_data]Test content[/apbct_encode_data]</p>';
        $result  = $shortcode->changeContentBeforeEncoderModify($content);

        $this->assertStringContainsString('%%APBCT_SHORT_CODE_INCLUDE_EE_0%%', $result);
        $this->assertStringContainsString('<p>', $result);
    }

    public function testChangeContentAfterEncoderModifyRestoresShortcodes()
    {
        $this->shortcode->shortcode_replacements = [
            '%%APBCT_SHORT_CODE_INCLUDE_EE_0%%' => '[apbct_encode_data]Test content[/apbct_encode_data]'
        ];
        $content                                 = '%%APBCT_SHORT_CODE_INCLUDE_EE_0%%';
        $result                                  = $this->shortcode->changeContentAfterEncoderModify($content);

        $this->assertStringNotContainsString('[apbct_encode_data]', $result);
        $this->assertStringNotContainsString('%%APBCT_SHORT_CODE_INCLUDE_EE_0%%', $result);
        $this->assertStringContainsString('apbct-email-encoder', $result);
    }

    public function testChangeContentAfterEncoderModifyRestoresShortcodesWithoutEncodingWhenDecoderCookieSet()
    {
        $this->setDecoderPassedCookie();

        $this->shortcode->shortcode_replacements = [
            '%%APBCT_SHORT_CODE_INCLUDE_EE_0%%' => '[apbct_encode_data]Test content[/apbct_encode_data]'
        ];
        $content = '%%APBCT_SHORT_CODE_INCLUDE_EE_0%%';
        $result  = $this->shortcode->changeContentAfterEncoderModify($content);

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

    public function testNestedShortcodesAreNotExecutedViaDoShortcode()
    {
        add_shortcode('apbct_test_arbitrary_sc', static function () {
            return 'ARBITRARY_SHORTCODE_EXECUTED';
        });

        try {
            $content = '[apbct_encode_data][apbct_test_arbitrary_sc][/apbct_encode_data]';
            $result  = $this->shortcode->changeContentAfterEncoderModify($content);

            $this->assertStringNotContainsString('ARBITRARY_SHORTCODE_EXECUTED', $result);
            $this->assertStringContainsString('apbct-email-encoder', $result);
            $this->assertStringNotContainsString('[apbct_test_arbitrary_sc]', $result);
        } finally {
            remove_shortcode('apbct_test_arbitrary_sc');
        }
    }

    public function testUnclosedEncodeDataTagDoesNotExecuteOtherShortcodes()
    {
        add_shortcode('apbct_test_arbitrary_sc', static function () {
            return 'ARBITRARY_SHORTCODE_EXECUTED';
        });

        try {
            $content = '[apbct_encode_data][apbct_test_arbitrary_sc]';
            $result  = $this->shortcode->changeContentAfterEncoderModify($content);

            $this->assertStringNotContainsString('ARBITRARY_SHORTCODE_EXECUTED', $result);
            $this->assertEquals($content, $result);
        } finally {
            remove_shortcode('apbct_test_arbitrary_sc');
        }
    }

    public function testAdjacentShortcodesOutsideEncodeDataAreNotExecuted()
    {
        add_shortcode('apbct_test_arbitrary_sc', static function () {
            return 'ARBITRARY_SHORTCODE_EXECUTED';
        });

        try {
            $content = '[apbct_encode_data]safe@example.com[/apbct_encode_data][apbct_test_arbitrary_sc]';
            $result  = $this->shortcode->changeContentAfterEncoderModify($content);

            $this->assertStringNotContainsString('ARBITRARY_SHORTCODE_EXECUTED', $result);
            $this->assertStringContainsString('[apbct_test_arbitrary_sc]', $result);
        } finally {
            remove_shortcode('apbct_test_arbitrary_sc');
        }
    }

    public function testBufferModeDoesNotAbsorbCommentsFromEncodeDataTags()
    {
        global $apbct;

        $previous_buffer_setting = $apbct->settings['data__email_decoder_buffer'];
        $apbct->settings['data__email_decoder_buffer'] = true;

        $html = '<div id="comments" class="comments-area"><ol class="comment-list">'
            . '<li class="comment"><div class="comment-content">[apbct_encode_data]</div></li>'
            . '<li class="comment"><div class="comment-content">Second comment text</div></li>'
            . '<li class="comment"><div class="comment-content">[apbct_encode_data]z[/apbct_encode_data][/apbct_encode_data]</div></li>'
            . '</ol></div>';

        try {
            $buffer = $this->shortcodes_service->modifyBufferBefore($html);
            $buffer = apbctGetContactsEncoder()->modifyContent($buffer);
            $buffer = $this->shortcodes_service->modifyBufferAfter($buffer);

            $this->assertStringContainsString('Second comment text', $buffer);
            $this->assertStringContainsString('[apbct_encode_data]', $buffer);
            $this->assertStringNotContainsString('apbct-email-encoder', $buffer);
        } finally {
            $apbct->settings['data__email_decoder_buffer'] = $previous_buffer_setting;
        }
    }

}
