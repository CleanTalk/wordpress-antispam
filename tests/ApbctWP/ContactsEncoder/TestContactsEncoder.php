<?php

namespace ApbctWP\ContactsEncoder;

use Cleantalk\ApbctWP\ContactsEncoder\ContactsEncoder;
use Cleantalk\ApbctWP\State;
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

}
