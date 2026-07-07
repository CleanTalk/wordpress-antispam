<?php

namespace Cleantalk\Common\RateLimiter;

use Cleantalk\Common\Templates\Dto;

/**
 * Data transfer object for rate limit UID records
 *
 * @package CleantalkSP\Common\RateLimit
 */
class RateLimiterDto extends Dto
{
    /**
     * Current request counter for the UID
     *
     * @var int
     */
    public $counter = 0;

    /**
     * Timestamp of the last request
     *
     * @var int
     */
    public $last_call = 0;

    /**
     * Timestamp when the record was created
     *
     * @var int
     */
    public $created_at = 0;

    /**
     * IP address associated with the UID
     *
     * @var string
     */
    public $ip = '';

    /**
     * User agent associated with the UID
     *
     * @var string
     */
    public $ua = '';

    /**
     * Unique identifier for the rate limit record
     *
     * @var string
     */
    public $uid = '';

    /**
     * Type of rate limit (e.g., 'login', 'comment', etc.)
     *
     * @var string
     */
    public $type = '';

    /**
     * Flag indicating if all required data fields are present
     *
     * @var bool
     */
    public $data_ok = false;

    protected $obligatory_properties = [
        'uid',
        'type',
        'ip',
        'ua',
        'counter',
        'last_call',
        'created_at',
    ];

    /**
     * RateLimitUidData constructor.
     *
     * @param array $raw_data Raw data array from database
     */
    public function __construct($raw_data)
    {
        try {
            parent::__construct($raw_data);
            $this->data_ok = true;
        } catch (\Exception $_e) {
            $this->data_ok = false;
        }
    }
}
