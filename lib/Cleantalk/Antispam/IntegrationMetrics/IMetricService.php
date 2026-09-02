<?php

namespace Cleantalk\Antispam\IntegrationMetrics;

use Cleantalk\Antispam\Integrations\IntegrationBase;
use Cleantalk\Antispam\IntegrationsByClass\IntegrationByClassBase;

/**
 * Integration Metrics Service
 *
 * Orchestrates performance metric collection for integration processing.
 * Provides a comprehensive API for tracking execution time, memory usage, and custom performance data.
 *
 * The service manages the full lifecycle of metrics:
 * 1. Creation: getDTO() - Creates and initializes a metrics DTO
 * 2. Collection: seek() / lease() - Records named time spans for operations
 * 3. Finalization: finalizeDTO() - Computes final metrics and serializes to JSON
 *
 * Key Concepts:
 * - Spans: Named checkpoints that track execution time and memory between seek() and lease()
 * - Timer: Records elapsed time and memory delta for operations
 * - Custom Fields: Arbitrary data added by integrations
 * - Variable Tracking: Monitors maximum memory usage of specific variables
 *
 * Complete Workflow Example:
 * <code>
 *   // 1. Initialize metrics
 *   $integration = new MyIntegration();
 *   $dto = IMetricService::getDTO($integration);
 *   if ($dto) {
 *       $integration->setIMetricDTO($dto);
 *
 *       // 2. Record operations as spans
 *       IMetricService::seek($integration, 'form_validation');
 *       // ... validate form fields ...
 *       IMetricService::lease($integration, 'form_validation');
 *
 *       // 3. Add custom data
 *       IMetricService::setCustomField($dto, 'field_count', count($fields));
 *       IMetricService::dumpVarsSize($integration, ['data' => $data], 'data_size');
 *
 *       // 4. Finalize and retrieve metrics
 *       $json = IMetricService::finalizeDTO($integration);
 *       // Send $json to analytics backend
 *   }
 * </code>
 *
 * Integration Requirements:
 * - Class must have $imetric_dto_version property set (or be null to skip metrics)
 * - Class should use IMetricDTOTrait and extend IntegrationBase or IntegrationByClassBase
 *
 * @see IMetricDTO
 * @see IMetricDTOTrait
 */
class IMetricService
{
    /**
     * Creates and initializes a metrics DTO for an integration.
     *
     * Checks if the integration has enabled metrics (via $imetric_dto_version property).
     * If enabled, creates a new IMetricDTO instance, sets the integration name from class name,
     * and captures initial timing/memory values.
     *
     * This method should be called at the beginning of integration processing,
     * typically before any metric collection begins.
     *
     * The integration_name is automatically extracted from the class name by removing
     * the namespace, making it human-readable (e.g., '\Foo\Bar\WooCommerce' -> 'WooCommerce').
     *
     * Usage:
     * <code>
     *   $dto = IMetricService::getDTO($integration);
     *   if ($dto) {
     *       $integration->setIMetricDTO($dto);
     *       // Metrics are now available for collection
     *   } else {
     *       // Integration doesn't have metrics enabled
     *   }
     * </code>
     *
     * @param IntegrationBase|IntegrationByClassBase $integration The integration instance
     *
     * @return IMetricDTO|false Initialized DTO if metrics are enabled, false otherwise
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
     * Checks if the integration has metrics enabled.
     *
     * Reads the $imetric_dto_version property. If it's set (not null) and truthy,
     * metrics collection is enabled for this integration.
     *
     * @param IntegrationBase|IntegrationByClassBase $integration The integration instance
     *
     * @return string|false The DTO version string if enabled, false otherwise
     */
    private static function getDTOVersion($integration)
    {
        return $integration->imetric_dto_version ?? false;
    }

