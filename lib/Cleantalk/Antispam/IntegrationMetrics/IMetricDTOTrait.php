<?php

namespace Cleantalk\Antispam\IntegrationMetrics;

trait IMetricDTOTrait
{
    /**
     * @var IMetricDTO|null
     */
    protected $imetric_dto = null;

    /**
     * @var string|null
     */
    public $imetric_dto_version = null;

    /**
     * @param IMetricDTO $imetric_dto
     *
     * @return void
     */
    public function setIMetricDTO(IMetricDTO $imetric_dto): void
    {
        $this->imetric_dto = $imetric_dto;
        if (isset($this->imetric_dto_version)) {
            $this->imetric_dto->dto_version = $this->imetric_dto_version;
        }
    }

    /**
     * @return IMetricDTO|null
     */
    public function getIMetricDTO()
    {
        return $this->imetric_dto;
    }
}
