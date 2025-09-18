<?php

namespace Cleantalk\ApbctWP\RequestParameters;

/**
 * Class SubmitTimeHandler
 *
 * Handles the submission time logic for requests, including retrieving and setting
 * timestamps in requests and cookies. It also provides a mechanism to disable
 * calculation based on global settings.
 */
final class SubmitTimeHandler
{
    const DEFAULT_VALUE = null; // Default value to return when calculation is disabled or invalid
    const REQUEST_PARAM_NAME = 'apbct_timestamp'; // Name of the request parameter for the timestamp

    /**
     * Retrieves the time difference between the current time and the timestamp
     * stored in the request. Returns null if calculation is disabled or the timestamp
     * is invalid.
     *
     * @return int|null Time difference in seconds or null if invalid.
     */
    final public static function getFromRequest()
    {
        // Check if calculation is disabled globally
        if (self::isCalculationDisabled()) {
            return self::DEFAULT_VALUE;
        }

        // Retrieve the timestamp from the request
        $timestamp_from_request = (int) RequestParameters::get(self::REQUEST_PARAM_NAME, true);

        // Validate the timestamp and cookie test
        if ($timestamp_from_request === 0 || apbct_cookies_test() !== 1) {
            return self::DEFAULT_VALUE;
        }

        // Return the time difference
        return time() - $timestamp_from_request;
    }

    /**
     * Sets the current timestamp in the request and updates the cookie test value.
     * Does nothing if calculation is disabled.
     *
     * @param int $current_timestamp The current timestamp to set.
     * @param array $cookie_test_value Reference to the cookie test value array.
     *
     * @return void
     */
    final public static function setToRequest($current_timestamp, &$cookie_test_value)
    {
        // Check if calculation is disabled globally
        if (self::isCalculationDisabled()) {
            return;
        }

        // Set the timestamp in the request
        RequestParameters::set(self::REQUEST_PARAM_NAME, (string)$current_timestamp, true);

        // Update the cookie test value with the timestamp
        $cookie_test_value['cookies_names'][] = self::REQUEST_PARAM_NAME;
        if (isset($cookie_test_value['check_value']) && is_string($cookie_test_value['check_value'])) {
            $cookie_test_value['check_value'] .= (string)$current_timestamp;
        } else {
            $cookie_test_value['check_value'] = (string)$current_timestamp;
        }
    }

    /**
     * Checks if the calculation of submission time is disabled based on global settings.
     *
     * @return bool True if calculation is disabled, false otherwise.
     */
    final public static function isCalculationDisabled()
    {
        global $apbct;

        // Return the value of the bot detector setting
        return (bool)$apbct->settings['data__bot_detector_enabled'];
    }
}
