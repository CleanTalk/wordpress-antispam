<?php

namespace Cleantalk\Common\RateLimiter;

/**
 * Configuration class for rate limiter settings
 *
 * Holds the configuration parameters that define how rate limiting should be applied
 * for a specific type of request (e.g., login attempts, comment submissions, etc.)
 */
class RateLimiterConfig
{
    /**
     * Type of rate limit (e.g., 'login', 'comment', 'registration')
     * Used to differentiate between different kinds of rate-limited actions
     *
     * @var string|null
     */
    public $type = null;

    /**
     * Maximum number of allowed requests within the configured period
     * Once this limit is exceeded, further requests will be rate limited
     *
     * @var int
     */
    public $limit = 5;

    /**
     * Time period in seconds during which the request limit applies
     * For example, a period of 60 with limit 10 means 10 requests per minute
     *
     * @var int
     */
    public $period = 5;

    /**
     * RateLimiterConfig constructor.
     *
     * @param string $type  The type of rate-limited action
     * @param int    $limit Maximum number of allowed requests
     * @param int    $period Time period in seconds for the limit
     */
    public function __construct(string $type, int $limit, int $period)
    {
        $this->type = $type;
        $this->limit = $limit;
        $this->period = $period;
    }
}
