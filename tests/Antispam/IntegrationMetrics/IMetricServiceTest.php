<?php

use Cleantalk\Antispam\IntegrationMetrics\IMetricDTO;
use Cleantalk\Antispam\IntegrationMetrics\IMetricService;
use Cleantalk\Antispam\Integrations\IntegrationBase;
use Cleantalk\Antispam\IntegrationsByClass\IntegrationByClassBase;
use PHPUnit\Framework\TestCase;

if ( ! class_exists('IMetricServiceIntegrationBaseFixture', false)) {
    class IMetricServiceIntegrationBaseFixture extends IntegrationBase
    {
        public function getDataForChecking($argument)
        {
            return array();
        }

        public function doBlock($message)
        {
            return null;
        }
    }
}

if ( ! class_exists('IMetricServiceIntegrationByClassBaseFixture', false)) {
    class IMetricServiceIntegrationByClassBaseFixture extends IntegrationByClassBase
    {
        public function doPublicWork()
        {
            return;
        }
    }
}

if ( ! class_exists('IMetricServiceNonIntegrationFixture', false)) {
    class IMetricServiceNonIntegrationFixture
    {
        public $imetric_dto_version = null;
        public $imetric_dto = null;

        public function getIMetricDTO()
        {
            return $this->imetric_dto;
        }
    }
}

class IMetricServiceTest extends TestCase
{
    public function testGetDTOReturnsFalseWhenVersionNotSet()
    {
        $integration = new IMetricServiceIntegrationBaseFixture();
        $this->assertFalse(IMetricService::getDTO($integration));
    }

    public function testGetDTOReturnsFalseForNonIntegration()
    {
        $integration = new IMetricServiceNonIntegrationFixture();
        $this->assertFalse(IMetricService::getDTO($integration));
    }

    public function testGetDTOReturnsPopulatedDTO()
    {
        $integration = new IMetricServiceIntegrationBaseFixture();
        $integration->imetric_dto_version = '9.9.9';

        $dto = IMetricService::getDTO($integration);

        $this->assertInstanceOf(IMetricDTO::class, $dto);
        $this->assertSame('9.9.9', $dto->dto_version);
        $this->assertSame('IMetricServiceIntegrationBaseFixture', $dto->integration_name);
        $this->assertGreaterThan(0, $dto->timer_on_start_msec);
        $this->assertGreaterThanOrEqual(0, $dto->memory_usage_on_start_kb);
        $this->assertGreaterThanOrEqual(0, $dto->peak_memory_on_start_kb);
        $this->assertFalse($dto->is_released);
    }

    public function testGetDTOWorksForIntegrationByClassBase()
    {
        $integration = new IMetricServiceIntegrationByClassBaseFixture();
        $integration->imetric_dto_version = '1.2.3';

        $dto = IMetricService::getDTO($integration);

        $this->assertInstanceOf(IMetricDTO::class, $dto);
        $this->assertSame('1.2.3', $dto->dto_version);
        $this->assertSame('IMetricServiceIntegrationByClassBaseFixture', $dto->integration_name);
    }

    public function testSeekCreatesSpan()
    {
        $integration = new IMetricServiceIntegrationBaseFixture();
        $dto = new IMetricDTO();
        $integration->setIMetricDTO($dto);

        IMetricService::seek($integration, 'my_span');

        $this->assertArrayHasKey('my_span', $dto->spans);
        $span = $dto->spans['my_span'];
        $this->assertFalse($span['released']);
        $this->assertArrayHasKey('time_msec', $span);
        $this->assertArrayHasKey('memory_kb', $span);
        $this->assertArrayHasKey('memory_peak_kb', $span);
    }

    public function testSeekIsNoOpForNonIntegration()
    {
        $integration = new IMetricServiceNonIntegrationFixture();
        $dto = new IMetricDTO();
        $integration->imetric_dto = $dto;

        IMetricService::seek($integration, 'my_span');

        $this->assertSame(array(), $dto->spans);
    }

    public function testSeekIsNoOpWhenDTOReleased()
    {
        $integration = new IMetricServiceIntegrationBaseFixture();
        $dto = new IMetricDTO();
        $dto->is_released = true;
        $integration->setIMetricDTO($dto);

        IMetricService::seek($integration, 'my_span');

        $this->assertSame(array(), $dto->spans);
    }

    public function testSeekIsNoOpWhenDTOMissing()
    {
        $integration = new IMetricServiceIntegrationBaseFixture();

        IMetricService::seek($integration, 'my_span');

        $this->assertNull($integration->getIMetricDTO());
    }

    public function testSeekDoesNotOverwriteExistingSpan()
    {
        $integration = new IMetricServiceIntegrationBaseFixture();
        $dto = new IMetricDTO();
        $integration->setIMetricDTO($dto);

        IMetricService::seek($integration, 'my_span');
        $original = $dto->spans['my_span'];
        usleep(1000);
        IMetricService::seek($integration, 'my_span');

        $this->assertSame($original, $dto->spans['my_span']);
    }

    public function testLeaseMarksSpanReleased()
    {
        $integration = new IMetricServiceIntegrationBaseFixture();
        $dto = new IMetricDTO();
        $integration->setIMetricDTO($dto);

        IMetricService::seek($integration, 'my_span');
        usleep(1000);
        IMetricService::lease($integration, 'my_span');

        $this->assertTrue($dto->spans['my_span']['released']);
        $this->assertGreaterThanOrEqual(0, $dto->spans['my_span']['time_msec']);
    }

