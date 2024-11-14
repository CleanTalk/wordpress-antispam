<?php

/*
 * This is a very bad test!
 * An example how have not to write tests.
 * Using more magic inexplicable reflection actions.
 * But the test works...
 */

namespace Antispam;

use Cleantalk\Antispam\EmailEncoder;
use Cleantalk\ApbctWP\Variables\Post;
use PHPUnit\Framework\TestCase;

class EmailEncoderTest extends TestCase
{

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
        $encoded_plain = $this->email_encoder->encodeString($this->plain_text);
        $this->assertNotEmpty($encoded_plain);
        $this->assertIsString($encoded_plain);
        $decoded_entity = $this->email_encoder->decodeString($encoded_plain);
        $this->assertNotEmpty($decoded_entity);
        $this->assertIsString($decoded_entity);
        global $apbct;
        $this->assertFalse($apbct->isHaveErrors());
        $this->assertEquals($decoded_entity, $this->plain_text);
    }

    public function testPlainTextEncodeDecodeBase()
    {
        $encoded_plain                = $this->email_encoder->ignoreOpenSSLMode()->encodeString($this->plain_text);
        $this->assertNotEmpty($encoded_plain);
        $this->assertIsString($encoded_plain);
        // make sure that b64 fired instead of ssl
        $decoded_b_string = base64_decode($encoded_plain);
        $decoded_b_string = str_rot13($decoded_b_string);
        $this->assertEquals($this->plain_text, $decoded_b_string);
        $decoded_entity = $this->email_encoder->decodeString($encoded_plain);
        $this->assertNotEmpty($decoded_entity);
        $this->assertIsString($decoded_entity);
        global $apbct;
        $this->assertFalse($apbct->isHaveErrors());
        $this->assertEquals($decoded_entity, $this->plain_text);
    }

}
