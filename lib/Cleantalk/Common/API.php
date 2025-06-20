<?php

namespace Cleantalk\Common;

use Cleantalk\Common\HTTP\Request;

/**
 * CleanTalk API class.
 * Mostly contains wrappers for API methods. Check and send methods.
 * Compatible with any CMS.
 *
 * @version       4.0
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see           https://github.com/CleanTalk/php-antispam
 */
class API
{
    /* Default params  */
    public static $api_url = 'https://api.cleantalk.org';
    const AGENT = 'ct-api-3.2';

    /**
     * Wrapper for 2s_blacklists_db API method.
     * Gets data for SpamFireWall.
     *
     * @param string $api_key
     * @param null|string $out Data output type (JSON or file URL)
     * @param string $version API method version
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodGet2sBlacklistsDb($api_key, $out = null, $version = '1_0', $common_lists = null)
    {
        $request = array(
            'method_name'  => '2s_blacklists_db',
            'auth_key'     => $api_key,
            'out'          => $out,
            'version'      => $version,
            'common_lists' => $common_lists,
        );

        return static::sendRequest($request);
    }

    /**
     * Wrapper for get_api_key API method.
     * Gets Access key automatically.
     *
     * @param string $product_name Type of product
     * @param string $email Website admin email
     * @param string $website Website host
     * @param string $platform Website platform
     * @param string|null $timezone
     * @param string|null $language
     * @param string|null $user_ip
     * @param bool $wpms
     * @param bool $white_label
     * @param string $hoster_api_key
     *
     * @return array|bool
     */
    public static function methodGetApiKey(
        $product_name,
        $email,
        $website,
        $platform,
        $timezone = null,
        $language = null,
        $user_ip = null,
        $wpms = false,
        $white_label = false,
        $hoster_api_key = '',
        $email_filtered = false
    ) {
        $request = array(
            'method_name'          => 'get_api_key',
            'product_name'         => $product_name,
            'email'                => $email,
            'website'              => $website,
            'platform'             => $platform,
            'timezone'             => $timezone,
            'http_accept_language' => $language,
            'user_ip'              => $user_ip,
            'wpms_setup'           => $wpms,
            'hoster_whitelabel'    => $white_label,
            'hoster_api_key'       => $hoster_api_key,
            'email_filtered'       => $email_filtered
        );

        return static::sendRequest($request);
    }

    /**
     * Wrapper for get_antispam_report API method.
     * Gets spam report.
     *
     * @param string $host website host
     * @param integer $period report days
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodGetAntispamReport($host, $period = 1)
    {
        $request = array(
            'method_name' => 'get_antispam_report',
            'hostname'    => $host,
            'period'      => $period
        );

        return static::sendRequest($request);
    }

    /**
     * Wrapper for get_antispam_report_breif API method.
     * Ggets spam statistics.
     *
     * @param string $api_key
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodGetAntispamReportBreif($api_key)
    {
        $request = array(
            'method_name' => 'get_antispam_report_breif',
            'auth_key'    => $api_key,
        );

        return static::sendRequest($request);
    }

    /**
     * Wrapper for notice_paid_till API method.
     * Gets information about renew notice.
     *
     * @param string $api_key Access key
     * @param string $path_to_cms Website URL
     * @param string $product_name
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodNoticePaidTill(
        $api_key,
        $path_to_cms,
        $product_name = 'antispam'
    ) {
        $request = array(
            'method_name' => 'notice_paid_till',
            'path_to_cms' => $path_to_cms,
            'auth_key'    => $api_key,
        );

        if (self::getProductId($product_name)) {
            $request['product_id'] = self::getProductId($product_name);
        }

        return static::sendRequest($request);
    }

    /**
     * Wrapper for notice_banners API method.
     * Gets notice banners.
     *
     * @param string $api_key
     *
     * @return array|bool
     */
    public static function getNoticeBanners($api_key)
    {
        $request = array(
            'method_name' => 'notice_banners',
            'auth_key'    => $api_key,
        );

        return static::sendRequest($request);
    }