    public function testLeaseIsNoOpForNonIntegration()
    {
        $integration = new IMetricServiceNonIntegrationFixture();
        $dto = new IMetricDTO();
        $dto->spans = array('my_span' => array(
            'time_msec' => 1.0,
            'memory_kb' => 1.0,
            'memory_peak_kb' => 1.0,
            'released' => false,
        ));
        $integration->imetric_dto = $dto;

        IMetricService::lease($integration, 'my_span');

        $this->assertFalse($dto->spans['my_span']['released']);
    }

    public function testReleaseAllSpansReleasesEverySpan()
    {
        $dto = new IMetricDTO();
        $dto->spans = array(
            'a' => array('time_msec' => 1.0, 'memory_kb' => 1.0, 'memory_peak_kb' => 1.0, 'released' => false),
            'b' => array('time_msec' => 2.0, 'memory_kb' => 2.0, 'memory_peak_kb' => 2.0, 'released' => false),
        );

        IMetricService::releaseAllSpans($dto);

        $this->assertTrue($dto->spans['a']['released']);
        $this->assertTrue($dto->spans['b']['released']);
    }

    public function testReleaseAllSpansSkipsAlreadyReleased()
    {
        $dto = new IMetricDTO();
        $dto->spans = array(
            'a' => array('time_msec' => 5.0, 'memory_kb' => 5.0, 'memory_peak_kb' => 5.0, 'released' => true),
        );

        IMetricService::releaseAllSpans($dto);

        $this->assertSame(5.0, $dto->spans['a']['time_msec']);
        $this->assertTrue($dto->spans['a']['released']);
    }

    public function testFinalizeDTOReleasesAndReturnsJSON()
    {
        $integration = new IMetricServiceIntegrationBaseFixture();
        $integration->imetric_dto_version = '1.0.0';
        $dto = IMetricService::getDTO($integration);
        $integration->setIMetricDTO($dto);
        IMetricService::seek($integration, 'span_a');
        usleep(500);

        $json = IMetricService::finalizeDTO($integration);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertTrue($dto->is_released);
        $this->assertArrayHasKey('span_a', $decoded['spans']);
        $this->assertTrue($decoded['spans']['span_a']['released']);
        $this->assertGreaterThanOrEqual(0, $decoded['total_exec_time_ms']);
    }

    public function testFinalizeDTOReturnsDefaultWhenDTOMissing()
    {
        $integration = new IMetricServiceIntegrationBaseFixture();

        $json = IMetricService::finalizeDTO($integration);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('unset', $decoded['integration_name']);
    }

    public function testFinalizeDTOReturnsDefaultWhenDTOAlreadyReleased()
    {
        $integration = new IMetricServiceIntegrationBaseFixture();
        $dto = new IMetricDTO();
        $dto->is_released = true;
        $integration->setIMetricDTO($dto);

        $json = IMetricService::finalizeDTO($integration);

        $decoded = json_decode($json, true);
        $this->assertSame('unset', $decoded['integration_name']);
    }

    public function testSetCustomFieldStoresValue()
    {
        $dto = new IMetricDTO();

        IMetricService::setCustomField($dto, 'foo', 'bar');

        $this->assertSame('bar', $dto->custom_fields['foo']);
    }

    public function testSetCustomFieldIsNoOpOnReleasedDTO()
    {
        $dto = new IMetricDTO();
        $dto->is_released = true;

        IMetricService::setCustomField($dto, 'foo', 'bar');

        $this->assertArrayNotHasKey('foo', $dto->custom_fields);
    }

    public function testSetCustomFieldIsNoOpWhenDTONull()
    {
        IMetricService::setCustomField(null, 'foo', 'bar');
        $this->assertTrue(true);
    }

    public function testDumpVarsSizeStoresMaxSerializedSize()
    {
        $integration = new IMetricServiceIntegrationBaseFixture();
        $dto = new IMetricDTO();
        $integration->setIMetricDTO($dto);
        $small = 'x';
        $large = str_repeat('y', 2048);

        IMetricService::dumpVarsSize($integration, array('small' => $small, 'large' => $large), 'vars_span');

        $this->assertArrayHasKey('vars_span', $dto->variable_peak_kb);
        $expected_kb = round(strlen(serialize($large)) / 1024, 1);
        $this->assertSame($expected_kb, $dto->variable_peak_kb['vars_span']);
    }

    public function testDumpVarsSizeIsNoOpWhenVarsEmpty()
    {
        $integration = new IMetricServiceIntegrationBaseFixture();
        $dto = new IMetricDTO();
        $integration->setIMetricDTO($dto);

        IMetricService::dumpVarsSize($integration, array(), 'vars_span');

        $this->assertSame(array(), $dto->variable_peak_kb);
    }

    public function testDumpVarsSizeIsNoOpWhenDTOMissing()
    {
        $integration = new IMetricServiceIntegrationBaseFixture();

        IMetricService::dumpVarsSize($integration, array('a' => 'b'), 'vars_span');

        $this->assertNull($integration->getIMetricDTO());
    }

    public function testDumpVarsSizeUsesDefaultSpanName()
    {
        $integration = new IMetricServiceIntegrationBaseFixture();
        $dto = new IMetricDTO();
        $integration->setIMetricDTO($dto);

        IMetricService::dumpVarsSize($integration, array('a' => 'value'));

        $this->assertArrayHasKey('undefined_variable_span', $dto->variable_peak_kb);
    }
}
