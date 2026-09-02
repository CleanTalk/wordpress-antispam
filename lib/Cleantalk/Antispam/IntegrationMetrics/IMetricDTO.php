<?php

namespace Cleantalk\Antispam\IntegrationMetrics;

class IMetricDTO
{
    /**
     * @var string
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $integration_name = 'unset';
    /**
     * @var string
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $dto_version = '1.0.0';
    /**
     * @var float
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $peak_memory_diff_kb = 0;
    /**
     * @var float
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $total_exec_time_ms = 0;
    /**
     * @var array
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $custom_fields = array();

    public $spans = array();
    /**
     * @var array
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $variable_peak_kb = array();
    /**
     * @var float
     */
    public $timer_on_start_msec = 0;
    /**
     * @var float
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $memory_usage_on_start_kb = 0;
    /**
     * @var float
     */
    public $peak_memory_on_start_kb = 0;
    /**
     * @var bool
     */
    public $is_released = false;

    public static $SENDER_INFO_KEY = 'imetric';

    /**
     * @return false|string
     */
    public function getJSON()
    {
        return @json_encode($this->getArray());
    }

    public function getArray()
    {
        $skip_properties = array(
            'is_released',
            'peak_memory_on_start_kb',
            'memory_usage_on_start_kb',
            'timer_on_start_msec',
            'SENDER_INFO_KEY'
        );
        return array_map(function ($value) {
            return $value;
        }, array_diff_key(get_object_vars($this), array_flip($skip_properties)));
    }
}
