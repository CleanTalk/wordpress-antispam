<?php

namespace ApbctWP\ContactsEncoder;

use Cleantalk\ApbctWP\ContactsEncoder\Shortcodes\EncodeContentSC;
use Cleantalk\ApbctWP\Variables\Cookie;
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
        $this->shortcode = new EncodeContentSC();
        $this->shortcode->register();
        global $apbct;
        $apbct->api_key              = 'tetskey';
        $apbct->data['cookies_type'] = 'native';
        $apbct->saveData();
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
}
