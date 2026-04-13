<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\ApbctWP\HTTP\HTTPMultiRequestService;
use Cleantalk\Common\TT;

/**
 * Class SFWFilesDownloader
 *
 * Handles downloading of SpamFireWall data files from remote servers.
 * Manages batch processing, retry logic, and error handling for file downloads.
 */
class SFWFilesDownloader
{
    /**
     * HTTP multi-request service instance
     *
     * @var HTTPMultiRequestService
     */
    private $http_multi_request_service;

    /**
     * @var string
     */
    private $deafult_error_prefix;

    /**
     * @var string
     */
    private $deafult_error_content = 'UNKNOWN ERROR';

    /**
     * SFWFilesDownloader constructor
     *
     * @param HTTPMultiRequestService|null $service Optional. Custom service instance for dependency injection.
     * @throws \InvalidArgumentException If service is not an instance of HTTPMultiRequestService
     */
    public function __construct($service = null)
    {
        $this->deafult_error_prefix = basename(__CLASS__) . ': ';

        if ($service !== null && !$service instanceof HTTPMultiRequestService) {
            throw new \InvalidArgumentException(
                'Service must be an instance of ' . HTTPMultiRequestService::class
            );
        }

        $this->http_multi_request_service = $service ?: new HTTPMultiRequestService();
    }

    /**
     * Downloads SFW data files from provided URLs with batch processing and retry logic
     *
     * Downloads files in batches to avoid server overload. Automatically retries failed downloads
     * with reduced batch size if necessary. Validates write permissions and URL format before processing.
     *
     * @param array|mixed $all_urls List of URLs to download files from
     * @param bool $direct_update Optional. If true, returns boolean result. If false, returns stage info array.
     * @param int sleep Pause in seconds before multi contracts run, default is 3
     *
     * @return true|array True on success (direct update mode), or array with 'next_stage' key,
     *                    or array with 'error' key on failure, or array with 'update_args' for retry.
     */
    public function downloadFiles($all_urls, $direct_update = false, $sleep = 3)
    {
        global $apbct;

        // Delay to prevent server overload
        sleep($sleep);

        // Validate write permissions for update folder
        if ( ! is_writable($apbct->fw_stats['updating_folder']) ) {
            return $this->responseStopUpdate('SFW UPDATE FOLDER IS NOT WRITABLE.');
        }

        // Validate URLs parameter type
        if ( ! is_array($all_urls) ) {
            return $this->responseStopUpdate('URLS LIST SHOULD BE AN ARRAY');
        }

        // Remove duplicates and reset array keys to sequential integers
        $all_urls = array_values(array_unique($all_urls));

        // Get current batch size from settings or default
        $work_batch_size = SFWUpdateHelper::getSFWFilesBatchSize();

        // Initialize batch processing variables
        $total_urls = count($all_urls);
        $batches = ceil($total_urls / $work_batch_size);
        $download_again = [];
        $written_urls = [];

        // Get or set default batch size for retry attempts
        $on_repeat_batch_size = !empty($apbct->data['sfw_update__batch_size'])
            ? TT::toInt($apbct->data['sfw_update__batch_size'])
            : 10;

        // Process URLs in batches
        for ($i = 0; $i < $batches; $i++) {
            // Extract current batch of URLs
            $current_batch_urls = array_slice($all_urls, $i * $work_batch_size, $work_batch_size);

            if (!empty($current_batch_urls)) {
                // Execute multi-request for current batch
                $multi_request_contract = $this->http_multi_request_service->setMultiContract($current_batch_urls);

                // Critical error: contract processing failed, stop update immediately
                if (!$multi_request_contract->process_done) {
                    $error = !empty($multi_request_contract->error_msg) ? $multi_request_contract->error_msg : 'UNKNOWN ERROR';
                    return $this->responseStopUpdate($error);
                }

                // Handle failed downloads in this batch
                if (!empty($multi_request_contract->getFailedURLs())) {
                    // Reduce batch size for retry if service suggests it
                    if ($multi_request_contract->suggest_batch_reduce_to) {
                        $on_repeat_batch_size = min($on_repeat_batch_size, $multi_request_contract->suggest_batch_reduce_to);
                    }
                    // Collect failed URLs for retry
                    $download_again = array_merge($download_again, $multi_request_contract->getFailedURLs());
                }

                // Write successfully downloaded content to files
                $write_result = $multi_request_contract->writeSuccessURLsContent($apbct->fw_stats['updating_folder']);

                // File write error occurred, stop update
                if (is_string($write_result)) {
                    return $this->responseStopUpdate($write_result);
                }

                // Track successfully written URLs
                $written_urls = array_merge($written_urls, $write_result);
            }
        }

        // Some downloads failed, schedule retry with adjusted batch size
        if (!empty($download_again)) {
            $apbct->fw_stats['multi_request_batch_size'] = $on_repeat_batch_size;
            $apbct->save('data');
            return $this->responseRepeatStage('FILES DOWNLOAD NOT COMPLETED, TRYING AGAIN', $download_again);
        }

        // Verify all URLs were successfully downloaded and written
        if (empty(array_diff($all_urls, $written_urls))) {
            return $this->responseSuccess($direct_update);
        }

        // Download incomplete with no retry - collect error information
        $last_contract_errors = isset($multi_request_contract) && $multi_request_contract->getContractsErrors()
            ? $multi_request_contract->getContractsErrors()
            : 'no known contract errors';

        $error = 'FILES DOWNLOAD NOT COMPLETED - STOP UPDATE, ERRORS: ' . $last_contract_errors;
        return $this->responseStopUpdate($error);
    }

    /**
     * Creates error response to stop the update process
     *
     * @param string $message Error message describing why update was stopped
     *
     * @return array Error response array with 'error' key
     */
    private function responseStopUpdate($message): array
    {
        $message = is_string($message) ? $message : $this->deafult_error_content;
        $message = $this->deafult_error_prefix . $message;
        return [
            'error' => $message
        ];
    }

    /**
     * Creates response to repeat current stage with modified arguments
     *
     * Used when downloads partially failed and should be retried with
     * potentially reduced batch size or different parameters.
     *
     * @param string $message Descriptive message about why stage needs repeating
     * @param array $args Arguments for retry attempt (typically failed URLs)
     *
     * @return array Response array with 'error' message and 'update_args' for retry
     */
    private function responseRepeatStage($message, $args): array
    {
        $args = is_array($args) ? $args : [];
        $message = is_string($message) ? $message : $this->deafult_error_content;
        $message = $this->deafult_error_prefix . $message;
        return [
            'error' => $message,
            'update_args' => [
                'args' => $args
            ],
        ];
    }

    /**
     * Creates success response to proceed to next stage or complete update
     *
     * @param bool $direct_update If true, returns simple boolean. If false, returns stage transition array.
     *
     * @return true|array True for direct update mode, or array with 'next_stage' key for staged updates
     */
    private function responseSuccess($direct_update)
    {
        return $direct_update ? true : [
            'next_stage' => array(
                'name' => 'apbct_sfw_update__create_tables'
            )
        ];
    }
}
