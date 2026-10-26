<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\Antispam\CleantalkRequest;
use Cleantalk\Antispam\CleantalkResponse;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TextPlateStatic;
use Cleantalk\Common\TT;

class ConnectionReports
{
    const SOURCE_MODERATE = 'moderate';
    const SOURCE_API = 'api';

    /**
     * Statistics of state (moderate requests)
     * @var int[]
     */
    public $reports_count = array(
        'positive' => 0,
        'negative' => 0,
        'total' => 0,
        'stat_since' => 0
    );

    /**
     * Statistics of state (API requests)
     * @var int[]
     */
    public $api_reports_count = array(
        'positive' => 0,
        'negative' => 0,
        'total' => 0,
        'stat_since' => 0
    );

    /**
     * API methods which are not tracked. 2s_blacklists_db is handled by SFWUpdateSentinel.
     * @var string[]
     */
    private $api_methods_excluded = array(
        '2s_blacklists_db',
    );

    /**
     * Error prefixes from API::checkResponse() which mean the server answered (business errors, not connection ones)
     * @var string[]
     */
    private $api_business_errors_prefixes = array(
        'SERVER_ERROR',
        'NO_DATA',
        'COULDNT_ADD_WL_IP',
    );

    /**
     * Request data keys that must never be stored in reports
     * @var string[]
     */
    private $api_sensitive_keys = array(
        'auth_key',
        'api_key',
        'user_token',
        'key',
        'token',
        'password',
    );

    /**
     * Max length of a single API request field kept in report
     * @var int
     */
    private $api_field_max_length = 500;

    /**
     * @var bool|null Cache for the table existence check
     */
    private $table_exists = null;

    /**
     * @var bool|null Cache for source/method_name columns existence (they appear after DB update)
     */
    private $has_source_columns = null;

    /**
     * Instance of DB object
     * @var DB
     */
    private $db;

    /**
     * DB table name
     * @var string
     */
    private $cr_table_name;

    /**
     * Limit of reports to keep, per source
     * @var int[]
     */
    private $reports_limits = array(
        self::SOURCE_MODERATE => 20,
        self::SOURCE_API => 20,
    );

    /**
     * @var array Current reports data from DB
     */
    private $reports_data = array();

    /**
     * @var bool Flag to track if reports data needs refreshing
     */
    private $reports_data_dirty = true;

    /**
     * @var array|null Cache for unsent reports IDs
     */
    private $unsent_reports_cache = null;

    /**
     * ConnectionReports constructor.
     * @param DB $db
     * @param string $cr_table_name
     */
    public function __construct(DB $db, $cr_table_name)
    {
        global $apbct;
        $this->db = $db;
        $this->cr_table_name = $cr_table_name;

        // Initialize reports count from APBCT data
        $this->initializeReportsCount($apbct);

        // Defer loading reports data until actually needed
    }

    /**
     * Initialize reports count from APBCT data
     * @param mixed $apbct
     */
    private function initializeReportsCount($apbct)
    {
        $this->reports_count = $this->readCountFromData($apbct, 'connection_reports_count');
        $this->api_reports_count = $this->readCountFromData($apbct, 'connection_reports_count_api');
    }

    /**
     * @param mixed $apbct
     * @param string $data_key
     * @return array
     */
    private function readCountFromData($apbct, $data_key)
    {
        $stored = isset($apbct->data[$data_key]) && is_array($apbct->data[$data_key])
            ? $apbct->data[$data_key]
            : array();

        return array(
            'positive' => isset($stored['positive']) ? $stored['positive'] : 0,
            'negative' => isset($stored['negative']) ? $stored['negative'] : 0,
            'total' => isset($stored['total']) ? $stored['total'] : 0,
            'stat_since' => isset($stored['stat_since']) ? $stored['stat_since'] : date('d M'),
        );
    }

    /**
     * Check once if the reports table exists
     * @return bool
     */
    private function isTableExists()
    {
        if ($this->table_exists === null) {
            $this->table_exists = $this->db->isTableExists($this->cr_table_name);
        }
        return $this->table_exists;
    }

    /**
     * Check once if the table has source/method_name columns
     * @return bool
     */
    private function hasSourceColumns()
    {
        if ($this->has_source_columns === null) {
            $columns = $this->db->fetchAll('SHOW COLUMNS FROM ' . $this->cr_table_name . " LIKE 'method_name';");
            $this->has_source_columns = ! empty($columns);
        }
        return $this->has_source_columns;
    }

