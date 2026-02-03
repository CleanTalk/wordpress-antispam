<?php

namespace Cleantalk\ApbctWP\HTTP;

use Cleantalk\Common\HTTP\Request as CommonRequest;

/**
 * Class HTTPMultiRequestService
 *
 * Service for managing multiple HTTP requests with contract-based approach.
 * Handles batch HTTP requests, validates responses, tracks success/failure states,
 * and provides file writing capabilities for downloaded content.
 *
 * @package Cleantalk\ApbctWP\HTTP
 * @since 1.0.0
 */
class HTTPMultiRequestService
{
    /**
     * Array of HTTP request contracts
     *
     * @var HTTPRequestContract[]
     */
    public $contracts = [];

    /**
     * Indicates whether the multi-request process completed
     *
     * @var bool
     */
    public $process_done = false;

    /**
     * Suggested batch size reduction for retry attempts
     * False if no reduction suggested, integer value if reduction recommended
     *
     * @var int|false
     */
    public $suggest_batch_reduce_to = false;

    /**
     * Error message if contract processing failed
     *
     * @var null|string
     */
    public $error_msg = null;

    /**
     * Initializes and executes multi-request contract for given URLs
     *
     * Resets service state, prepares contracts for each URL, executes HTTP requests,
     * and fills contracts with response data. This is the main entry point for batch processing.
     *
     * @param array $urls List of URLs to process
     *
     * @return $this Returns self for method chaining
     */
    public function setMultiContract($urls)
    {
        // Reset service state for new batch
        $this->process_done = false;
        $this->suggest_batch_reduce_to = false;
        $this->contracts = [];
        $this->error_msg = null;

        // Prepare individual contracts for each URL
        $this->prepareContracts($urls);

        // Execute all HTTP requests if contracts are valid
        $this->executeMultiContract();

        return $this;
    }

    /**
     * Prepares HTTP request contracts from URLs array
     *
     * Validates URLs and creates HTTPRequestContract instance for each valid URL.
     * Sets error message and stops processing if validation fails.
     *
     * @param array $urls Array of URLs to prepare contracts for
     *
     * @return void
     */
    public function prepareContracts(array $urls)
    {
        // Validate URLs array is not empty
        if (empty($urls)) {
            $this->error_msg = __CLASS__ . ': URLS SHOULD BE NOT EMPTY';
            return;
        }

        // Create contract for each URL with validation
        foreach ($urls as $url) {
            // Ensure each URL is a string
            if (!is_string($url)) {
                $this->error_msg = __CLASS__ . ': SINGLE URL SHOULD BE A STRING';
                $this->contracts = [];
                return;
            }
            $this->contracts[] = new HTTPRequestContract($url);
        }
    }

    /**
     * Executes multi-request and fills contracts with response data
     *
     * Sends HTTP requests for all prepared contracts and processes the results.
     * Only executes if there are valid URLs to process.
     *
     * @return void
     */
    public function executeMultiContract()
    {
        // Execute requests only if contracts contain URLs
        if (!empty($this->getAllURLs())) {
            $http_multi_result = $this->sendRequests($this->getAllURLs());
            $this->fillMultiContract($http_multi_result);
        }
    }

    /**
     * Fills contracts with HTTP response data and validates results
     *
     * Processes multi-request results, validates response content, updates contract states,
     * and suggests batch size reduction if some requests failed.
     *
     * @param array|bool $http_multi_result Response data from multi-request or false on failure
     *
     * @return $this Returns self for method chaining
     */
    public function fillMultiContract($http_multi_result)
    {
        // Handle HTTP request error
        if (!empty($http_multi_result['error'])) {
            $this->error_msg = __CLASS__ . ': HTTP_MULTI_RESULT ERROR' . $http_multi_result['error'];
            return $this;
        }

        // Validate result is an array
        if (!is_array($http_multi_result)) {
            $this->error_msg = __CLASS__ . ': HTTP_MULTI_RESULT INVALID';
            return $this;
        }

        // Fill each contract with corresponding response data
        foreach ($this->contracts as $contract) {
            if (isset($http_multi_result[$contract->url])) {
                $contract_content = $http_multi_result[$contract->url];

                // Validate response content is string
                if (!is_string($contract_content)) {
                    $contract->error_msg = __CLASS__ . ': SINGLE CONTRACT_CONTENT SHOULD BE A STRING';
                    continue;
                }

                // Validate response content is not empty
                if (empty($contract_content)) {
                    $contract->error_msg = __CLASS__ . ': SINGLE CONTRACT_CONTENT SHOULD BE NOT EMPTY';
                    continue;
                }

                // Mark contract as successful with content
                $contract->content = $contract_content;
                $contract->success = true;
            }
        }

        // Suggest batch size reduction if some contracts failed
        if (!$this->allContractsCompleted()) {
            // Reduce to number of successful requests, minimum 2
            $this->suggest_batch_reduce_to = !empty($this->getSuccessURLs())
                ? count($this->getSuccessURLs())
                : 2;
        }

        // Mark processing as completed
        $this->process_done = true;
        return $this;
    }

