<?php

namespace ApbctWP\ContactsEncoder;

use Cleantalk\ApbctWP\ContactsEncoder\ContactsEncoder;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use PHPUnit\Framework\TestCase;

class TestEmailEncoder extends TestCase
{

    /**
     * @var ContactsEncoder
     */
    private $contacts_encoder;

    private $plain_text = 'This is a plain text';

    public function setUp(): void
    {
        global $apbct;
        $apbct->api_key         = 'testapikey';
        $this->contacts_encoder = apbctGetContactsEncoder();
        $this->clearDecoderPassedCookie();
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

    private function setDecoderPassedCookie(): string
    {
        global $apbct;
        $apbct->data['key_is_ok'] = true;
        $apbct->data['cookies_type'] = 'native';
        $pass_key = apbct_get_email_encoder_pass_key();
        $cookie_name = apbct__get_cookie_prefix() . 'apbct_email_encoder_passed';
        $this->clearDecoderPassedCookie();
        $_COOKIE[$cookie_name] = $pass_key;
        Cookie::set('apbct_email_encoder_passed', $pass_key);

        return $pass_key;
    }

    public function testPlainTextEncodeDecodeSSL()
    {
        $encoded_plain = $this->contacts_encoder->encoder->encodeString($this->plain_text);
        $this->assertNotEmpty($encoded_plain);
        $this->assertIsString($encoded_plain);
        $decoded_entity = $this->contacts_encoder->encoder->decodeString($encoded_plain);
        $this->assertNotEmpty($decoded_entity);
        $this->assertIsString($decoded_entity);
        global $apbct;
        $this->assertFalse($apbct->isHaveErrors());
        $this->assertEquals($decoded_entity, $this->plain_text);
    }

    public function testPlainTextDecodeError()
    {
        $decoded_entity = $this->contacts_encoder->encoder->decodeString('asdas121312');
        $this->assertEmpty($decoded_entity);
        $this->assertIsString($decoded_entity);
        /**
         * @var State $apbct
         */
        global $apbct;
        $this->assertTrue($apbct->isHaveErrors());
        $this->assertTrue($apbct->errorExists('email_encoder'), $this->plain_text);
        $this->assertStringContainsString( 'decrypt attempts failed', $apbct->errors['email_encoder'][0]['error']);
        $apbct->errorDeleteAll();
    }

    public function testEncodeErrors()
    {
        /**
         * @var State $apbct
         */
        global $apbct;
        $null = null;

        $encoded_plain = $this->contacts_encoder->encoder->encodeString($null);
        $this->assertNull($null);
        $this->assertTrue($apbct->isHaveErrors());
        $this->assertArrayHasKey('email_encoder', $apbct->errors);
        $this->assertIsArray($apbct->errors['email_encoder']);
        $this->assertIsArray($apbct->errors['email_encoder'][0]);
        $this->assertArrayHasKey('error', $apbct->errors['email_encoder'][0]);
        $this->assertStringContainsString('is not string', $apbct->errors['email_encoder'][0]['error']);
        $this->assertStringContainsString('TYPE_NULL', $apbct->errors['email_encoder'][0]['error']);
        $apbct->errorDeleteAll();

        $empty_string = '';

        $encoded_plain = $this->contacts_encoder->encoder->encodeString($empty_string);
        $this->assertEmpty($empty_string);
        $this->assertTrue($apbct->isHaveErrors());
        $this->assertArrayHasKey('email_encoder', $apbct->errors);
        $this->assertIsArray($apbct->errors['email_encoder']);
        $this->assertIsArray($apbct->errors['email_encoder'][0]);
        $this->assertArrayHasKey('error', $apbct->errors['email_encoder'][0]);
        $this->assertStringContainsString('Empty plain string', $apbct->errors['email_encoder'][0]['error']);
        $this->assertStringContainsString('EMPTY_STRING', $apbct->errors['email_encoder'][0]['error']);
        $apbct->errorDeleteAll(true);
    }

    public function testPlainTextEncodeDecodeBase()
    {
        $encoded_plain                = $this->contacts_encoder->ignoreOpenSSLMode()->encoder->encodeString($this->plain_text);
        $this->assertNotEmpty($encoded_plain);
        $this->assertIsString($encoded_plain);
        // make sure that b64 fired instead of ssl
        $decoded_entity = $this->contacts_encoder->encoder->decodeString($encoded_plain);
        $this->assertNotEmpty($decoded_entity);
        $this->assertIsString($decoded_entity);
        $this->assertEquals($this->plain_text, $decoded_entity);
        global $apbct;
        $this->assertFalse($apbct->isHaveErrors());
        $this->assertEquals($decoded_entity, $this->plain_text);
    }

    public function testEmailObfuscation()
    {
        $test_array = array(
            ['alex@comon.com', 'al**@***on.com'],
            ['meya.com', 'meya.com'],
            ['meya.com@', 'meya.com@'],
            ['alexander@ya.com', 'al*******@**.com'],
            ['me@ya.com', '**@**.com'],
            ['danger.not.of.mine@yandexooobfuscaaated.com', 'da****************@******************ed.com'],
            ['@@@', '@@@'],
            ['@.@.@', '@.@.@'],
        );
        foreach ($test_array as $item) {
            $result = $this->contacts_encoder->getObfuscatedEmailString($item[0]);
            $this->assertEquals($item[1],$result);
        }
    }

    public function testEmailObfuscationModes()
    {
        global $apbct;
        $test_email = 'alex@comon.com';
        $test_email_obf = 'al\*\*\@\*\*\*on\.com';
        $test_email_replace = 'REPLACEBYME';
        $regexp_for_blur = '/.+data-original-string=[\s\S]+apbct-email-encoder[\s\S]+browser\.[\"\']>[\s\S]+apbct-blur[\s\S]+\*?apbct-blur[\s\S]+\*+<\/span>[\s\S]+<\/span>/';
        $regexp_for_obfuscation = '/.+data-original-string=[\s\S]+apbct-email-encoder[\s\S]+browser\.[\"\']>+' . $test_email_obf . '/';
        $regexp_for_replace = '/.+data-original-string=[\s\S]+apbct-email-encoder[\s\S]+browser\.[\"\']>.+' . $test_email_replace . '/';

        $apbct->settings['data__email_decoder_obfuscation_mode'] = Params::OBFUSCATION_MODE_BLUR;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance(); // Need to rebuild the object after the settings changed
        $this->contacts_encoder = apbctGetContactsEncoder();
        $result = $this->contacts_encoder->modifyContent($test_email);
        $this->assertNotRegExp($regexp_for_obfuscation, $result);
        $this->assertNotRegExp($regexp_for_replace, $result);
        $this->assertRegExp($regexp_for_blur, $result);

        $apbct->settings['data__email_decoder_obfuscation_mode'] = Params::OBFUSCATION_MODE_OBFUSCATE;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance(); // Need to rebuild the object after the settings changed
        $this->contacts_encoder = apbctGetContactsEncoder();
        $result = $this->contacts_encoder->modifyContent($test_email);
        $this->assertNotRegExp($regexp_for_replace, $result);
        $this->assertNotRegExp($regexp_for_blur, $result);
        $this->assertRegExp($regexp_for_obfuscation, $result);

        $apbct->settings['data__email_decoder_obfuscation_mode'] = Params::OBFUSCATION_MODE_REPLACE;
        $apbct->settings['data__email_decoder_obfuscation_custom_text'] = $test_email_replace;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance(); // Need to rebuild the object after the settings changed
        $this->contacts_encoder = apbctGetContactsEncoder();
        $result = $this->contacts_encoder->modifyContent($test_email);
        $this->assertNotRegExp($regexp_for_blur, $result);
        $this->assertNotRegExp($regexp_for_obfuscation, $result);
        $this->assertRegExp($regexp_for_replace, $result);
    }

    /**
     * Emails inside <option> must stay plain so select/Fluent Forms submit valid values (#53406).
     */
    public function testModifyContentSkipsEmailsInsideOptionTags()
    {
        $email_in_option = 'dropdown@example.com';
        $email_outside = 'public@example.com';
        $content = '<p>Contact ' . $email_outside . '</p>'
            . '<select name="department">'
            . '<option value="' . $email_in_option . '">' . $email_in_option . '</option>'
            . '</select>';

        $result = $this->contacts_encoder->modifyContent($content);

        $this->assertStringContainsString(
            '<option value="' . $email_in_option . '">' . $email_in_option . '</option>',
            $result
        );
        $this->assertStringNotContainsString('%%APBCT_OPTION_SKIP_', $result);
        $this->assertStringNotContainsString($email_outside, $result);
        $this->assertStringContainsString('apbct-email-encoder', $result);
    }

    public function testEncodingPhoneNumbers()
    {
        global $apbct;

        $apbct->settings['data__email_decoder_obfuscation_mode'] = Params::OBFUSCATION_MODE_BLUR;
        $apbct->settings['data__email_decoder_encode_phone_numbers'] = 1;
        $apbct->saveSettings();

        $this->contacts_encoder->dropInstance(); // Need to rebuild the object after the settings changed
        $this->contacts_encoder = apbctGetContactsEncoder();

        $test_stack = array(
            '+442071838750',
            '+44 20 7123 4567',
            '+12025551234',
            '+1 (234) 567-8901',
            '+49 30 1234567',
            '+33 1 23 45 67 89',
            '+7 (123) 456-78-90',
            '+7 123 456 78 90',
            '+71234567890',
            '(999) 321-1233',
            '+1.775.301.1130',
            '+49.30.123.4567',
            '+49.30.1234567',
        );

        $test_stack_skip_to = array(
            '192.168.2.1',
            'prefix.1.775.301.1130.postfix',
            'prefix.+1.225.201.113.postfix',
            '+1.225.201.112ss',
            '+1.225.201.112',
            '8.168.2.1',
            '81.234.56.78',
            '71234567890',
            '+7413033',
            '+7(413)033',
            '+7 (413) 03 3',
            '+49.30.1.234567',
        );

        $regexp_for_blur = '/.+data-original-string=[\s\S]+apbct-email-encoder[\s\S]+browser\.[\"\']>[\s\S]+apbct-blur[\s\S]+\*?\*+<\/span>[\s\S]+/';

        foreach ($test_stack as $phone) {
            $result = $this->contacts_encoder->modifyContent($phone);
            $this->assertNotEmpty($result);
            $this->assertStringNotContainsString($phone, $result);
            $this->assertRegExp($regexp_for_blur, $result);
        }

        foreach ($test_stack_skip_to as $phone) {
            $result = $this->contacts_encoder->modifyContent($phone);
            $this->assertNotEmpty($result);
            $this->assertEquals($phone, $result);
        }
    }

    public function testPhoneEncodingSetting()
    {
        global $apbct;

        $test_tel_origin = 'Lorem ipsum bla bla +17776663322';
        $test_tel_obfuscated = '\+1777\*\*\*\*\*22';
        $regexp_for_obfuscation = '/.+data-original-string=[\s\S]+apbct-email-encoder[\s\S]+browser\.[\"\']>+' . $test_tel_obfuscated . '/';

        $apbct->settings['data__email_decoder_obfuscation_mode'] = Params::OBFUSCATION_MODE_OBFUSCATE;
        $apbct->settings['data__email_decoder_encode_phone_numbers'] = 1;
        $apbct->saveSettings();

        $this->contacts_encoder->dropInstance(); // Need to rebuild the object after the settings changed
        $this->contacts_encoder = apbctGetContactsEncoder();

        $result = $this->contacts_encoder->modifyContent($test_tel_origin);
        $this->assertRegExp($regexp_for_obfuscation, $result);

        $apbct->settings['data__email_decoder_encode_phone_numbers'] = 0;
        $apbct->saveSettings();

        $this->contacts_encoder->dropInstance(); // Need to rebuild the object after the settings changed
        $this->contacts_encoder = apbctGetContactsEncoder();

        $result = $this->contacts_encoder->modifyContent($test_tel_origin);
        $this->assertEquals($test_tel_origin, $result);
    }

    public function testGetPhonesEncodingLongDescription()
    {
        $description = ContactsEncoder::getPhonesEncodingLongDescription();
        $this->assertIsString($description);
        $this->assertStringStartsWith('<', $description);
        $this->assertStringEndsWith('>', $description);
    }

    public function testModifyBuffer()
    {
        global $apbct;
        $test_string = 'test string with email test@example.com';
        $apbct->buffer = $test_string;

        $this->contacts_encoder->modifyBuffer();

        $this->assertNotEquals($apbct->buffer, $test_string);
    }

    public function testModifyBufferPreservesParagraphWrapperAroundShortcode()
    {
        global $apbct;

        $apbct->settings['data__email_decoder_buffer'] = true;
        $apbct->settings['data__email_decoder_encode_email_addresses'] = 1;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance();
        $this->contacts_encoder = apbctGetContactsEncoder();
        $this->contacts_encoder->runEncoding();

        $apbct->buffer = '<p>[apbct_encode_data]any data to encode[/apbct_encode_data]</p>';
        $this->contacts_encoder->modifyBuffer();

        $this->assertStringStartsWith('<p>', $apbct->buffer);
        $this->assertStringContainsString('</p>', $apbct->buffer);
        $this->assertStringNotContainsString('[apbct_encode_data]', $apbct->buffer);
        $this->assertStringContainsString('apbct-email-encoder', $apbct->buffer);
    }

    public function testModifyBufferPreservesParagraphWrapperAroundPlainEmailAndShortcode()
    {
        global $apbct;

        $apbct->settings['data__email_decoder_buffer'] = true;
        $apbct->settings['data__email_decoder_encode_email_addresses'] = 1;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance();
        $this->contacts_encoder = apbctGetContactsEncoder();
        $this->contacts_encoder->runEncoding();

        $apbct->buffer =
            '<p>s@cleantalk.org</p>' .
            '<p>[apbct_encode_data]any data to encode[/apbct_encode_data]</p>' .
            '<p>[apbct_encode_data]plain shortcode line[/apbct_encode_data]</p>';

        $this->contacts_encoder->modifyBuffer();

        $this->assertEquals(3, substr_count($apbct->buffer, '<p>'));
        $this->assertEquals(3, substr_count($apbct->buffer, '</p>'));
        $this->assertStringNotContainsString('[apbct_encode_data]', $apbct->buffer);
        $this->assertStringContainsString('apbct-email-encoder', $apbct->buffer);
    }

    public function testModifyBufferPreservesWpGridBuilderInlineScript()
    {
        global $apbct;

        $apbct->settings['data__email_decoder_buffer'] = true;
        $apbct->settings['data__email_decoder_encode_email_addresses'] = 1;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance();
        $this->contacts_encoder = apbctGetContactsEncoder();
        $this->contacts_encoder->runEncoding();

        $wpgb_script =
            '<script>(function(){var wpgb=WP_Grid_Builder.instance(1);if(!wpgb.init){return}wpgb.init()})();</script>';
        $wpgb_script_src =
            '<script src="https://example.com/wp-content/plugins/wp-grid-builder/public/js/layout.js?ver=2.3.5"></script>';
        $wpgb_noscript =
            '<noscript><style>.wp-grid-builder .wpgb-card.wpgb-card-hidden .wpgb-card-wrapper{opacity:1!important}</style></noscript>';
        $apbct->buffer =
            '<head><title>test</title></head><body>' .
            '<p>contact@example.com</p>' .
            $wpgb_script .
            $wpgb_script_src .
            $wpgb_noscript .
            '<style>.wpgb-card-wrapper{opacity:1!important}</style></body>';

        $this->contacts_encoder->modifyBuffer();

        $this->assertStringContainsString($wpgb_script, $apbct->buffer);
        $this->assertStringContainsString($wpgb_script_src, $apbct->buffer);
        $this->assertStringContainsString($wpgb_noscript, $apbct->buffer);
        $this->assertStringContainsString('.wpgb-card-wrapper{opacity:1!important}', $apbct->buffer);
        $this->assertStringContainsString('apbct-wpgb-opacity-fix', $apbct->buffer);
        $this->assertStringContainsString('wpgb-card-media-thumbnail', $apbct->buffer);
        $this->assertStringContainsString('apbct-wpgb-styles-css', $apbct->buffer);
        $this->assertStringContainsString('wp-grid-builder/public/css/style.css?ver=2.3.5', $apbct->buffer);
        $this->assertStringContainsString('apbct-email-encoder', $apbct->buffer);
        $this->assertStringNotContainsString('contact@example.com', $apbct->buffer);
    }

    public function testModifyBufferPreservesWpGridBuilderCardCss()
    {
        global $apbct;

        $apbct->settings['data__email_decoder_buffer'] = true;
        $apbct->settings['data__email_decoder_encode_email_addresses'] = 1;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance();
        $this->contacts_encoder = apbctGetContactsEncoder();
        $this->contacts_encoder->runEncoding();

        $card_css =
            '<style id="wpgb-styles-inline-css">.wpgb-card-1 .wpgb-block-5{position:absolute;top:12px;left:12px;background:#fff;padding:4px 8px}</style>';
        $apbct->buffer =
            '<head><title>test</title>' . $card_css . '</head><body>' .
            '<div class="wp-grid-builder wpgb-grid-20"><div class="wpgb-block-5">35+vat</div></div>' .
            '<p>contact@example.com</p></body>';

        $this->contacts_encoder->modifyBuffer();

        $this->assertStringContainsString('.wpgb-card-1 .wpgb-block-5{position:absolute', $apbct->buffer);
        $this->assertStringNotContainsString('contact@example.com', $apbct->buffer);
        $this->assertStringNotContainsString('apbct-wpgb-card-inline-css', $apbct->buffer);
    }

    public function testModifyBufferPreservesWpGridBuilderMultiDigitCardCss()
    {
        global $apbct;

        $apbct->settings['data__email_decoder_buffer'] = true;
        $apbct->settings['data__email_decoder_encode_email_addresses'] = 1;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance();
        $this->contacts_encoder = apbctGetContactsEncoder();
        $this->contacts_encoder->runEncoding();

        $card_css =
            '<style id="wpgb-styles-inline-css">.wpgb-card-12 .wpgb-block-10{background:#fff;padding:4px 8px}</style>';
        $apbct->buffer =
            '<head><title>test</title>' . $card_css . '</head><body>' .
            '<div class="wp-grid-builder wpgb-grid-20"><div class="wpgb-block-10">10+vat</div></div>' .
            '<p>contact@example.com</p></body>';

        $this->contacts_encoder->modifyBuffer();

        $this->assertStringContainsString('.wpgb-card-12 .wpgb-block-10{background:#fff', $apbct->buffer);
        $this->assertStringNotContainsString('contact@example.com', $apbct->buffer);
    }

    public function testModifyBufferInjectsCapturedWpGridBuilderCardCss()
    {
        $card_css = '.wpgb-card-1 .wpgb-block-5{position:absolute;top:12px;left:12px;background:#fff;padding:4px 8px}';
        $integration = $this->contacts_encoder->getGridBuilderIntegration();
        $integration->setCardInlineCssForTests($card_css);

        $result = $integration->appendFix(
            '<head><title>test</title></head><body><div class="wp-grid-builder wpgb-grid-20"></div></body>'
        );

        $this->assertStringContainsString('apbct-wpgb-card-inline-css', $result);
        $this->assertStringContainsString($card_css, $result);
    }

    public function testModifyBufferDoesNotInjectGenericWpGridBuilderHeadCssAsCardCss()
    {
        global $apbct;

        $apbct->settings['data__email_decoder_buffer'] = true;
        $apbct->settings['data__email_decoder_encode_email_addresses'] = 1;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance();
        $this->contacts_encoder = apbctGetContactsEncoder();
        $this->contacts_encoder->runEncoding();

        $generic_css =
            '.wp-grid-builder:not(.wpgb-template),.wpgb-facet{opacity:0.01}';
        $apbct->buffer =
            '<head><title>test</title>' .
            '<style id="wpgb-head-inline-css">' . $generic_css . '</style></head><body>' .
            '<div class="wp-grid-builder wpgb-grid-20"><div class="wpgb-block-5">35+vat</div></div>' .
            '<p>contact@example.com</p></body>';

        $this->contacts_encoder->modifyBuffer();

        $this->assertStringNotContainsString('apbct-wpgb-card-inline-css', $apbct->buffer);
        $this->assertStringNotContainsString('contact@example.com', $apbct->buffer);
    }

    public function testAppendWpGridBuilderFixInjectsMissingCardStylesheetLink()
    {
        $card_css_url = 'https://example.com/wp-content/uploads/wp-grid-builder/styles/card-1.min.css';
        $integration = $this->contacts_encoder->getGridBuilderIntegration();
        $integration->setStylesheetLinksForTests(array('wpgb-card-1' => $card_css_url));

        $result = $integration->appendFix(
            '<head><title>test</title></head><body><div class="wp-grid-builder wpgb-grid-20"></div></body>'
        );

        $this->assertStringContainsString('apbct-wpgb-card-css-wpgb-card-1', $result);
        $this->assertStringContainsString($card_css_url, $result);
    }

    public function testAppendWpGridBuilderFixReplacesInvalidCardCssPlaceholder()
    {
        $card_css = '.wpgb-card-1 .wpgb-block-5{background:#fff;padding:4px 8px}';
        $integration = $this->contacts_encoder->getGridBuilderIntegration();
        $integration->setCardInlineCssForTests($card_css);

        $result = $integration->appendFix(
            '<head><title>test</title>' .
            '<style id="apbct-wpgb-card-inline-css">.wp-grid-builder:not(.wpgb-template){opacity:0.01}</style>' .
            '</head><body><div class="wp-grid-builder wpgb-grid-20"></div></body>'
        );

        $this->assertEquals(1, substr_count($result, 'apbct-wpgb-card-inline-css'));
        $this->assertStringContainsString($card_css, $result);
        $this->assertStringNotContainsString('opacity:0.01', $result);
    }

    public function testReadCssFromSrcRejectsTraversalAndNonCssPaths()
    {
        $read_css = $this->invokeReadCssFromSrc(
            'https://example.org/wp-content/../wp-config.php'
        );

        $this->assertSame('', $read_css);

        $read_css = $this->invokeReadCssFromSrc(
            'https://example.org/wp-content/plugins/cleantalk-spam-protect/cleantalk.php'
        );

        $this->assertSame('', $read_css);
    }

    public function testReadCssFromSrcReadsValidCssUnderAbspath()
    {
        $fixture_path = '/wp-content/plugins/cleantalk-spam-protect/tests/fixtures/wpgb-card-fixture.css';
        $read_css = $this->invokeReadCssFromSrc('https://example.org' . $fixture_path);

        $this->assertStringContainsString('.wpgb-card-1 .wpgb-block-5{background:#fff', $read_css);
    }

    public function testModifyBufferSkipsEncodingWhenDecoderCookieSet()
    {
        global $apbct;

        $pass_key = $this->setDecoderPassedCookie();

        $apbct->settings['data__email_decoder_buffer'] = true;
        $apbct->settings['data__email_decoder_encode_email_addresses'] = 1;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance();
        $this->contacts_encoder = apbctGetContactsEncoder();
        $this->contacts_encoder->runEncoding();

        $this->assertEquals($pass_key, Cookie::get('apbct_email_encoder_passed'));

        $apbct->buffer =
            '<p>any text to encode</p>' .
            '<p>email test1@te.st</p>' .
            '<p>text and email, email - test2@te.st</p>';

        $this->contacts_encoder->modifyBuffer();

        $this->assertEquals(3, substr_count($apbct->buffer, '<p>'));
        $this->assertEquals(3, substr_count($apbct->buffer, '</p>'));
        $this->assertStringNotContainsString('apbct-email-encoder', $apbct->buffer);
        $this->assertStringContainsString('any text to encode', $apbct->buffer);
        $this->assertStringContainsString('test1@te.st', $apbct->buffer);
        $this->assertStringContainsString('test2@te.st', $apbct->buffer);
    }

    public function testBufferOutput()
    {
        global $apbct;
        ob_start();
        $apbct->buffer = $this->plain_text;

        $this->contacts_encoder->bufferOutput();
        $output = ob_get_clean();

        $this->assertEquals($this->plain_text, $output);
    }

    // =========================================================================
    // compileResponse — sanitization tests
    // =========================================================================

    /**
     * compileResponse returns false when an empty array is passed.
     */
    public function testCompileResponseReturnsFalseOnEmptyInput()
    {
        $result = $this->contacts_encoder->compileResponse([], true);
        $this->assertFalse($result);
    }

    /**
     * compileResponse returns false when a non-array is passed.
     */
    public function testCompileResponseReturnsFalseOnNonArrayInput()
    {
        $result = $this->contacts_encoder->compileResponse(null, true);
        $this->assertFalse($result);
    }

    /**
     * When is_allowed = true the decoded_email is present in the result
     * and passes through wp_kses_post (escKsesPost).
     */
    public function testCompileResponseDecodedEmailPresentWhenAllowed()
    {
        $encoded = 'encodedKey';
        $decoded = 'user@example.com';

        $result = $this->contacts_encoder->compileResponse([$encoded => $decoded], true);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertTrue($result[0]['is_allowed']);
        $this->assertFalse($result[0]['show_comment']);
        $this->assertEquals($decoded, $result[0]['decoded_email']);
        $this->assertEquals($encoded, $result[0]['encoded_email']);
    }

    /**
     * When is_allowed = false the decoded_email must be an empty string —
     * contact data must not be exposed to blocked visitors.
     */
    public function testCompileResponseDecodedEmailEmptyWhenNotAllowed()
    {
        $encoded = 'encodedKey';
        $decoded = 'user@example.com';

        $result = $this->contacts_encoder->compileResponse([$encoded => $decoded], false);

        $this->assertIsArray($result);
        $this->assertFalse($result[0]['is_allowed']);
        $this->assertTrue($result[0]['show_comment']);
        $this->assertSame('', $result[0]['decoded_email']);
    }

    /**
     * escHtml (esc_html) must escape HTML special characters in the comment field.
     * e.g. <script>alert(1)</script> → &lt;script&gt;alert(1)&lt;/script&gt;
     */
    public function testCompileResponseCommentIsEscapedByEscHtml()
    {
        // Simulate a comment that contains XSS payload — arrives from API response
        $xss_comment    = '<script>alert(1)</script>';
        $expected_comment = esc_html($xss_comment); // &lt;script&gt;alert(1)&lt;/script&gt;

        // Inject the comment via reflection so we don't need a real API call
        $reflection = new \ReflectionClass($this->contacts_encoder);
        $prop = $reflection->getProperty('comment');
        $prop->setAccessible(true);
        $prop->setValue($this->contacts_encoder, $xss_comment);

        $result = $this->contacts_encoder->compileResponse(['key' => 'val'], true);

        $this->assertSame($expected_comment, $result[0]['comment']);
        $this->assertStringNotContainsString('<script>', $result[0]['comment']);
    }

    /**
     * escHtml must escape angle brackets, quotes and ampersands in the comment.
     */
    public function testCompileResponseCommentEscapesSpecialHtmlChars()
    {
        $raw_comment      = '"Hello" & <World>';
        $expected_comment = esc_html($raw_comment);

        $reflection = new \ReflectionClass($this->contacts_encoder);
        $prop = $reflection->getProperty('comment');
        $prop->setAccessible(true);
        $prop->setValue($this->contacts_encoder, $raw_comment);

        $result = $this->contacts_encoder->compileResponse(['key' => 'val'], true);

        $this->assertSame($expected_comment, $result[0]['comment']);
    }

    /**
     * escKsesPost (wp_kses_post) must strip disallowed tags from decoded_email
     * while keeping safe tags like <a>.
     */
    public function testCompileResponseDecodedEmailStripsDisallowedTagsViaKsesPost()
    {
        $encoded = 'encodedKey';
        // <script> must be stripped; <a> is allowed by wp_kses_post
        $decoded_with_script = '<script>evil()</script><a href="mailto:u@e.com">u@e.com</a>';
        $expected = wp_kses_post(strip_tags($decoded_with_script, '<a>'));

        $result = $this->contacts_encoder->compileResponse([$encoded => $decoded_with_script], true);

        $this->assertStringNotContainsString('<script>', $result[0]['decoded_email']);
        $this->assertSame($expected, $result[0]['decoded_email']);
    }

    /**
     * strip_tags with '<a>' allowlist must remove all tags except <a> before kses.
     * Verifies the strip_tags → escKsesPost chain for decoded_email.
     */
    public function testCompileResponseDecodedEmailStripTagsAllowsOnlyAnchor()
    {
        $encoded = 'encodedKey';
        $decoded = '<b>bold</b> <a href="#">link</a> <img src="x">';
        $expected = wp_kses_post(strip_tags($decoded, '<a>'));

        $result = $this->contacts_encoder->compileResponse([$encoded => $decoded], true);

        $this->assertStringNotContainsString('<b>', $result[0]['decoded_email']);
        $this->assertStringNotContainsString('<img', $result[0]['decoded_email']);
        $this->assertStringContainsString('<a', $result[0]['decoded_email']);
        $this->assertSame($expected, $result[0]['decoded_email']);
    }

    /**
     * strip_tags on encoded_email key must allow only <a> tag.
     */
    public function testCompileResponseEncodedEmailStripsTagsAllowsOnlyAnchor()
    {
        $encoded = '<b>boldKey</b><a href="#">anchorKey</a>';
        $decoded = 'user@example.com';
        $expected_encoded = strip_tags($encoded, '<a>');

        $result = $this->contacts_encoder->compileResponse([$encoded => $decoded], true);

        $this->assertSame($expected_encoded, $result[0]['encoded_email']);
        $this->assertStringNotContainsString('<b>', $result[0]['encoded_email']);
    }

    /**
     * compileResponse correctly handles multiple entries in a single call.
     */
    public function testCompileResponseHandlesMultipleEntries()
    {
        $data = [
            'encoded1' => 'user1@example.com',
            'encoded2' => 'user2@example.com',
            'encoded3' => 'user3@example.com',
        ];

        $result = $this->contacts_encoder->compileResponse($data, true);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);

        foreach ($result as $index => $item) {
            $this->assertArrayHasKey('is_allowed', $item);
            $this->assertArrayHasKey('decoded_email', $item);
            $this->assertArrayHasKey('encoded_email', $item);
            $this->assertArrayHasKey('comment', $item);
            $this->assertArrayHasKey('show_comment', $item);
            $this->assertNotEmpty($item['decoded_email']);
        }
    }

    /**
     * When is_allowed = false and multiple entries are passed,
     * all decoded_email fields must be empty strings.
     */
    public function testCompileResponseAllDecodedEmailsEmptyWhenNotAllowed()
    {
        $data = [
            'encoded1' => 'user1@example.com',
            'encoded2' => 'user2@example.com',
        ];

        $result = $this->contacts_encoder->compileResponse($data, false);

        foreach ($result as $item) {
            $this->assertSame('', $item['decoded_email']);
        }
    }

    /**
     * @param string $url
     *
     * @return string
     */
    private function invokeReadCssFromSrc($url)
    {
        $integration = $this->contacts_encoder->getGridBuilderIntegration();
        $method = new \ReflectionMethod($integration, 'readCssFromSrc');
        $method->setAccessible(true);

        return $method->invoke($integration, $url);
    }

    public function tearDown() : void
    {
        global $apbct;
        $apbct->buffer = '';
        $this->clearDecoderPassedCookie();
    }
}