    /**
     * Get source of the report row. Legacy rows without source are moderate ones.
     * @param array $report
     * @return string
     */
    private static function getReportSource($report)
    {
        return isset($report['source']) && $report['source'] === self::SOURCE_API
            ? self::SOURCE_API
            : self::SOURCE_MODERATE;
    }

    /**
     * Get reports data with lazy loading
     * @return array
     */
    private function getReportsData()
    {
        if ($this->reports_data_dirty || empty($this->reports_data)) {
            $this->loadReportsDataFromDb();
        }
        return $this->reports_data;
    }

    /**
     * Initialize once all the reports data from Db to class
     */
    private function loadReportsDataFromDb()
    {
        $table_exist = $this->db->fetchAll(
            'SHOW TABLES LIKE "' . $this->cr_table_name . '";'
        );

        if (empty($table_exist)) {
            $this->reports_data = array();
            $this->reports_data_dirty = false;
            $this->unsent_reports_cache = null;
            return;
        }

        $sql = "SELECT *, date AS date_timestamp, FROM_UNIXTIME(date) AS date, FROM_UNIXTIME(sent_on) AS sent_on FROM " . $this->cr_table_name . " ORDER BY date_timestamp;";
        $this->reports_data = TT::toArray($this->db->fetchAll($sql));
        $this->reports_data_dirty = false;
        $this->unsent_reports_cache = null; // Invalidate cache
    }

    /**
     * Mark reports data as dirty (needs refresh)
     */
    private function markReportsDataDirty()
    {
        $this->reports_data_dirty = true;
        $this->unsent_reports_cache = null;
    }

    /**
     * Update global stats in state
     * @param string $source
     */
    private function updateStats($source = self::SOURCE_MODERATE)
    {
        global $apbct;
        if ($source === self::SOURCE_API) {
            $this->api_reports_count = $this->recalculateTotal($this->api_reports_count);
            $apbct->data['connection_reports_count_api'] = $this->api_reports_count;
        } else {
            $this->reports_count = $this->recalculateTotal($this->reports_count);
            $apbct->data['connection_reports_count'] = $this->reports_count;
        }
        $apbct->saveData();
    }

    /**
     * @param array $count
     * @return array
     */
    private function recalculateTotal($count)
    {
        $positive = isset($count['positive']) ? $count['positive'] : 0;
        $negative = isset($count['negative']) ? $count['negative'] : 0;
        $count['total'] = $positive + $negative;
        return $count;
    }

    /**
     * Increment positive or negative counter of the source
     * @param string $source
     * @param string $type positive|negative
     */
    private function incrementCount($source, $type)
    {
        if ($source === self::SOURCE_API) {
            $this->api_reports_count[$type] = isset($this->api_reports_count[$type])
                ? $this->api_reports_count[$type] + 1
                : 1;
        } else {
            $this->reports_count[$type] = isset($this->reports_count[$type])
                ? $this->reports_count[$type] + 1
                : 1;
        }
    }

    /**
     * Array of report's IDs that has null on sent_on field
     * @return array
     */
    private function getUnsentReportsIds()
    {
        if ($this->unsent_reports_cache !== null) {
            return $this->unsent_reports_cache;
        }

        $result = array();
        $reports_data = $this->getReportsData();

        foreach ($reports_data as $row) {
            if (isset($row['id']) && (empty($row['sent_on']) || $row['sent_on'] === 'NULL')) {
                $result[] = $row['id'];
            }
        }

        $this->unsent_reports_cache = $result;
        return $result;
    }

    /**
     * Set reports as sent in batch
     * @param array $ids Array of report IDs
     */
    private function setReportsAsSent(array $ids)
    {
        if (empty($ids)) {
            return;
        }

        // Use IN clause for batch update
        $placeholders = implode(',', array_fill(0, count($ids), '%s'));
        $query = "UPDATE " . $this->cr_table_name . " SET sent_on = %s WHERE id IN ($placeholders)";

        $params = array_merge([time()], $ids);
        $this->db->prepare($query, $params);
        $this->db->execute($this->db->getQuery());

        $this->markReportsDataDirty();
    }

