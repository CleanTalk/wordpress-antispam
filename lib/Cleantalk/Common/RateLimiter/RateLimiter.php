<?php

namespace Cleantalk\Common\RateLimiter;

/**
 * Abstract base class for rate limiting functionality
 * Defines the core rate limiting logic and required methods for implementation
 */
abstract class RateLimiter
{
    /**
     * Rate limiter configuration object
     *
     * @var RateLimiterConfig
     */
    protected $config;

    /**
     * Current timestamp
     *
     * @var int
     */
    protected $current_ts;

    /**
     * Unique identifier for the current request
     *
     * @var string
     */
    protected $uid;

    /**
     * IP address of the current request
     *
     * @var string
     */
    protected $ip;

    /**
     * User agent of the current request
     *
     * @var string
     */
    protected $ua;

    /**
     * Flag indicating if the process completed successfully
     *
     * @var bool
     */
    public $process_ok = true;

    /**
     * RateLimiter constructor.
     *
     * @param RateLimiterConfig $config Configuration for the rate limiter
     */
    public function __construct(RateLimiterConfig $config)
    {
        $this->config = $config;
        $this->current_ts = time();

        $this->setIP();
        $this->setUA();
        $this->setUID();
    }

    /**
     * Sets the unique identifier for the current request
     * Must be implemented by child classes
     *
     * @return void
     */
    protected function setUID(): void
    {
        $this->uid = md5($this->ip . $this->ua . $this->config->type);
    }

    /**
     * Determines if the current request should be rate limited
     *
     * @return bool True if request should be allowed or error occurred, false if rate limited
     */
    public function checkPassed(): bool
    {
        try {
            if (!$this->healthCheck()) {
                throw new \Exception('HEALTH_CHECK_FAILED');
            }

            if (!$this->cleanUp()) {
                throw new \Exception('CLEANUP_FAILED');
            }

            $uid_data = $this->selectUIDData();
            $record_found = false !== $uid_data;

            if ($record_found && !$uid_data->data_ok) {
                throw new \Exception('UID_DATA_INVALID');
            }

            if ($record_found && $this->isLocked($uid_data)) {
                return false; // Block here by limit exceeded
            }

            if ($record_found) {
                if (!$this->increment($uid_data)) {
                    throw new \Exception('INCREMENT_FAILED');
                }
            } else {
                $uid_data = new RateLimiterDto(
                    array(
                        'uid' => $this->uid,
                        'type' => $this->config->type,
                        'counter' => 1,
                        'last_call' => $this->current_ts,
                        'created_at' => $this->current_ts,
                        'ip' => $this->ip,
                        'ua' => $this->ua,
                    )
                );
                if (!$uid_data->data_ok) {
                    throw new \Exception('UID_DATA_INVALID__INSERT');
                }
                if (!$this->insert($uid_data)) {
                    throw new \Exception('INSERT_FAILED');
                }
            }
        } catch (\Exception $e) {
            $this->process_ok = false;
            $this->handleErrors($e->getMessage());
            return true;
        }

        return true;
    }

    /**
     * Checks if the current UID has exceeded the rate limit
     *
     * @param RateLimiterDto $uid_data
     * @return bool True if rate limited, false otherwise
     */
    protected function isLocked(RateLimiterDto $uid_data): bool
    {
        return $uid_data->data_ok && ($uid_data->counter > $this->config->limit);
    }

    /**
     * Performs basic health check on configuration
     *
     * @return bool True if configuration is valid, false otherwise
     */
    protected function healthCheck(): bool
    {
        if ($this->config->limit < 1) {
            return false;
        }
        if ($this->config->period < 1) {
            return false;
        }
        if ($this->config->type === null) {
            return false;
        }
        return true;
    }

    /**
     * Retrieves rate limit data for the current UID
     * Default implementation returns empty data
     *
     * @return RateLimiterDto|false Rate limit data or false if not found
     */
    protected function selectUIDData()
    {
        return new RateLimiterDto(array());
    }

    /**
     * Sets the IP address for the current request
     * Must be implemented by child classes
     *
     * @return void
     */
    abstract protected function setIP(): void;

    /**
     * Sets the IP address for the current request
     * Must be implemented by child classes
     *
     * @return void
     */
    abstract protected function setUA(): void;

    /**
     * Handles errors that occur during rate limiting
     * Must be implemented by child classes
     *
     * @param string $msg Error message
     * @return void
     */
    abstract protected function handleErrors(string $msg): void;

    /**
     * Increments the counter for an existing rate limit record
     * Must be implemented by child classes
     * @param RateLimiterDto $uid_data
     * @return bool True on success, false on failure
     */
    abstract protected function increment(RateLimiterDto $uid_data): bool;

    /**
     * Inserts a new rate limit record
     * Must be implemented by child classes
     * @param RateLimiterDto $uid_data
     * @return bool True on success, false on failure
     */
    abstract protected function insert(RateLimiterDto $uid_data): bool;

    /**
     * Cleans up expired rate limit records
     * Must be implemented by child classes
     *
     * @return bool True on success, false on failure
     */
    abstract protected function cleanUp(): bool;
}
