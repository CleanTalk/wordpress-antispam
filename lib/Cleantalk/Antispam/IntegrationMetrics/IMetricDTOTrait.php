<?php

namespace Cleantalk\Antispam\IntegrationMetrics;

/**
 * Integration Metrics DTO Trait
 *
 * Provides metric storage and access methods for integration classes.
 * This trait should be used in IntegrationBase and IntegrationByClassBase subclasses
 * to enable performance metric collection.
 *
 * The trait manages:
 * - Storage of the IMetricDTO instance
 * - Schema version configuration for the metrics
 * - Getter/setter methods for safe access
 *
 * Usage Example:
 * <code>
 *   class MyIntegration extends IntegrationBase {
 *       use IMetricDTOTrait; // Already included in base class
 *
 *       public function __construct() {
 *           $this->imetric_dto_version = '2.1.0'; // Override schema version if needed
 *       }
 *   }
 *
 *   $integration = new MyIntegration();
 *   $dto = IMetricService::getDTO($integration);
 *   $integration->setIMetricDTO($dto);
 *   // Now metrics can be collected via IMetricService
 * </code>
 *
 * @see IntegrationBase
 * @see IntegrationByClassBase
 * @see IMetricService
 */
trait IMetricDTOTrait
{
    /**
     * Stores the metrics DTO instance for this integration.
     * Access via getIMetricDTO() method.
     * Should not be accessed directly - use getter/setter methods instead.
     *
     * @var IMetricDTO|null
     */
    protected $imetric_dto = null;

    /**
     * Custom schema version for the metrics DTO.
     * If set before calling setIMetricDTO(), this value will override the default DTO version.
     * Useful for versioning different integration metrics implementations.
     *
     * Default: null (uses IMetricDTO default version)
     *
     * Example:
     * <code>
     *   $integration->imetric_dto_version = '2.5.0';
     *   IMetricService::getDTO($integration); // Will set dto_version to '2.5.0'
     * </code>
     *
     * @var string|null
     */
    public $imetric_dto_version = null;

    /**
     * Stores the provided IMetricDTO instance and applies version override if set.
     *
     * This method is called by IMetricService or integration setup code to assign
     * a metrics DTO to this integration. If $imetric_dto_version is set on the integration,
     * it will override the DTO's default version.
     *
     * Usage:
     * <code>
     *   $dto = new IMetricDTO();
     *   $dto->integration_name = 'WooCommerce';
     *   $integration->setIMetricDTO($dto);
     *   // Now $integration->getIMetricDTO() returns the same DTO
     * </code>
     *
     * @param IMetricDTO $imetric_dto The metrics DTO instance to store
     *
     * @return void
     */
    public function setIMetricDTO(IMetricDTO $imetric_dto): void
    {
        $this->imetric_dto = $imetric_dto;
        // Apply version override if the integration specifies a custom version
        if (isset($this->imetric_dto_version)) {
            $this->imetric_dto->dto_version = $this->imetric_dto_version;
        }
    }

    /**
     * Retrieves the stored IMetricDTO instance.
     *
     * Returns the metrics DTO that was previously set via setIMetricDTO(),
     * or null if no DTO has been assigned to this integration.
     *
     * Usage:
     * <code>
     *   $dto = $integration->getIMetricDTO();
     *   if ($dto) {
     *       IMetricService::seek($integration, 'operation_name');
     *       // ... perform operation ...
     *       IMetricService::lease($integration, 'operation_name');
     *   }
     * </code>
     *
     * @return IMetricDTO|null The stored metrics DTO, or null if not set
     */
    public function getIMetricDTO()
    {
        return $this->imetric_dto;
    }
}