    /**
     * Rotates reports of the source in DB, remove the oldest ones by limit and age (6 months).
     * @param string $source
     */
    private function rotateReports($source = self::SOURCE_MODERATE)
    {
        $reports_data = $this->getReportsData();
        $ids_to_delete = array();

        // Remove reports older than 6 months
        $six_months_ago = time() - (6 * 30 * 24 * 60 * 60);
        foreach ($reports_data as $report) {
            if ( isset($report['date_timestamp'], $report['id']) && $report['date_timestamp'] < $six_months_ago ) {
                $ids_to_delete[] = $report['id'];
            }
        }

        // Remove oldest reports of the source if exceeding the source limit
        $source_reports = array_values(array_filter($reports_data, static function ($report) use ($source) {
            return self::getReportSource($report) === $source;
        }));
        $limit = isset($this->reports_limits[$source]) ? $this->reports_limits[$source] : 20;
        if (count($source_reports) >= $limit) {
            $overlimit = count($source_reports) - $limit + 1;
            $reports_to_del = array_slice($source_reports, 0, $overlimit);

            $overlimit_ids = array_column($reports_to_del, 'id');
            $ids_to_delete = array_unique(array_merge($ids_to_delete, $overlimit_ids));
        }

        // Delete reports if any
        if (!empty($ids_to_delete)) {
            $placeholders = implode(',', array_fill(0, count($ids_to_delete), '%s'));

            $this->db->prepare(
                "DELETE FROM " . $this->cr_table_name . " WHERE id IN ($placeholders)",
                $ids_to_delete
            );
            $this->db->execute($this->db->getQuery());

            $this->markReportsDataDirty();
        }
    }

    /**
     * Return reports data by their Ids
     * @param array $ids
     * @return array
     */
    private function getReportsDataByIds(array $ids)
    {
        if (empty($ids)) {
            return array();
        }

        $reports = array();
        $reports_data = $this->getReportsData();

        foreach ($reports_data as $report) {
            if (in_array($report['id'], $ids, false)) {
                $reports[] = $report;
            }
        }

        return $reports;
    }

    /**
     * Add report data to DB
     * @param string $lib_report HTTP lib report text
     * @param string $failed_work_urls Current work URLs of CT server that failed
     * @param array $request_content CleanTalk request content
     * @param bool $post_blocked_via_js_check Flag if JS check passed request or not
     * @param string $source Report source, moderate|api
     * @param string|null $method_name API method name
     */
    private function addReportToDb(
        $lib_report = '',
        $failed_work_urls = '',
        $request_content = array(),
        $post_blocked_via_js_check = false,
        $source = self::SOURCE_MODERATE,
        $method_name = null
    ) {
        $cr_data = array(
            'date' => time(),
            'page_url' => get_site_url() . TT::toString(Server::get('REQUEST_URI')),
            'lib_report' => $lib_report,
            'failed_work_urls' => $failed_work_urls,
            'request_content' => json_encode(esc_sql($request_content)),
            'js_block' => $post_blocked_via_js_check ? '1' : '0',
            'source' => $source,
            'method_name' => TT::toString($method_name),
        );

        $query = "INSERT INTO " . $this->cr_table_name . "
            SET
                date = %s,
                page_url = %s,
                lib_report = %s,
                failed_work_urls = %s,
                request_content = %s,
                js_block = %s";
        $params = array(
            $cr_data['date'],
            $cr_data['page_url'],
            $cr_data['lib_report'],
            $cr_data['failed_work_urls'],
            $cr_data['request_content'],
            $cr_data['js_block'],
        );

        if ($this->hasSourceColumns()) {
            $query .= ",
                source = %s,
                method_name = %s";
            $params[] = $cr_data['source'];
            $params[] = $cr_data['method_name'];
        }

        $this->db->prepare($query, $params);

        $this->db->execute($this->db->getQuery());
        $this->markReportsDataDirty();
    }

