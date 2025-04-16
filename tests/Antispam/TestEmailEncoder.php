<?php

namespace Antispam;

use Cleantalk\Antispam\EmailEncoder;
use PHPUnit\Framework\TestCase;

class TestEmailEncoder extends TestCase
{

    /**
     * @var EmailEncoder
     */
    private $email_encoder;

    private $plain_text = 'This is a plain text';

    public function setUp()
    {
        global $apbct;
        $apbct->api_key      = 'testapikey';
        $this->email_encoder = EmailEncoder::getInstance();
    }

    public function testPlainTextEncodeDecodeSSL()
    {
        $encoded_plain = $this->email_encoder->encoder->encodeString($this->plain_text);
        $this->assertNotEmpty($encoded_plain);
        $this->assertIsString($encoded_plain);
        $decoded_entity = $this->email_encoder->encoder->decodeString($encoded_plain);
        $this->assertNotEmpty($decoded_entity);
        $this->assertIsString($decoded_entity);
        global $apbct;
        $this->assertFalse($apbct->isHaveErrors());
        $this->assertEquals($decoded_entity, $this->plain_text);
    }

    public function testPlainTextEncodeDecodeBase()
    {
        $encoded_plain                = $this->email_encoder->ignoreOpenSSLMode()->encoder->encodeString($this->plain_text);
        $this->assertNotEmpty($encoded_plain);
        $this->assertIsString($encoded_plain);
        // make sure that b64 fired instead of ssl
        $decoded_b_string = base64_decode($encoded_plain);
        $decoded_b_string = str_rot13($decoded_b_string);
        $this->assertEquals($this->plain_text, $decoded_b_string);
        $decoded_entity = $this->email_encoder->encoder->decodeString($encoded_plain);
        $this->assertNotEmpty($decoded_entity);
        $this->assertIsString($decoded_entity);
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
            $result = $this->email_encoder->getObfuscatedEmailString($item[0]);
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
        $regexp_for_replace = '/.+data-original-string=[\s\S]+apbct-email-encoder[\s\S]+browser\.[\"\']>+' . $test_email_replace . '/';

        $apbct->settings['data__email_decoder_obfuscation_mode'] = 'blur';
        $apbct->saveSettings();
        $result = $this->email_encoder->modifyContent($test_email);
        $this->assertNotRegExp($regexp_for_obfuscation, $result);
        $this->assertNotRegExp($regexp_for_replace, $result);
        $this->assertRegExp($regexp_for_blur, $result);

        $apbct->settings['data__email_decoder_obfuscation_mode'] = 'obfuscate';
        $apbct->saveSettings();
        $result = $this->email_encoder->modifyContent($test_email);
        $this->assertNotRegExp($regexp_for_replace, $result);
        $this->assertNotRegExp($regexp_for_blur, $result);
        $this->assertRegExp($regexp_for_obfuscation, $result);

        $apbct->settings['data__email_decoder_obfuscation_mode'] = 'replace';
        $apbct->settings['data__email_decoder_obfuscation_custom_text'] = $test_email_replace;
        $apbct->saveSettings();
        $result = $this->email_encoder->modifyContent($test_email);
        $this->assertNotRegExp($regexp_for_blur, $result);
        $this->assertNotRegExp($regexp_for_obfuscation, $result);
        $this->assertRegExp($regexp_for_replace, $result);
    }

    public function testEncodingPhoneNumbers()
    {
        global $apbct;

        $apbct->settings['data__email_decoder_obfuscation_mode'] = 'blur';
        $apbct->settings['data__email_decoder_encode_phone_numbers'] = 1;
        $apbct->saveSettings();

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
        );

        $test_stack_skip_to = array(
            '192.168.2.1',
            '8.168.2.1',
            '81.234.56.78',
            '71234567890',
            '+7413033',
            '+7(413)033',
            '+7 (413) 03 3',
        );

        $regexp_for_blur = '/.+data-original-string=[\s\S]+apbct-email-encoder[\s\S]+browser\.[\"\']>[\s\S]+apbct-blur[\s\S]+\*?\*+<\/span>[\s\S]+/';

        foreach ($test_stack as $phone) {
            $result = $this->email_encoder->modifyContent($phone);
            $this->assertNotEmpty($result);
            $this->assertStringNotContainsString($phone, $result);
            $this->assertRegExp($regexp_for_blur, $result);
        }

        foreach ($test_stack_skip_to as $phone) {
            $result = $this->email_encoder->modifyContent($phone);
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

        $apbct->settings['data__email_decoder_obfuscation_mode'] = 'obfuscate';
        $apbct->settings['data__email_decoder_encode_phone_numbers'] = 1;
        $apbct->saveSettings();

        $result = $this->email_encoder->modifyContent($test_tel_origin);
        $this->assertRegExp($regexp_for_obfuscation, $result);

        $apbct->settings['data__email_decoder_encode_phone_numbers'] = 0;
        $apbct->saveSettings();

        $result = $this->email_encoder->modifyContent($test_tel_origin);
        $this->assertEquals($test_tel_origin, $result);
    }

}