    /**
     * Captures initial timing and memory values for metric tracking.
     *
     * Records the starting state of the system (current time, memory usage, peak memory).
     * These baseline values are used later to calculate deltas (how much time and memory
     * were consumed during integration processing).
     *
     * Called internally by getDTO() - do not call directly.
     *
     * @param IMetricDTO $dto The DTO to initialize
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
     * Gets the current system time in milliseconds.
     *
     * Uses microtime() for high precision timing.
     *
     * @return float Current time in milliseconds, rounded to 1 decimal place
     */
    private static function getCurrentTimeMS()
    {
        return round(microtime(true) * 1000, 1);
    }

    /**
     * Gets the current memory usage in kilobytes.
     *
     * Retrieves memory_get_usage() and converts to KB.
     *
     * @return float Current memory usage in KB
     */
    private static function getCurrentMemoryUsageKb()
    {
        return round(memory_get_usage() / 1024);
    }

    /**
     * Gets the peak memory usage in kilobytes.
     *
     * Retrieves memory_get_peak_usage() and converts to KB.
     * Note: Peak memory can only increase, never decrease during execution.
     *
     * @return float Peak memory usage in KB
     */
    private static function getPeakMemoryUsageKb()
    {
        return round(memory_get_peak_usage() / 1024);
    }

    /**
     * Records the start of a named operation span.
     *
     * Creates a new span with the current timing and memory values.
     * Spans track execution time and resource usage between seek() and lease().
     * Can only create each named span once - subsequent calls for the same span are ignored.
     *
     * Only works if:
     * - Integration has a valid IMetricDTO
     * - DTO has not been released (finalized)
     * - Span name doesn't already exist
     *
     * Typical usage with lease():
     * <code>
     *   IMetricService::seek($integration, 'database_query');
     *   // ... execute database operations ...
     *   IMetricService::lease($integration, 'database_query');
     *   // Now $dto->spans['database_query'] contains timing/memory delta
     * </code>
     *
     * @param IntegrationBase|IntegrationByClassBase $integration The integration instance
     * @param string $span_name Unique name for this span (e.g., 'form_validation', 'user_check')
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

    /**
     * Records the end of a named operation span and calculates deltas.
     *
     * Finalizes a span by:
     * - Calculating elapsed time since seek() was called
     * - Calculating memory delta
     * - Calculating peak memory delta
     * - Marking span as released (complete)
     *
     * Only works if:
     * - Integration has a valid IMetricDTO
     * - DTO has not been released
     * - Span exists and has not been released yet
     *
     * If span doesn't exist or is already released, has no effect.
     *
     * After calling lease(), the span values contain:
     * - time_msec: Time elapsed (milliseconds)
     * - memory_kb: Memory delta (KB used during span)
     * - memory_peak_kb: Peak memory delta (KB)
     * - released: true (marks span as finalized)
     *
     * Usage:
     * <code>
     *   IMetricService::seek($integration, 'validation');
     *   // ... perform validation (takes 50ms, uses 2MB) ...
     *   IMetricService::lease($integration, 'validation');
     *   // Now: spans['validation']['time_msec'] ≈ 50
     *   //      spans['validation']['memory_kb'] ≈ 2048
     * </code>
     *
     * @param IntegrationBase|IntegrationByClassBase $integration The integration instance
     * @param string $span_name Name of the span to finalize
     *
     * @return void
     */
    public static function lease($integration, string $span_name = 'undefined_span')
    {
if ($integration instanceof IntegrationBase || $integration instanceof IntegrationByClassBase) {
    $dto = $integration->getIMetricDTO();
    if ($dto && !$dto->is_released && isset($dto->spans[$span_name]) && is_array($dto->spans[$span_name])) {
        $dto->spans[$span_name] = self::releaseSpan($dto->spans[$span_name]);
    }
}
    }