    /**
     * Return HTML of negative reports table
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     * @psalm-suppress PossiblyUndefinedStringArrayOffset
     */
    public function prepareNegativeReportsHtmlForSettingsPage()
    {
        $reports_data = $this->getReportsData();

        if (empty($reports_data)) {
            return '';
        }

        $moderate_reports = self::filterReportsBySource($reports_data, self::SOURCE_MODERATE);
        $api_reports = self::filterReportsBySource($reports_data, self::SOURCE_API);

        $reports_html = '<div>' . $this->prepareStatsParagraph($this->reports_count, 'Anti-Spam') . '</div>';
        if ( ! empty($moderate_reports) ) {
            $rows = '';
            foreach ($moderate_reports as $key => $report) {
                $rows .= $this->prepareModerateReportRow($key, $report);
            }
            $reports_html .= self::prepareReportsTable(
                'apbct_negative_reports_table',
                'Failed connection reports',
                array('Date', 'Page URL', 'Report', 'Server IP', 'Blocked via JS', 'Status'),
                $rows
            );
        }

        if ( ! empty($this->api_reports_count['total']) || ! empty($api_reports) ) {
            $reports_html .= '<div>' . $this->prepareStatsParagraph($this->api_reports_count, 'API') . '</div>';
            if ( ! empty($api_reports) ) {
                $rows = '';
                foreach ($api_reports as $key => $report) {
                    $rows .= $this->prepareApiReportRow($key, $report);
                }
                $reports_html .= self::prepareReportsTable(
                    'apbct_negative_reports_table_api',
                    'Failed API connection reports',
                    array('Date', 'Page URL', 'API method', 'Report', 'API URL', 'Status'),
                    $rows
                );
            }
        }

        return $reports_html;
    }

    /**
     * Get reports of the source, reindexed from 0
     * @param array $reports_data
     * @param string $source
     * @return array
     */
    private static function filterReportsBySource($reports_data, $source)
    {
        return array_values(array_filter($reports_data, static function ($report) use ($source) {
            return self::getReportSource($report) === $source;
        }));
    }

    /**
     * Prepare reports table HTML for the settings page
     * @param string $table_id
     * @param string $title
     * @param string[] $headers Columns headers except the first "#" one
     * @param string $rows Rows HTML, already escaped
     * @return string
     */
    private static function prepareReportsTable($table_id, $title, $headers, $rows)
    {
        $headers_html = '<td>#</td>';
        foreach ($headers as $header) {
            $headers_html .= '<td><b>' . Escape::escHtml($header) . '</b></td>';
        }

        return "<table id='" . Escape::escAttr($table_id) . "'>"
               . "<th colspan='" . (count($headers) + 1) . "'>" . Escape::escHtml($title) . '</th>'
               . '<tr>' . $headers_html . '</tr>'
               . $rows
               . '</table>';
    }

    /**
     * Prepare escaped HTML paragraphs with stats of both sources
     * @return string
     */
    private function prepareStatsHtml()
    {
        $html = $this->prepareStatsParagraph($this->reports_count, 'Anti-Spam');
        if ( ! empty($this->api_reports_count['total']) ) {
            $html .= $this->prepareStatsParagraph($this->api_reports_count, 'API');
        }
        return $html;
    }

    /**
     * @param array $count
     * @param string $label
     * @return string
     */
    private function prepareStatsParagraph($count, $label)
    {
        $stat_since = isset($count['stat_since']) ? $count['stat_since'] : '';
        $total = isset($count['total']) ? $count['total'] : '';
        $positive = isset($count['positive']) ? $count['positive'] : '';
        $negative = isset($count['negative']) ? $count['negative'] : '';

        return '<p>' . Escape::escHtml($label) . ': from '
               . Escape::escHtml($stat_since)
               . ' to ' . Escape::escHtml(date('d M')) . ' has been made '
               . Escape::escHtml($total)
               . ' calls, where ' . Escape::escHtml($positive) . ' were success and '
               . Escape::escHtml($negative) . ' were negative</p>';
    }

    /**
     * Get report status label and row color
     * @param array $report
     * @return string[] [status, color]
     */
    private static function getReportStatus($report)
    {
        return isset($report['sent_on']) && $report['sent_on']
            ? array('Sent', 'gray')
            : array('New', 'black');
    }

