<?php

namespace Cleantalk\Antispam\IntegrationMetrics;

/**
 * Integration Metrics Data Transfer Object
 *
 * This class captures performance metrics for integration processing, including execution time,
 * memory usage, and custom performance data. It acts as a container for performance telemetry
 * that can be serialized to JSON for transmission to monitoring/analytics systems.
 *
 * The class is designed to work with IMetricService which handles metric collection and
 * span lifecycle management (creation, measurement, finalization).
 *
 * Usage Example:
 * <code>
 *   $dto = new IMetricDTO();
 *   $dto->integration_name = 'WooCommerce';
 *   $dto->custom_fields['order_count'] = 5;
 *
 *   $json = $dto->getJSON();
 *   // Send $json to analytics backend
 * </code>
 *
 * @see IMetricService for metric lifecycle management
 * @see IMetricDTOTrait for integration with IntegrationBase and IntegrationByClassBase
 */
class IMetricDTO
{
    /**
     * Name of the integration being measured (e.g., 'WooCommerce', 'NinjaForms').
     * Set by IMetricService::getDTO() when creating a new DTO instance.
     *
     * @var string
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $integration_name = 'unset';

    /**
     * Version of the metrics schema/format.
     * Can be overridden via IMetricDTOTrait::$imetric_dto_version on the integration class.
     *
     * @var string
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $dto_version = '1.0.0';

    /**
     * Peak memory usage difference in kilobytes (KB) during integration processing.
     * Calculated as: peak_memory_at_end - peak_memory_on_start
     * Set during IMetricService::finalizeDTO()
     *
     * @var float
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $peak_memory_diff_kb = 0;

    /**
     * Total execution time in milliseconds (ms) for the entire integration processing.
     * Calculated as: end_time_ms - timer_on_start_msec
     * Set during IMetricService::finalizeDTO()
     *
     * @var float
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $total_exec_time_ms = 0;

    /**
     * Custom performance fields added by the integration.
     * Format: field_name => field_value
     * Example: ['form_fields_count' => 15, 'validation_passed' => true]
     * Populated via IMetricService::setCustomField()
     *
     * @var array
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $custom_fields = array();

    /**
     * Named time spans tracking specific operations within the integration.
     * Format: span_name => ['time_msec' => float, 'memory_kb' => float, 'memory_peak_kb' => float, 'released' => bool]
     *
     * Each span captures:
     * - time_msec: Duration in milliseconds (calculated by IMetricService::lease())
     * - memory_kb: Memory usage in KB (calculated by IMetricService::lease())
     * - memory_peak_kb: Peak memory in KB (calculated by IMetricService::lease())
     * - released: Whether the span has been finalized (true = measurements complete)
     *
     * Spans are created via IMetricService::seek() and finalized via IMetricService::lease()
     *
     * @var array
     */
    public $spans = array();

    /**
     * Peak memory usage in KB for specific variable groups, tracked by span name.
     * Format: span_name => peak_kb_value
     * Example: ['form_data_vars' => 42.5, 'post_data_vars' => 128.3]
     * Populated via IMetricService::dumpVarsSize()
     *
     * @var array
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $variable_peak_kb = array();

    /**
     * Internal: Timer value (in ms) captured at metric start.
     * Used to calculate total_exec_time_ms = current_time - timer_on_start_msec
     * Set by IMetricService::startGlobalSeeking() and should not be modified directly.
     * Excluded from JSON output via getArray()
     *
     * @var float
     */
    public $timer_on_start_msec = 0;

    /**
     * Internal: Memory usage (in KB) captured at metric start.
     * Used to track relative memory usage throughout integration processing.
     * Set by IMetricService::startGlobalSeeking() and should not be modified directly.
     * Excluded from JSON output via getArray()
     *
     * @var float
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $memory_usage_on_start_kb = 0;

    /**
     * Internal: Peak memory usage (in KB) captured at metric start.
     * Used to calculate peak_memory_diff_kb = peak_memory_at_end - peak_memory_on_start_kb
     * Set by IMetricService::startGlobalSeeking() and should not be modified directly.
     * Excluded from JSON output via getArray()
     *
     * @var float
     */
    public $peak_memory_on_start_kb = 0;

    /**
     * Internal: Flag indicating whether metric finalization is complete.
     * When true, no further span creation or field updates are allowed.
     * Set by IMetricService::finalizeDTO()
     * Excluded from JSON output via getArray()
     *
     * @var bool
     */
    public $is_released = false;

    /**
     * JSON key name used when embedding this DTO in sender info.
     * Used to identify metric data in analytics payloads.
     *
     * @var string
     */
    public static $SENDER_INFO_KEY = 'imetric';

    /**
     * Serializes the DTO to JSON string.
     *
     * Converts the DTO object to a JSON-encoded string suitable for transmission to analytics systems.
     * Internally calls getArray() to filter out internal properties before encoding.
     *
     * @return false|string JSON string representation of the DTO, or false if JSON encoding fails
     *
     * @see getArray() for the exact properties included in the output
     */
    public function getJSON()
    {
        return @json_encode($this->getArray());
    }

    /**
     * Returns the DTO as an associative array, excluding internal properties.
     *
     * This method filters out internal tracking properties that are not meant to be transmitted:
     * - is_released: tracks finalization state
     * - peak_memory_on_start_kb: baseline for calculations
     * - memory_usage_on_start_kb: baseline for calculations
     * - timer_on_start_msec: baseline for calculations
     * - SENDER_INFO_KEY: static metadata key
     *
     * All other public properties are included in the output array.
     *
     * Usage:
     * <code>
     *   $dto = new IMetricDTO();
     *   $dto->integration_name = 'MyForm';
     *   $dto->custom_fields['status'] = 'success';
     *
     *   $array = $dto->getArray();
     *   // $array now contains integration_name, dto_version, spans, custom_fields, etc.
     *   // but not is_released or *_on_start_* properties
     * </code>
     *
     * @return array Associative array of DTO properties ready for JSON serialization
     *
     * @see getJSON() for JSON-encoded output
     */
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
