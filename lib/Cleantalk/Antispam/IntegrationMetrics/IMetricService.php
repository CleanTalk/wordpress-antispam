<?php

namespace Cleantalk\Antispam\IntegrationMetrics;

use Cleantalk\Antispam\Integrations\IntegrationBase;
use Cleantalk\Antispam\IntegrationsByClass\IntegrationByClassBase;

class IMetricService
{
    /**
     * @param IntegrationBase|IntegrationByClassBase $integration
     *
     * @return IMetricDTO|false
     */
    public static function getDTO($integration)
    {
        $dto_version = self::getDTOVersion($integration);
        if ($dto_version) {
            $dto = new IMetricDTO();
            $dto->dto_version = $dto_version;
            $full_class = get_class($integration);
            $position = strrpos($full_class, '\\');
            if (!$position) {
                $position = -1;
            }
            $dto->integration_name = substr($full_class, $position + 1);
            self::startGlobalSeeking($dto);
            return $dto;
        }
        return false;
    }

    /**
     * @param IntegrationBase|IntegrationByClassBase $integration
     *
     * @return string|false
     */
    private static function getDTOVersion($integration)
    {
        return $integration->imetric_dto_version ?? false;
    }

    /**
     * @param IMetricDTO $dto
     *
     * @return void
     */
    private static function startGlobalSeeking(IMetricDTO $dto)
    {
        $dto->timer_on_start_msec = self::getCurrentTimeMS();
        $dto->memory_usage_on_start_kb = self::getCurrentMemoryUsageKb();
        $dto->peak_memory_on_start_kb = self::getPeakMemoryUsageKb();
    }

    /**
     * @return float
     */
    private static function getCurrentTimeMS()
    {
        return round(microtime(true) * 1000, 1);
    }

    /**
     * @return float
     */
    private static function getCurrentMemoryUsageKb()
    {
        return round(memory_get_usage() / 1024);
    }

    /**
     * @return float
     */
    private static function getPeakMemoryUsageKb()
    {
        return round(memory_get_peak_usage() / 1024);
    }

    /**
     * @param IntegrationBase|IntegrationByClassBase $integration
     * @param string $span_name
     *
     * @return void
     */
    public static function seek($integration, string $span_name = 'undefined_span')
    {
        if ($integration instanceof IntegrationBase || $integration instanceof IntegrationByClassBase) {
            $dto = $integration->getImetricDTO();
            if ($dto && !$dto->is_released) {
                if (!isset($dto->spans[$span_name])) {
                    $dto->spans[$span_name] = [
                        'time_msec' => self::getCurrentTimeMS(),
                        'memory_kb' => self::getCurrentMemoryUsageKb(),
                        'memory_peak_kb' => self::getPeakMemoryUsageKb(),
                        'released' => false
                    ];
                }
            }
        }
    }

    public static function lease($integration, string $span_name = 'undefined_span')
    {
        if ($integration instanceof IntegrationBase || $integration instanceof IntegrationByClassBase) {
            $dto = $integration->getImetricDTO();
            if ($dto && !$dto->is_released) {
                $dto->spans[$span_name] = self::releaseSpan($dto->spans[$span_name]);
            }
        }
    }

    /**
     * @param IMetricDTO $dto
     *
     * @return void
     */
    public static function releaseAllSpans(IMetricDTO $dto)
    {
        foreach ($dto->spans as $_span_name => &$span_content) {
            $span_content = self::releaseSpan($span_content);
        }
    }

    /**
     * @param array $span_content
     *
     * @return array
     */
    private static function releaseSpan(array $span_content)
    {
        if (
            isset(
                $span_content['released'],
                $span_content['time_msec'],
                $span_content['memory_kb'],
                $span_content['memory_peak_kb']
            )
        ) {
            if (!$span_content['released']) {
                $span_content['time_msec'] = self::getCurrentTimeMS() - $span_content['time_msec'];
                $span_content['memory_kb'] = self::getCurrentMemoryUsageKb() - $span_content['memory_kb'];
                $span_content['memory_peak_kb'] = self::getPeakMemoryUsageKb() - $span_content['memory_peak_kb'];
                $span_content['released'] = true;
            }
        }
        return $span_content;
    }

    /**
     * @param IntegrationBase|IntegrationByClassBase $integration
     * @return string
     */
    public static function finalizeDTO($integration)
    {
        $out = false;
        $dto = $integration->getImetricDTO();
        if ($dto && !$dto->is_released) {
            $dto->peak_memory_diff_kb = self::getPeakMemoryUsageKb() - $dto->peak_memory_on_start_kb;
            $dto->total_exec_time_ms = self::getCurrentTimeMS() - $dto->timer_on_start_msec;
            self::releaseAllSpans($dto);
            $dto->is_released = true;
            $out = $dto->getJSON();
        }
        if (!$out) {
            $out = new IMetricDTO();
            $out = $out->getJSON();
            if (!$out) {
                $out = '{}';
            }
        }
        return $out;
    }

    /**
     * @param IMetricDTO|null $dto
     * @param string $field_name
     * @param mixed $field_value
     */
    public static function setCustomField($dto = null, $field_name = 'field', $field_value = null)
    {
        $dto && !$dto->is_released && $dto->custom_fields[$field_name] = $field_value;
    }

    /**
     * @param IntegrationBase|IntegrationByClassBase $integration
     * @param array $vars
     * @param string $span
     */
    public static function dumpVarsSize($integration, array $vars = [], string $span = 'undefined_variable_span')
    {
        $dto = $integration->getImetricDTO();
        if (!$dto || empty($vars)) {
            return;
        }
        $max_var_size_kb = 0;
        foreach ($vars as $_name => $value) {
            try {
                $size = strlen(serialize($value));
                $current_size = round($size / 1024, 1);
                if ($current_size > $max_var_size_kb) {
                    $max_var_size_kb = $current_size;
                }
            } catch (\Exception $e) {
                // is unserializable, skip
            }
        }
        $dto->variable_peak_kb[$span] = $max_var_size_kb;
    }
}