    /**
     * Prepare single Anti-Spam report row HTML
     * @param int $key
     * @param array $report
     * @return string
     */
    private function prepareModerateReportRow($key, $report)
    {
        list($status, $color) = self::getReportStatus($report);

        $report_date = isset($report['date']) ? $report['date'] : time();
        $report_page_url = isset($report['page_url']) ? $report['page_url'] : '';
        $report_lib_report = isset($report['lib_report']) ? $report['lib_report'] : '';
        $report_failed_work_urls = isset($report['failed_work_urls']) ? $report['failed_work_urls'] : '';
        $report_js_block = isset($report['js_block']) ? $report['js_block'] : 0;

        return '<tr style="color:' . $color . '">'
               . '<td>' . Escape::escHtml((int)$key + 1) . '.</td>'
               . '<td>' . Escape::escHtml($report_date) . '</td>'
               . '<td>' . Escape::escUrl($report_page_url) . '</td>'
               . '<td>' . Escape::escHtml($report_lib_report) . '</td>'
               . '<td>' . Escape::escHtml($report_failed_work_urls) . '</td>'
               . '<td>' . Escape::escHtml($report_js_block === '1' ? 'Yes' : 'No') . '</td>'
               . '<td>' . Escape::escHtml($status) . '</td>'
               . '</tr>';
    }

    /**
     * Prepare single API report row HTML
     * @param int $key
     * @param array $report
     * @return string
     */
    private function prepareApiReportRow($key, $report)
    {
        list($status, $color) = self::getReportStatus($report);

        $report_date = isset($report['date']) ? $report['date'] : time();
        $report_page_url = isset($report['page_url']) ? $report['page_url'] : '';
        $report_method_name = isset($report['method_name']) ? $report['method_name'] : '';
        $report_lib_report = isset($report['lib_report']) ? $report['lib_report'] : '';
        $report_api_url = isset($report['failed_work_urls']) ? $report['failed_work_urls'] : '';

        return '<tr style="color:' . $color . '">'
               . '<td>' . Escape::escHtml((int)$key + 1) . '.</td>'
               . '<td>' . Escape::escHtml($report_date) . '</td>'
               . '<td>' . Escape::escUrl($report_page_url) . '</td>'
               . '<td>' . Escape::escHtml($report_method_name) . '</td>'
               . '<td>' . Escape::escHtml($report_lib_report) . '</td>'
               . '<td>' . Escape::escHtml($report_api_url) . '</td>'
               . '<td>' . Escape::escHtml($status) . '</td>'
               . '</tr>';
    }

    /**
     * Check if there are reports kept
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function hasNegativeReports()
    {
        return count($this->getReportsData()) > 0;
    }

    /**
     * Init connection reports handling
     * @param CleantalkRequest $request
     * @param CleantalkResponse $request_response
     * @param bool $post_blocked_via_js_check
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function handleRequest(
        CleantalkRequest $request,
        CleantalkResponse $request_response,
        $post_blocked_via_js_check
    ) {
        // Succeeded connection
        if ($request_response->errno === 0 && empty($request_response->errstr)) {
            $this->incrementCount(self::SOURCE_MODERATE, 'positive');
        } else {
            // Failed to connect. Add a negative report
            $this->rotateReports(self::SOURCE_MODERATE);
            $this->incrementCount(self::SOURCE_MODERATE, 'negative');
            $this->addReportToDb(
                $request_response->errstr,
                $request_response->failed_connections_urls_string,
                Helper::arrayObjectToArray($request),
                $post_blocked_via_js_check,
                self::SOURCE_MODERATE
            );
        }
        $this->updateStats(self::SOURCE_MODERATE);
    }

    /**
     * Connection reports handling for API (api.cleantalk.org) requests
     * @param string $method_name API method name
     * @param array $request_data Data sent to API
     * @param mixed $result Result of API::sendRequest()
     * @param string $url API URL
     * @param int|null $http_code HTTP code of the response if it was received
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function handleApiRequest($method_name, $request_data, $result, $url, $http_code = null)
    {
        $method_name = TT::toString($method_name);

        if (in_array($method_name, $this->api_methods_excluded, true) || ! $this->isTableExists()) {
            return;
        }

        $error = $this->getApiConnectionError($result, $http_code);

        if ($error === null) {
            $this->incrementCount(self::SOURCE_API, 'positive');
        } else {
            $this->rotateReports(self::SOURCE_API);
            $this->incrementCount(self::SOURCE_API, 'negative');
            $this->addReportToDb(
                $error,
                TT::toString($url),
                $this->sanitizeApiRequestData(is_array($request_data) ? $request_data : array()),
                false,
                self::SOURCE_API,
                $method_name
            );
        }
        $this->updateStats(self::SOURCE_API);
    }

    /**
     * Get the connection error from API result. Business errors (the server has answered) are not connection errors.
     * @param mixed $result
     * @param int|null $http_code
     * @return string|null Error text or null if there is no connection error
     */
    public function getApiConnectionError($result, $http_code = null)
    {
        $error = null;

        if (is_array($result) && ! empty($result['error'])) {
            $error = is_string($result['error']) ? $result['error'] : (string)json_encode($result['error']);
            foreach ($this->api_business_errors_prefixes as $prefix) {
                if (strpos($error, $prefix) === 0) {
                    $error = null;
                    break;
                }
            }
        }

        $http_code = (int)$http_code;
        if ($http_code >= 500) {
            $error = 'HTTP_CODE_' . $http_code . ($error !== null ? ': ' . $error : '');
        } elseif ($error !== null && $http_code > 0) {
            $error = 'HTTP_CODE_' . $http_code . ': ' . $error;
        }

        return $error;
    }