    /**
     * Wrapper for ip_info API method.
     * Gets IP country.
     *
     * @param string $data
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodIpInfo($data)
    {
        $request = array(
            'method_name' => 'ip_info',
            'data'        => $data
        );

        return static::sendRequest($request);
    }

    /**
     * Wrapper for spam_check_cms API method.
     * Checks IP|email via CleanTalk's database.
     *
     * @param string $api_key
     * @param array $data
     * @param null|string $date
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodSpamCheckCms($api_key, $data, $date = null)
    {
        $request = array(
            'method_name' => 'spam_check_cms',
            'auth_key'    => $api_key,
            'data'        => is_array($data) ? implode(',', $data) : $data,
        );

        if ($date) {
            $request['date'] = $date;
        }

        return static::sendRequest($request, 20);
    }

    /**
     * Wrapper for notice_paid_till API method.
     * Gets information about renew notice.
     *
     * @param string $api_key Access key
     * @param string $path_to_cms Website URL
     * @param string $product_name
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodEmailCheck($email, $cache_only = true)
    {
        $request = array(
            'method_name' => 'email_check',
            'cache_only'  => $cache_only ? '1' : '0',
            'email'       => $email,
        );

        return static::sendRequest($request);
    }

    /**
     * Wrapper for spam_check API method.
     * Checks IP|email via CleanTalk's database.
     *
     * @param string $api_key
     * @param array $data
     * @param null|string $date
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodSpamCheck($api_key, $data, $date = null)
    {
        $request = array(
            'method_name' => 'spam_check',
            'auth_key'    => $api_key,
            'data'        => is_array($data) ? implode(',', $data) : $data,
        );

        if ($date) {
            $request['date'] = $date;
        }

        return static::sendRequest($request);
    }

    /**
     * Wrapper for sfw_logs API method.
     * Sends SpamFireWall logs to the cloud.
     *
     * @param string $api_key
     * @param array $data
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodSfwLogs($api_key, $data)
    {
        $request = array(
            'auth_key'    => $api_key,
            'method_name' => 'sfw_logs',
            'data'        => json_encode($data),
            'rows'        => count($data),
            'timestamp'   => time()
        );

        $request['data'] = str_replace('"EMPTY_ASSOCIATIVE_ARRAY"', '{}', $request['data']);

        return static::sendRequest($request);
    }

    /**
     * Wrapper for security_logs API method.
     * Sends security logs to the cloud.
     *
     * @param string $api_key
     * @param array $data
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodSecurityLogs($api_key, $data)
    {
        $request = array(
            'auth_key'    => $api_key,
            'method_name' => 'security_logs',
            'timestamp'   => current_time('timestamp'),
            'data'        => json_encode($data),
            'rows'        => count($data),
        );

        return static::sendRequest($request);
    }

    /**
     * Wrapper for security_logs API method.
     * Sends Securitty Firewall logs to the cloud.
     *
     * @param string $api_key
     * @param array $data
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodSecurityLogsSendFWData($api_key, $data)
    {
        $request = array(
            'auth_key'    => $api_key,
            'method_name' => 'security_logs',
            'timestamp'   => current_time('timestamp'),
            'data_fw'     => json_encode($data),
            'rows_fw'     => count($data),
        );

        return static::sendRequest($request);
    }

    /**
     * Wrapper for security_logs API method.
     * Sends empty data to the cloud to syncronize version.
     *
     * @param string $api_key
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodSecurityLogsFeedback($api_key)
    {
        $request = array(
            'auth_key'    => $api_key,
            'method_name' => 'security_logs',
            'data'        => '0',
        );

        return static::sendRequest($request);
    }

    /**
     * Wrapper for security_firewall_data API method.
     * Gets Securitty Firewall data to write to the local database.
     *
     * @param string $api_key
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodSecurityFirewallData($api_key)
    {
        $request = array(
            'auth_key'    => $api_key,
            'method_name' => 'security_firewall_data',
        );

        return static::sendRequest($request);
    }

    /**
     * Wrapper for security_firewall_data_file API method.
     * Gets URI with security firewall data in .csv.gz file to write to the local database.
     *
     * @param string $api_key
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodSecurityFirewallDataFile($api_key)
    {
        $request = array(
            'auth_key'    => $api_key,
            'method_name' => 'security_firewall_data_file',
        );

        return static::sendRequest($request);
    }

    /**
     * Wrapper for security_linksscan_logs API method.
     * Send data to the cloud about scanned links.
     *
     * @param string $api_key
     * @param string $scan_time Datetime of scan
     * @param bool $scan_result
     * @param int $links_total
     * @param array $links_list
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodSecurityLinksscanLogs(
        $api_key,
        $scan_time,
        $scan_result,
        $links_total,
        $links_list
    ) {
        $request = array(
            'auth_key'          => $api_key,
            'method_name'       => 'security_linksscan_logs',
            'started'           => $scan_time,
            'result'            => $scan_result,
            'total_links_found' => $links_total,
            'links_list'        => $links_list,
        );

        return static::sendRequest($request);
    }

    /**
     * Wrapper for security_mscan_logs API method.
     * Sends result of file scan to the cloud.
     *
     * @param string $api_key
     * @param int $service_id
     * @param string $scan_time Datetime of scan
     * @param bool $scan_result
     * @param int $scanned_total
     * @param array $modified List of modified files with details
     * @param array $unknown List of modified files with details
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodSecurityMscanLogs(
        $api_key,
        $service_id,
        $scan_time,
        $scan_result,
        $scanned_total,
        $modified,
        $unknown
    ) {
        $request = array(
            'method_name'      => 'security_mscan_logs',
            'auth_key'         => $api_key,
            'service_id'       => $service_id,
            'started'          => $scan_time,
            'result'           => $scan_result,
            'total_core_files' => $scanned_total,
        );

        if ( ! empty($modified)) {
            $request['failed_files']      = json_encode($modified);
            $request['failed_files_rows'] = count($modified);
        }
        if ( ! empty($unknown)) {
            $request['unknown_files']      = json_encode($unknown);
            $request['unknown_files_rows'] = count($unknown);
        }

        return static::sendRequest($request);
    }

    /**
     * Wrapper for security_mscan_files API method.
     * Sends file to the cloud for analysis.
     *
     * @param string $api_key
     * @param string $file_path Path to the file
     * @param array $file File itself
     * @param string $file_md5 MD5 hash of file
     * @param array $weak_spots List of weak spots found in file
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodSecurityMscanFiles(
        $api_key,
        $file_path,
        $file,
        $file_md5,
        $weak_spots
    ) {
        $request = array(
            'method_name'    => 'security_mscan_files',
            'auth_key'       => $api_key,
            'path_to_sfile'  => $file_path,
            'attached_sfile' => $file,
            'md5sum_sfile'   => $file_md5,
            'dangerous_code' => $weak_spots,
        );

        return static::sendRequest($request);
    }

    /**
     * Wrapper for get_antispam_report API method.
     * Function gets spam domains report.
     *
     * @param string $api_key
     * @param array|string|mixed $data
     * @param string $date
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodBacklinksCheckCms($api_key, $data, $date = null)
    {
        $request = array(
            'method_name' => 'backlinks_check_cms',
            'auth_key'    => $api_key,
            'data'        => is_array($data) ? implode(',', $data) : $data,
        );

        if ($date) {
            $request['date'] = $date;
        }

        return static::sendRequest($request);
    }

    /**
     * Wrapper for get_antispam_report API method.
     * Function gets spam domains report
     *
     * @param string $api_key
     * @param array $logs
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodSecurityBackendLogs($api_key, $logs)
    {
        $request = array(
            'method_name' => 'security_backend_logs',
            'auth_key'    => $api_key,
            'logs'        => json_encode($logs),
            'total_logs'  => count($logs),
        );

        return static::sendRequest($request);
    }

    /**
     * Wrapper for get_antispam_report API method.
     * Sends data about auto repairs
     *
     * @param string $api_key
     * @param bool $repair_result
     * @param string $repair_comment
     * @param        $repaired_processed_files
     * @param        $repaired_total_files_proccessed
     * @param        $backup_id
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodSecurityMscanRepairs(
        $api_key,
        $repair_result,
        $repair_comment,
        $repaired_processed_files,
        $repaired_total_files_proccessed,
        $backup_id
    ) {
        $request = array(
            'method_name'                  => 'security_mscan_repairs',
            'auth_key'                     => $api_key,
            'repair_result'                => $repair_result,
            'repair_comment'               => $repair_comment,
            'repair_processed_files'       => json_encode($repaired_processed_files),
            'repair_total_files_processed' => $repaired_total_files_proccessed,
            'backup_id'                    => $backup_id,
            'mscan_log_id'                 => 1,
        );

        return static::sendRequest($request);
    }

    /**
     * Wrapper for get_antispam_report API method.
     * Force server to update checksums for specific plugin\theme
     *
     * @param string $api_key
     * @param string $plugins_and_themes_to_refresh
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodRequestChecksums($api_key, $plugins_and_themes_to_refresh)
    {
        $request = array(
            'method_name' => 'request_checksums',
            'auth_key'    => $api_key,
            'data'        => $plugins_and_themes_to_refresh
        );

        return static::sendRequest($request);
    }

    /**
     * Settings templates get API method wrapper
     *
     * @param string $api_key
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodServicesTemplatesGet($api_key, $product_name = 'antispam')
    {
        $request = array(
            'method_name'        => 'services_templates_get',
            'auth_key'           => $api_key,
            'search[product_id]' => self::getProductId($product_name),
        );

        return static::sendRequest($request);
    }

    /**
     * Settings templates add API method wrapper
     *
     * @param string $api_key
     * @param null|string $template_name
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodServicesTemplatesAdd(
        $api_key,
        $template_name = null,
        $options = '',
        $product_name = 'antispam'
    ) {
        $request = array(
            'method_name'        => 'services_templates_add',
            'auth_key'           => $api_key,
            'name'               => $template_name,
            'options_site'       => $options,
            'search[product_id]' => self::getProductId($product_name),
        );

        return static::sendRequest($request);
    }

    /**
     * Settings templates add API method wrapper
     *
     * @param string $api_key
     * @param int $template_id
     * @param string $options
     * @param string $product_name
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodServicesTemplatesUpdate(
        $api_key,
        $template_id,
        $options = '',
        $product_name = 'antispam'
    ) {
        $request = array(
            'method_name'        => 'services_templates_update',
            'auth_key'           => $api_key,
            'template_id'        => $template_id,
            'name'               => null,
            'options_site'       => $options,
            'search[product_id]' => self::getProductId($product_name),
        );

        return static::sendRequest($request);
    }

    /**
     *
     *
     * @param string $user_token
     * @param string $service_id
     * @param string $ip
     * @param string $service_type
     * @param int $product_id
     * @param int $record_type
     * @param string $note Description text
     * @param string $status allow|deny
     * @param string $expired Date Y-m-d H:i:s
     *
     * @return array|bool|bool[]|string[]
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodPrivateListAdd(
        $user_token,
        $service_id,
        $ip,
        $service_type,
        $product_id,
        $record_type,
        $note,
        $status,
        $expired
    ) {
        $request = array(
            'method_name'  => 'private_list_add',
            'user_token'   => $user_token,
            'service_id'   => $service_id,
            'records'      => $ip,
            'service_type' => $service_type,
            'product_id'   => $product_id,
            'record_type'  => $record_type,
            'note'         => $note,
            'status'       => $status,
            'expired'      => $expired,
        );

        return static::sendRequest($request);
    }

    /**
     * Sending of local settings API method wrapper
     *
     * @param string $api_key
     * @param string $hostname
     * @param string $settings
     *
     * @return array|bool
     *
     * @psalm-suppress PossiblyUnusedMethod
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public static function methodSendLocalSettings(
        $api_key,
        $hostname,
        $settings
    ) {
        $request = array(
            'method_name' => 'service_update_local_settings',
            'auth_key' => $api_key,
            'hostname' => $hostname,
            'settings' => $settings
        );

        return static::sendRequest($request);
    }

    public static function methodUserDataUpdate($user_token, $user_data)
    {
        $request = array(
            'method_name' => 'user_data_update',
            'user_token'  => $user_token,
            'user_data'   => $user_data,
        );

        return static::sendRequest($request);
    }

    private static function getProductId($product_name)
    {
        $product_id = null;
        $product_id = $product_name === 'antispam' ? 1 : $product_id;
        $product_id = $product_name === 'security' ? 4 : $product_id;

        return $product_id;
    }

    /**
     * Function sends raw request to API server
     *
     * @param array $data to send
     * @param integer $timeout timeout in seconds
     *
     * @return array|bool
     */
    public static function sendRequest($data, $timeout = 10)
    {
        // Possibility to switch agent version
        $data['agent'] = ! empty($data['agent'])
            ? $data['agent']
            : (defined('CLEANTALK_AGENT') ? CLEANTALK_AGENT : self::AGENT);

        // Possibility to switch API url
        $url = defined('CLEANTALK_API_URL') ? CLEANTALK_API_URL : self::$api_url;

        $http = new Request();

        $request = $http->setUrl($url)
                    ->setData($data)
                    ->setPresets(['retry_with_socket'])
                    ->setOptions(['timeout' => $timeout]);
        if ( isset($data['method_name']) ) {
            $request->addCallback(
                __CLASS__ . '::checkResponse',
                [$data['method_name']]
            );
        }

        return $request->request();
    }

