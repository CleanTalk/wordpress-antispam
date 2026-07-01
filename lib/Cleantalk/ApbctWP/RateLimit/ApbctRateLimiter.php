<?php

namespace Cleantalk\ApbctWP\RateLimit;

use Cleantalk\Common\RateLimiter\RateLimiter;
use Cleantalk\Common\RateLimiter\RateLimiterDto;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\Variables\Server;

/**
 * WordPress-specific implementation of the rate limiter for Anti-Spam plugin
 *
 * @package Cleantalk\ApbctWP\RateLimit
 */
class ApbctRateLimiter extends RateLimiter
{
    /**
     * Sets the IP address from WordPress environment
     *
     * @return void
     */
    protected function setIP(): void
    {
        $this->ip = (string) Helper::ipGet('remote_addr', true);
    }

    /**
     * Sets the User-Agent from server variables
     *
     * @return void
     */
    protected function setUA(): void
    {
        $this->ua = Server::getString('HTTP_USER_AGENT');
    }

    /**
     * Override UID to be per-IP only (ignoring UA),
     * so an attacker cannot bypass the limit by rotating User-Agent.
     *
     * @return void
     */
    protected function setUID(): void
    {
        $this->uid = md5($this->ip . $this->config->type);
    }

    /**
     * Performs health check to ensure database table exists and config is valid
     *
     * @return bool
     */
    protected function healthCheck(): bool
    {
        global $wpdb;

        if ( ! defined('APBCT_TBL_RATE_LIMITS') ) {
            return false;
        }

        $table_name = APBCT_TBL_RATE_LIMITS;
        $sql = $wpdb->prepare('SHOW TABLES LIKE %s', $table_name);
        $table_ok = ! empty($wpdb->get_var($sql));

        return $table_ok && parent::healthCheck();
    }

    /**
     * Handles rate limiter errors (silent fail - allow request through)
     *
     * @param string $msg Error message
     * @return void
     */
    protected function handleErrors(string $msg): void
    {
        // Silent fail - errors are handled by process_ok flag in the caller
    }

    /**
     * Retrieves rate limit data for the current UID from database
     *
     * @return RateLimiterDto|false
     */
    protected function selectUIDData()
    {
        global $wpdb;

        $table_name = APBCT_TBL_RATE_LIMITS;

        $sql = $wpdb->prepare(
            "SELECT uid, type, ip, ua, counter, last_call, created_at FROM {$table_name} WHERE uid = %s LIMIT 1",
            $this->uid
        );

        $result = $wpdb->get_row($sql, ARRAY_A);

        return ! empty($result) ? new RateLimiterDto($result) : false;
    }

    /**
     * Inserts a new rate limit record
     *
     * @param RateLimiterDto $uid_data
     * @return bool
     */
    protected function insert(RateLimiterDto $uid_data): bool
    {
        global $wpdb;

        $table_name = APBCT_TBL_RATE_LIMITS;

        $sql = $wpdb->prepare(
            "INSERT INTO {$table_name} (uid, type, ip, ua, counter, last_call, created_at)
             VALUES (%s, %s, %s, %s, 1, %d, %d)
             ON DUPLICATE KEY UPDATE last_call = %d, counter = counter + 1",
            $uid_data->uid,
            $uid_data->type,
            $uid_data->ip,
            $uid_data->ua,
            $uid_data->last_call,
            $uid_data->created_at,
            $uid_data->last_call
        );

        return false !== $wpdb->query($sql);
    }

    /**
     * Increments the counter for an existing rate limit record.
     * Resets counter if the period has expired.
     *
     * @param RateLimiterDto $uid_data
     * @return bool
     */
    protected function increment(RateLimiterDto $uid_data): bool
    {
        global $wpdb;

        $table_name = APBCT_TBL_RATE_LIMITS;

        $is_expired = ($this->current_ts - $uid_data->created_at) > $this->config->period;

        $uid_data->counter = $is_expired ? 1 : $uid_data->counter + 1;
        $uid_data->created_at = $is_expired ? $this->current_ts : $uid_data->created_at;
        $uid_data->last_call = $this->current_ts;

        $sql = $wpdb->prepare(
            "UPDATE {$table_name} SET counter = %d, last_call = %d, created_at = %d WHERE uid = %s",
            $uid_data->counter,
            $uid_data->last_call,
            $uid_data->created_at,
            $uid_data->uid
        );

        return false !== $wpdb->query($sql);
    }

    /**
     * Removes expired rate limit records from the database
     *
     * @return bool
     */
    protected function cleanUp(): bool
    {
        global $wpdb;

        $table_name = APBCT_TBL_RATE_LIMITS;
        $threshold = $this->current_ts - ($this->config->period + 10);

        $sql = $wpdb->prepare(
            "DELETE FROM {$table_name} WHERE created_at < %d AND type = %s",
            $threshold,
            $this->config->type
        );

        return false !== $wpdb->query($sql);
    }
}
