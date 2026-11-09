<?php

namespace ApbctWP;

use Cleantalk\ApbctWP\Helper;
use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    /**
     * @dataProvider jsonValuesProvider
     */
    public function testJsonSuccessfulOperations($value, $use_wp_json_encode)
    {
        $json = Helper::jsonEncode($value, 0, $use_wp_json_encode);

        $this->assertIsString($json);
        $this->assertSame($value, Helper::jsonDecode($json, true));
    }

    public function jsonValuesProvider()
    {
        $cases = array();
        foreach ( array(false, true) as $use_wp_json_encode ) {
            foreach ( array(null, false, 0, '', array(), array('success' => true)) as $value ) {
                $cases[] = array($value, $use_wp_json_encode);
            }
        }

        return $cases;
    }

    public function testJsonDecodeReturnsObjectByDefault()
    {
        $decoded = Helper::jsonDecode('{"success":true}');

        $this->assertInstanceOf(\stdClass::class, $decoded);
        $this->assertTrue($decoded->success);
    }

    public function testJsonDecodeError()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('JSON decoding failed: ');

        Helper::jsonDecode('{');
    }

    /**
     * @dataProvider invalidJsonInputProvider
     */
    public function testJsonDecodeRejectsNonStringInput($value)
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('JSON decoding requires a string.');

        Helper::jsonDecode($value);
    }

    public function invalidJsonInputProvider()
    {
        return array(array(null), array(false), array(1), array(array()));
    }

    public function testJsonEncodingInvalidUtf8()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('JSON encoding failed: ');

        Helper::jsonEncode("\xB1");
    }

    public function testWpJsonEncodingRepairsInvalidUtf8()
    {
        $expected = wp_json_encode("\xB1");

        $this->assertNotFalse($expected);
        $this->assertSame($expected, Helper::jsonEncode("\xB1", 0, true));
    }

    /**
     * @dataProvider jsonEncodersProvider
     */
    public function testJsonEncodingRecursion($use_wp_json_encode)
    {
        $value = array();
        $value['recursive'] = &$value;
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('JSON encoding failed: ');

        Helper::jsonEncode($value, 0, $use_wp_json_encode);
    }

    /**
     * @dataProvider jsonEncodersProvider
     */
    public function testJsonEncodingNonFiniteNumber($use_wp_json_encode)
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('JSON encoding failed: ');

        Helper::jsonEncode(INF, 0, $use_wp_json_encode);
    }

    /**
     * @dataProvider jsonEncodersProvider
     */
    public function testJsonEncodingPreservesFlags($use_wp_json_encode)
    {
        $this->assertSame(
            '{"0":"value"}',
            Helper::jsonEncode(array('value'), JSON_FORCE_OBJECT, $use_wp_json_encode)
        );
    }

    /**
     * @dataProvider jsonEncodersProvider
     */
    public function testJsonEncodingRejectsPartialOutput($use_wp_json_encode)
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('JSON encoding failed: ');

        Helper::jsonEncode(array('value' => INF), JSON_PARTIAL_OUTPUT_ON_ERROR, $use_wp_json_encode);
    }

    public function jsonEncodersProvider()
    {
        return array(array(false), array(true));
    }
}
