<?php

use Cleantalk\Antispam\IntegrationMetrics\IMetricDTO;
use Cleantalk\Antispam\IntegrationMetrics\IMetricDTOTrait;
use PHPUnit\Framework\TestCase;

if ( ! class_exists('IMetricDTOTraitFixture', false)) {
    class IMetricDTOTraitFixture
    {
        use IMetricDTOTrait;
    }
}

class IMetricDTOTraitTest extends TestCase
{
    public function testDefaultValues()
    {
        $fixture = new IMetricDTOTraitFixture();
        $this->assertNull($fixture->getIMetricDTO());
        $this->assertNull($fixture->imetric_dto_version);
    }

    public function testSetIMetricDTOStoresInstance()
    {
        $fixture = new IMetricDTOTraitFixture();
        $dto = new IMetricDTO();

        $fixture->setIMetricDTO($dto);

        $this->assertSame($dto, $fixture->getIMetricDTO());
    }

    public function testSetIMetricDTOKeepsDefaultVersionWhenNoOverride()
    {
        $fixture = new IMetricDTOTraitFixture();
        $dto = new IMetricDTO();
        $original_version = $dto->dto_version;

        $fixture->setIMetricDTO($dto);

        $this->assertSame($original_version, $fixture->getIMetricDTO()->dto_version);
    }

    public function testSetIMetricDTOOverridesVersionWhenSet()
    {
        $fixture = new IMetricDTOTraitFixture();
        $fixture->imetric_dto_version = '3.1.4';
        $dto = new IMetricDTO();

        $fixture->setIMetricDTO($dto);

        $this->assertSame('3.1.4', $fixture->getIMetricDTO()->dto_version);
        $this->assertSame('3.1.4', $dto->dto_version);
    }
}
