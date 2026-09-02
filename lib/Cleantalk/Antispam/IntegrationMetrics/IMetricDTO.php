<?php

    namespace Cleantalk\Antispam\IntegrationMetrics;

    class IMetricDTO
    {
        /**
         * @var string
         */
        public $integration_name = 'unset';
        /**
         * @var string
         */
        public $dto_version = '1.0.0';
        /**
         * @var int
         */
        public $peak_memory_diff_kb = 0;
        /**
         * @var int
         */
        public $total_exec_time_ms = 0;
        /**
         * @var array
         */
        public $custom_fields = array();

        public $spans = array();
        /**
         * @var array
         */
        public $variable_peak_kb = array();
        /**
         * @var int
         */
        public $timer_on_start_msec = 0;
        /**
         * @var int
         */
        public $memory_usage_on_start_kb = 0;
        /**
         * @var int
         */
        public $peak_memory_on_start_kb = 0;
        /**
         * @var bool
         */
        public $is_released = false;

        static $SENDER_INFO_KEY = 'imetric';

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