    /**
     * Finalizes all open spans in a DTO.
     *
     * Iterates through all spans and calls releaseSpan() to finalize them.
     * Spans that are already released are left unchanged.
     * Typically called by finalizeDTO() as part of metric completion.
     *
     * @param IMetricDTO $dto The DTO containing spans to finalize
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
     * Completes a single span by calculating deltas.
     *
     * Converts span values from absolute measurements to deltas:
     * - time_msec: Changed from start_time to elapsed_time
     * - memory_kb: Changed from start_memory to used_memory
     * - memory_peak_kb: Changed from start_peak to peak_delta
     * - released: Changed to true
     *
     * If span is already released, returns unchanged.
     * If span is missing required fields, returns unchanged.
     *
     * Internal method called by lease() and releaseAllSpans() - typically not called directly.
     *
     * @param array $span_content The span data to finalize
     *
     * @return array Finalized span with delta values
     */
    private static function releaseSpan(array $span_content)
    {
        if (isset(
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
     * Completes metric collection and returns serialized JSON.
     *
     * This is the final step in metric lifecycle. It:
     * 1. Finalizes all spans (calls releaseAllSpans())
     * 2. Calculates overall metrics:
     *    - peak_memory_diff_kb: Peak memory change during processing
     *    - total_exec_time_ms: Total elapsed time
     * 3. Marks DTO as released (no further updates allowed)
     * 4. Serializes to JSON
     *
     * If DTO is missing or already released, returns a default empty DTO as JSON.
     *
     * Should be called at the end of integration processing, typically in error handlers
     * or finally blocks to ensure metrics are always captured.
     *
     * Complete usage pattern:
     * <code>
     *   try {
     *       $integration = new MyIntegration();
     *       $dto = IMetricService::getDTO($integration);
     *       if ($dto) {
     *           $integration->setIMetricDTO($dto);
     *           // ... perform integration work ...
     *       }
     *   } finally {
     *       $metrics_json = IMetricService::finalizeDTO($integration);
     *       // Send metrics to backend
     *       $response->add_custom_data('metrics', $metrics_json);
     *   }
     * </code>
     *
     * @param IntegrationBase|IntegrationByClassBase $integration The integration instance
     *
     * @return string JSON-encoded metrics. Returns '{}' if encoding fails.
     *
     * @see getDTO()
     * @see releaseAllSpans()
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
     * Adds a custom field to the DTO's custom_fields array.
     *
     * Custom fields allow integrations to attach arbitrary performance data.
     * Examples: form field count, validation result, user ID, etc.
     *
     * Safely handles null DTO and released DTO (silently ignores).
     *
     * Usage:
     * <code>
     *   IMetricService::setCustomField($dto, 'form_fields_count', 15);
     *   IMetricService::setCustomField($dto, 'validation_passed', true);
     *   IMetricService::setCustomField($dto, 'processing_stage', 'pre_submission');
     *   // Now $dto->custom_fields contains all three fields
     * </code>
     *
     * @param IMetricDTO|null $dto The DTO to update (if null, method is no-op)
     * @param string $field_name Custom field key name
     * @param mixed $field_value Custom field value (string, number, boolean, array, etc.)
     *
     * @return void
     */
    public static function setCustomField($dto = null, $field_name = 'field', $field_value = null)
    {
        $dto && !$dto->is_released && $dto->custom_fields[$field_name] = $field_value;
    }

    /**
     * Tracks the peak memory usage of specific variables.
     *
     * Serializes each variable and records the maximum serialized size in KB.
     * Useful for profiling large data structures being processed by integrations.
     *
     * Silently skips:
     * - Non-serializable objects (unserializable values are caught and skipped)
     * - Empty variable arrays
     * - Integrations without a DTO
     *
     * Usage:
     * <code>
     *   $form_data = ['field1' => 'value', 'nested' => ['x' => 'y', ...]];
     *   $user_data = get_user_meta($user_id);
     *
     *   IMetricService::dumpVarsSize(
     *       $integration,
     *       ['form' => $form_data, 'user' => $user_data],
     *       'user_form_data_size'
     *   );
     *   // Now $dto->variable_peak_kb['user_form_data_size'] = largest serialized size
     * </code>
     *
     * The stored value represents the maximum size of any single variable serialized,
     * not the sum of all variables. This helps identify which data structure is largest.
     *
     * @param IntegrationBase|IntegrationByClassBase $integration The integration instance
     * @param array $vars Key-value pairs where values are variables to profile
     * @param string $span Name for grouping related variable measurements
     *
     * @return void
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