    /**
     * Function checks server response
     *
     * @param array|string $result
     * @param string $method_name
     *
     * @return mixed (array || array('error' => true))
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public static function checkResponse($result, $method_name = null)
    {
        // Errors handling
        // Bad connection
        if (is_array($result)) {
            if ( isset($result['error']) && ! empty($result['error']) ) {
                $error_string = 'CONNECTION_ERROR : "' . $result['error'] . '"';
            } else {
                $last = error_get_last();
                $error = $last ? $last['message'] : 'Unhandled Error.';
                $error_string = 'CONNECTION_ERROR : "Unknown Error. Last error: ' . $error . '"';
            }

            return ['error' => $error_string];
        }

        // JSON decode errors
        $result = json_decode($result, true);
        if (empty($result)) {
            return array(
                'error' => 'JSON_DECODE_ERROR',
            );
        }

        // Server errors
        if ($result && (isset($result['error_no'], $result['error_message']))) {
            if ($result['error_no'] != 12) {
                return array(
                    'error'         => "SERVER_ERROR NO: {$result['error_no']} MSG: {$result['error_message']}",
                    'error_no'      => $result['error_no'],
                    'error_message' => $result['error_message'],
                );
            }
        }

        // Patches for different methods
        switch ($method_name) {
            // notice_paid_till
            case 'notice_paid_till':
                $result = isset($result['data']) ? $result['data'] : $result;

                if ((isset($result['error_no']) && $result['error_no'] == 12) ||
                    (
                        ! (isset($result['service_id']) && is_int($result['service_id'])) &&
                        empty($result['moderate_ip'])
                    )
                ) {
                    $result['valid'] = 0;
                } else {
                    $result['valid'] = 1;
                }

                return $result;

            case 'email_check':
                return isset($result['data']) ? $result : array('error' => 'NO_DATA');

            // get_antispam_report_breif
            case 'get_antispam_report_breif':
                $out = isset($result['data']) && is_array($result['data'])
                    ? $result['data']
                    : array('error' => 'NO_DATA');

                for ($tmp = array(), $i = 0; $i < 7; $i++) {
                    $tmp[date('Y-m-d', time() - 86400 * 7 + 86400 * $i)] = 0;
                }
                $out['spam_stat']    = array_merge($tmp, isset($out['spam_stat']) ? $out['spam_stat'] : array());
                $out['top5_spam_ip'] = isset($out['top5_spam_ip']) ? array_slice($out['top5_spam_ip'], 0, 5) : array();

                return $out;

            case 'services_templates_add':
            case 'services_templates_update':
                return isset($result['data'][0]) && is_array($result['data']) && count($result['data']) === 1
                    ? $result['data'][0]
                    : array('error' => 'NO_DATA');

            case 'private_list_add':
                return isset($result['records'][0]['operation_status']) && $result['records'][0]['operation_status'] === 'SUCCESS'
                    ? true
                    : array('error' => 'COULDNT_ADD_WL_IP');

            case '2s_blacklists_db':
                return isset($result['data']) && isset($result['data_user_agents'])
                    ? $result
                    : $result['data'];

            default:
                return isset($result['data']) && is_array($result['data'])
                    ? $result['data']
                    : array('error' => 'NO_DATA');
        }
    }
}
