<?php

use Cleantalk\Antispam\IntegrationMetrics\IMetricDTO;
use PHPUnit\Framework\TestCase;

class IMetricDTOTest extends TestCase
{
    public function testDefaultProperties()
    {
        $dto = new IMetricDTO();

        $this->assertSame('unset', $dto->integration_name);
        $this->assertSame('1.0.0', $dto->dto_version);
        $this->assertSame(0, $dto->peak_memory_diff_kb);
        $this->assertSame(0, $dto->total_exec_time_ms);
        $this->assertSame(array(), $dto->custom_fields);
        $this->assertSame(array(), $dto->spans);
        $this->assertSame(array(), $dto->variable_peak_kb);
        $this->assertSame(0, $dto->timer_on_start_msec);
        $this->assertSame(0, $dto->memory_usage_on_start_kb);
        $this->assertSame(0, $dto->peak_memory_on_start_kb);
        $this->assertFalse($dto->is_released);
    }

    public function testSenderInfoKey()
    {
        $this->assertSame('imetric', IMetricDTO::$SENDER_INFO_KEY);
    }

    public function testGetArrayContainsPublishedProperties()
    {
        $dto = new IMetricDTO();
        $dto->integration_name = 'MyIntegration';
        $dto->dto_version = '2.5.7';
        $dto->peak_memory_diff_kb = 10.5;
        $dto->total_exec_time_ms = 123.4;
        $dto->custom_fields = array('foo' => 'bar');
        $dto->spans = array('span1' => array('time_msec' => 1));
        $dto->variable_peak_kb = array('span1' => 2.2);

        $array = $dto->getArray();

        $this->assertArrayHasKey('integration_name', $array);
        $this->assertArrayHasKey('dto_version', $array);
        $this->assertArrayHasKey('peak_memory_diff_kb', $array);
        $this->assertArrayHasKey('total_exec_time_ms', $array);
        $this->assertArrayHasKey('custom_fields', $array);
        $this->assertArrayHasKey('spans', $array);
        $this->assertArrayHasKey('variable_peak_kb', $array);

        $this->assertSame('MyIntegration', $array['integration_name']);
        $this->assertSame('2.5.7', $array['dto_version']);
        $this->assertSame(10.5, $array['peak_memory_diff_kb']);
        $this->assertSame(123.4, $array['total_exec_time_ms']);
        $this->assertSame(array('foo' => 'bar'), $array['custom_fields']);
    }

    public function testGetArraySkipsInternalProperties()
    {
        $dto = new IMetricDTO();
        $dto->is_released = true;
        $dto->peak_memory_on_start_kb = 999;
        $dto->memory_usage_on_start_kb = 888;
        $dto->timer_on_start_msec = 777;

        $array = $dto->getArray();

        $this->assertArrayNotHasKey('is_released', $array);
        $this->assertArrayNotHasKey('peak_memory_on_start_kb', $array);
        $this->assertArrayNotHasKey('memory_usage_on_start_kb', $array);
        $this->assertArrayNotHasKey('timer_on_start_msec', $array);
        $this->assertArrayNotHasKey('SENDER_INFO_KEY', $array);
    }

    public function testGetJSONMatchesArray()
    {
        $dto = new IMetricDTO();
        $dto->integration_name = 'Foo';
        $dto->custom_fields = array('a' => 1, 'b' => 'two');

        $json = $dto->getJSON();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertSame($dto->getArray(), $decoded);
    }
}
