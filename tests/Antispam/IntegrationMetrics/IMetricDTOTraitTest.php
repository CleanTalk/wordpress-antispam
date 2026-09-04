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

    public function testGetIMetricDTOReturnsNullWhenDisabled()
    {
        // Создаем mock объекта с трейтом
        $fixture = $this->getMockBuilder(IMetricDTOTraitFixture::class)
            ->onlyMethods(['imetricIsDisabled'])
            ->getMock();

        // Настраиваем mock: метод imetricIsDisabled() должен вернуть true
        $fixture->expects($this->once())
            ->method('imetricIsDisabled')
            ->willReturn(true);

        // Устанавливаем DTO
        $dto = new IMetricDTO();
        $fixture->setIMetricDTO($dto);

        // Проверяем, что getIMetricDTO() возвращает null, несмотря на установленный DTO
        $this->assertNull($fixture->getIMetricDTO());

        // Проверяем, что свойство imetric_dto_version осталось null (как в оригинальном тесте)
        $this->assertNull($fixture->imetric_dto_version);
    }

    public function testGetIMetricDTOReturnsDtoWhenEnabled()
    {
        // Создаем mock с переопределенным методом imetricIsDisabled()
        $fixture = $this->getMockBuilder(IMetricDTOTraitFixture::class)
            ->onlyMethods(['imetricIsDisabled'])
            ->getMock();

        // Метод возвращает false (метрики включены)
        $fixture->expects($this->once())
            ->method('imetricIsDisabled')
            ->willReturn(false);

        $dto = new IMetricDTO();
        $fixture->setIMetricDTO($dto);

        // Проверяем, что getIMetricDTO() возвращает DTO
        $this->assertSame($dto, $fixture->getIMetricDTO());
    }
}