    /**
     * Checks if all contracts completed successfully
     *
     * @return bool True if all contracts succeeded, false if any failed
     */
    private function allContractsCompleted()
    {
        // Extract success flags from all contracts
        $flags = array_map(function ($contract) {
            return $contract->success;
        }, $this->contracts);

        // Return true only if no false flags exist
        return !in_array(false, $flags, true);
    }

    /**
     * Sends HTTP requests to multiple URLs using CommonRequest (cURL)
     *
     * Uses CommonRequest directly (bypassing WP HTTP API) to ensure per-URL
     * error tracking is available. This enables adaptive batch size reduction
     * when individual downloads fail.
     *
     * @param array $urls Array of URLs to request
     *
     * @return array|bool Response data array or false on failure
     */
    public function sendRequests($urls)
    {
        $http = new CommonRequest();

        // Configure and execute multi-request
        $http->setUrl($urls)
            ->setPresets('get');
        return $http->request();
    }

    /**
     * Collects and formats error messages from failed contracts
     *
     * Returns formatted string with URL and error message pairs for all failed contracts.
     * Used for debugging and error reporting.
     *
     * @return false|string False if no errors, comma-separated error string otherwise
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getContractsErrors()
    {
        $result = [];

        // Collect errors from failed contracts
        foreach ($this->contracts as $contract) {
            if (!$contract->success && !empty($contract->error_msg)) {
                // Format: [url]:[error_message]
                $result[] = '[' . esc_url($contract->url) . ']:[' . esc_html($contract->error_msg) . ']';
            }
        }

        return empty($result) ? false : implode(',', $result);
    }

    /**
     * Extracts URLs from all contracts
     *
     * @return array Array of URLs from all contracts
     */
    public function getAllURLs()
    {
        return array_map(function ($contract) {
            return $contract->url;
        }, $this->contracts);
    }

    /**
     * Returns URLs of failed contracts
     *
     * Contract is considered failed if success flag is false or content is empty.
     *
     * @return array Array of URLs that failed to download or have empty content
     */
    public function getFailedURLs()
    {
        $result = [];
        foreach ($this->contracts as $contract) {
            // Failed if not successful or content is empty
            if (!$contract->success || empty($contract->content)) {
                $result[] = $contract->url;
            }
        }
        return $result;
    }

    /**
     * Returns URLs of successful contracts
     *
     * Contract is considered successful if success flag is true and content is not empty.
     *
     * @return array Array of URLs that successfully downloaded with non-empty content
     */
    public function getSuccessURLs()
    {
        $result = [];
        foreach ($this->contracts as $contract) {
            // Successful only if both success flag and content exist
            if ($contract->success && !empty($contract->content)) {
                $result[] = $contract->url;
            }
        }
        return $result;
    }

    /**
     * Writes content from successful contracts to files
     *
     * Extracts filename from URL and writes contract content to specified directory.
     * Only processes contracts that completed successfully. Validates directory permissions
     * before writing and handles write errors gracefully.
     *
     * @param string $write_to_dir Target directory path (must exist and be writable)
     *
     * @return array|string Array of successfully written URLs on success, error message string on failure
     */
    public function writeSuccessURLsContent($write_to_dir)
    {
        $written_urls = [];
        try {
            // Validate target directory exists and is writable
            if (!is_dir($write_to_dir) || !is_writable($write_to_dir)) {
                throw new \Exception('CAN NOT WRITE TO DIRECTORY: ' . $write_to_dir);
            }

            // Write content from each successful contract
            foreach ($this->contracts as $single_contract) {
                if ($single_contract->success) {
                    // Extract filename from URL and build full path
                    $file_name = $write_to_dir . self::getFilenameFromUrl($single_contract->url);

                    // Write content to file
                    $write_result = $this->writeFile($file_name, $single_contract->content);

                    // Check for write failure
                    if (false === $write_result) {
                        throw new \Exception('CAN NOT WRITE TO FILE: ' . $file_name);
                    }

                    // Track successfully written URL
                    $written_urls[] = $single_contract->url;
                }
            }
        } catch (\Exception $e) {
            // Return error message on any exception
            return $e->getMessage();
        }

        return $written_urls;
    }

    /**
     * Extracts filename with extension from URL
     *
     * Parses URL to extract filename and extension components.
     * Example: "https://example.com/path/file.gz" -> "file.gz"
     *
     * @param string $url Full URL to extract filename from
     *
     * @return string Filename with extension
     */
    private static function getFilenameFromUrl($url)
    {
        return pathinfo($url, PATHINFO_FILENAME) . '.' . pathinfo($url, PATHINFO_EXTENSION);
    }

    /**
     * Wrapper for file_put_contents.
     * @param $filename
     * @param $data
     * @return false|int
     */
    public function writeFile($filename, $data)
    {
        return @file_put_contents($filename, $data);
    }
}