    /**
     * Remove credentials and truncate large fields of API request data
     * @param array $request_data
     * @return array
     */
    private function sanitizeApiRequestData(array $request_data)
    {
        $result = array();
        foreach ($request_data as $key => $value) {
            if (in_array(strtolower((string)$key), $this->api_sensitive_keys, true)) {
                $result[$key] = '***';
                continue;
            }
            if (is_array($value) || is_object($value)) {
                $value = (string)json_encode($value);
            }
            if (is_string($value)) {
                $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
                if ($length > $this->api_field_max_length) {
                    $value = (function_exists('mb_substr')
                            ? mb_substr($value, 0, $this->api_field_max_length)
                            : substr($value, 0, $this->api_field_max_length)) . '...[truncated]';
                }
            }
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * Send email to welcome@cleantlk.org about failed connection reports
     * @param array $unsent_reports_ids IDs of reports that still not sent
     * @param bool $is_cron_task Set if this is a cron task
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    private function sendEmail(array $unsent_reports_ids, $is_cron_task = false)
    {
        global $apbct;
        $selection = $this->getReportsDataByIds($unsent_reports_ids);

        if (empty($selection)) {
            return false;
        }

        $to = $apbct->data['email_for_reports'];
        $subject = "CleanTalk Service Report: Connection v" . APBCT_VERSION . " for " . Server::getString('HTTP_HOST') ;

        $message = $this->prepareEmailContent($selection, $is_cron_task);
        $headers = "Content-type: text/html; charset=utf-8 \r\n";
        $headers .= 'From: ' . ct_get_admin_email();

        if (wp_mail($to, $subject, $message, $headers)) {
            return true;
        }
        return false;
    }

    /**
     * Prepare email content
     * @param array $selection
     * @param bool $is_cron_task
     * @return string
     */
    private function prepareEmailContent(array $selection, $is_cron_task = false)
    {
        global $apbct;

        $moderate_reports = self::filterReportsBySource($selection, self::SOURCE_MODERATE);
        $api_reports = self::filterReportsBySource($selection, self::SOURCE_API);

        $message = '
            <html lang="en">
                <head>
                    <title></title>
                </head>
                <body>
                    ' . $this->prepareStatsHtml();

        if ( ! empty($moderate_reports) ) {
            $message .= '
                    <p>Negative report:</p>
                    <table>
                        <tr>
                            <td>&nbsp;</td>
                            <td><b>Date</b></td>
                            <td><b>Page URL</b></td>
                            <td><b>Library report</b></td>
                            <td><b>Server IP</b></td>
                            <td><b>Blocked via JS</b></td>
                        </tr>';

            $counter = 0;
            foreach ($moderate_reports as $report) {
                $message .= '<tr>'
                            . '<td>' . (++$counter) . '.</td>'
                            . '<td>' . TT::toString($report['date']) . '</td>'
                            . '<td>' . Escape::escUrl($report['page_url']) . '</td>'
                            . '<td>' . Escape::escHtml($report['lib_report']) . '</td>'
                            . '<td>' . Escape::escHtml($report['failed_work_urls']) . '</td>'
                            . '<td>' . ($report['js_block'] === '1' ? 'Yes' : 'No') . '</td>'
                            . '</tr>';
            }

            $message .= '</table><br>';
        }

        if ( ! empty($api_reports) ) {
            $message .= '
                    <p>Negative API report:</p>
                    <table>
                        <tr>
                            <td>&nbsp;</td>
                            <td><b>Date</b></td>
                            <td><b>Page URL</b></td>
                            <td><b>API method</b></td>
                            <td><b>Library report</b></td>
                            <td><b>API URL</b></td>
                        </tr>';

            $counter = 0;
            foreach ($api_reports as $report) {
                $message .= '<tr>'
                            . '<td>' . (++$counter) . '.</td>'
                            . '<td>' . TT::toString($report['date']) . '</td>'
                            . '<td>' . Escape::escUrl($report['page_url']) . '</td>'
                            . '<td>' . Escape::escHtml(isset($report['method_name']) ? $report['method_name'] : '') . '</td>'
                            . '<td>' . Escape::escHtml($report['lib_report']) . '</td>'
                            . '<td>' . Escape::escHtml($report['failed_work_urls']) . '</td>'
                            . '</tr>';
            }

            $message .= '</table><br>';
        }

        $message .= '<br>' . ($is_cron_task ? 'This is a cron task.' : 'This is a manual task.') . '<br>';
        $message .= '<br>Site service_id: ' . $apbct->data['service_id'] . '<br>';
        $message .= '</body></html>';

        return $message;
    }

    /**
     * Init reports sending
     * @param bool $is_cron_task Set if this is a cron task
     * @return string Used just to debug CRON task
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function sendUnsentReports($is_cron_task = false)
    {
        $unsent_reports_ids = $this->getUnsentReportsIds();

        if (!empty($unsent_reports_ids)) {
            if ($this->sendEmail($unsent_reports_ids, $is_cron_task)) {
                $this->setReportsAsSent($unsent_reports_ids);
                return count($unsent_reports_ids) . ' reports were sent.';
            }
        }
        return 'Nothing to sent.';
    }

    /**
     * Check if there are unsent reports
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function hasUnsentReports()
    {
        return !empty($this->getUnsentReportsIds());
    }

    /**
     * Prepare data for remote call answer
     * @return array
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function remoteCallOutput()
    {
        return $this->getReportsData();
    }

    /**
     * Truncate connection reports DB
     *  @psalm-suppress PossiblyUnusedMethod
     */
    public function wipeReportsData()
    {
        $this->db->execute("TRUNCATE TABLE " . $this->cr_table_name);
        $this->markReportsDataDirty();
    }

    /**
     * Get option description
     * @param bool $sfw_enabled
     * @param string $brand_name
     * @return string
     */
    public static function getOptionDescription($sfw_enabled, $brand_name, $email)
    {
        $send_connection_reports__sfw_text = '';
        $sfw_outdated_message = '';
        if ($sfw_enabled) {
            $send_connection_reports__sfw_text = '<br>' . __('- status of SpamFireWall database updating process', 'cleantalk-spam-protect');
            $sfw_outdated_message = '<br>' . __('Also, if enabled, a notification will appear in the plugin settings informing you that the SpamFireWall database is outdated.', 'cleantalk-spam-protect');
        }
        return TextPlateStatic::render(
            '{{first_row}}<br>{{second_row}}<br>{{list_1}}<br>{{list_api}}{{list_2}}{{sfw_outdated_message}}',
            array(
                'first_row' => __("Checking this box you allow plugin to send the information about your connection.", 'cleantalk-spam-protect'),
                'second_row' => esc_html__(
                    sprintf(
                        'These reports are to be sent to %s and could contain',
                        $email
                    )
                ),
                'list_1' => esc_html__(
                    sprintf(
                        '- connection status to %s cloud during Anti-Spam request',
                        TT::toString($brand_name, __('service', 'cleantalk-spam-protect'))
                    )
                ),
                'list_api' => esc_html__(
                    sprintf(
                        '- connection status to %s cloud during API requests',
                        TT::toString($brand_name, __('service', 'cleantalk-spam-protect'))
                    )
                ),
                'list_2' => $send_connection_reports__sfw_text,
                'sfw_outdated_message' => $sfw_outdated_message,
            )
        );
    }
}
