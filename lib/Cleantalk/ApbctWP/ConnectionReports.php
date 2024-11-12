<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\Antispam\CleantalkRequest;
use Cleantalk\Antispam\CleantalkResponse;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;

class ConnectionReports
{
    /**
     * Statistics of state
     * @var int[]
     */
    public $reports_count = array(
        'positive' => 0,
        'negative' => 0,
        'total' => 0,
        'stat_since' => 0
    );

    /**
     * Instance of DB object
     * @var DB
     */
    private $db;
    /**
     * DB table name
     * @var
     */
    private $cr_table_name;
    /**
     * Limit of reports to keep
     * @var int
     */
    private $reports_limit = 20;
    /**
     * @var array Current reports data from DB
     */
    private $reports_data;

    /**
     * ConnectionReports constructor.
     * @param DB $db
     * @param $cr_table_name
     */
    public function __construct(DB $db, $cr_table_name)
    {
        global $apbct;
        $this->db = $db;
        $this->cr_table_name = $cr_table_name;
        $this->reports_count['positive'] = isset($apbct->data['connection_reports_count']['positive'])
            ? $apbct->data['connection_reports_count']['positive']
            : 0;
        $this->reports_count['negative'] = isset($apbct->data['connection_reports_count']['negative'])
            ? $apbct->data['connection_reports_count']['negative']
            : 0;
        $this->reports_count['total'] = isset($apbct->data['connection_reports_count']['total'])
            ? $apbct->data['connection_reports_count']['total']
            : 0;
        $this->reports_count['stat_since'] = isset($apbct->data['connection_reports_count']['stat_since'])
            ? $apbct->data['connection_reports_count']['stat_since']
            : date('d M');
        $this->getReportsDataFromDb();
    }

    /**
     * Initialize once all the reports data from Db to class
     * @return array|null|object of reports data. Used in testcases only.
     * @psalm-suppress PossiblyUnusedMethod
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function getReportsDataFromDb()
    {
        $table_exist = $this->db->fetchAll(
            'SHOW TABLES LIKE "' . $this->cr_table_name . '";'
        );

        if ( empty($table_exist) ) {
            $this->reports_data = array();
            return;
        }

        $sql =
            "SELECT * FROM " . $this->cr_table_name .
            " ORDER BY date;";

        $this->reports_data = TT::toArray($this->db->fetchAll($sql));

        return $this->reports_data;
    }

    /**
     * Update global stats in state
     */
    private function updateStats()
    {
        global $apbct;
        $positive = isset($this->reports_count['positive']) ? $this->reports_count['positive'] : 0;
        $negative = isset($this->reports_count['negative']) ? $this->reports_count['negative'] : 0;
        $this->reports_count['total'] = $positive + $negative;
        $apbct->data['connection_reports_count'] = $this->reports_count;
        $apbct->saveData();
    }

    /**
     * Array of report's IDs that has null on on_sent field
     * @return array
     */
    private function getUnsentReportsIds()
    {
        $result = array();

        if ( !empty($this->reports_data) ) {
            foreach ( $this->reports_data as $row ) {
                if ( isset($row['id']) && ( empty($row['sent_on']) || $row['sent_on'] === 'NULL' ) ) {
                    $result[] = $row['id'];
                }
            }
        }

        return $result;
    }

    /**
     * Set a report as sent
     * @param string $id id of report
     */
    private function setReportAsSent($id = '')
    {
        if ( !empty($id) ) {
            $this->db->execute(
                "INSERT INTO " . $this->cr_table_name . " SET
                id = " . $id . ",
                date = date,
                page_url = page_url,
                lib_report = lib_report,
                failed_work_urls = failed_work_urls,
                request_content = request_content
				ON DUPLICATE KEY UPDATE
                sent_on = " . time() . ";"
            );
        }
    }

    /**
     * Rotates reports in DB, remove oldest one.
     */
    private function rotateReports()
    {
        /**
         * keep 20 newest records
         */
        $this->getReportsDataFromDb();

        if ( count($this->reports_data) === $this->reports_limit + 1 ) {
            $overlimit = count($this->reports_data) - $this->reports_limit + 1 ;
            $reports_to_del = array_slice($this->reports_data, 0, $overlimit);

            $ids = array_column($reports_to_del, 'id');

            $ids = '(' . implode(',', $ids) . ')';

            $this->db->execute(
                "DELETE FROM " . $this->cr_table_name . " WHERE id IN " . $ids . ";"
            );
        }
    }

    /**
     * Return reports data by their Ids
     * @param array $ids
     * @return array
     */
    private function getReportsDataByIds(array $ids)
    {

        $reports = array();

        foreach ( $this->reports_data as $report ) {
            if ( in_array($report['id'], $ids, false) ) {
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
     */
    private function addReportToDb(
        $lib_report = '',
        $failed_work_urls = '',
        $request_content = array(),
        $post_blocked_via_js_check = false
    ) {
        $cr_data = array(
            'date' => time(),
            'page_url' => get_site_url() . TT::toString(Server::get('REQUEST_URI')),
            'lib_report' => $lib_report,
            'failed_work_urls' => $failed_work_urls,
            'request_content' => json_encode(esc_sql($request_content)),
            'js_block' => $post_blocked_via_js_check ? '1' : '0'
        );

        $this->db->prepare(
            "INSERT INTO " . $this->cr_table_name . "
            SET
                date = %s,
                page_url = %s,
                lib_report = %s,
                failed_work_urls = %s,
                request_content = %s,
                js_block = %s",
            array(
                $cr_data['date'],
                $cr_data['page_url'],
                $cr_data['lib_report'],
                $cr_data['failed_work_urls'],
                $cr_data['request_content'],
                $cr_data['js_block'],
            )
        );

        $this->db->execute($this->db->getQuery());
    }

    /**
     * Return HTML of negative reports table
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     * @psalm-suppress PossiblyUndefinedStringArrayOffset
     */
    public function prepareNegativeReportsHtmlForSettingsPage()
    {
        if ( empty($this->reports_data) ) {
            return '';
        }

        $rows = '';

        $stat_since = isset($this->reports_count['stat_since']) ? $this->reports_count['stat_since'] : '';
        $total = isset($this->reports_count['total']) ? $this->reports_count['total'] : '';
        $positive = isset($this->reports_count['positive']) ? $this->reports_count['positive'] : '';
        $negative = isset($this->reports_count['negative']) ? $this->reports_count['negative'] : '';
        $reports_html = '<div><p>From '
            . $stat_since
            . ' to ' . date('d M') . ' has been made '
            . $total
            . ' calls, where ' . $positive . ' were success and '
            . $negative . ' were negative
                    </p></div>';

        foreach ( $this->reports_data as $key => $report ) {
            //colorize
            if ( isset($report['sent_on']) && $report['sent_on'] ) {
                $status = 'Sent';
                $color = 'gray';
            } else {
                $status = 'New';
                $color = 'black';
            }
            $report_date = isset($report['date']) ? $report['date'] : time();
            $report_page_url = isset($report['page_url']) ? $report['page_url'] : '';
            $report_lib_report = isset($report['lib_report']) ? $report['lib_report'] : '';
            $report_failed_work_urls = isset($report['failed_work_urls']) ? $report['failed_work_urls'] : '';
            $report_js_block = isset($report['js_block']) ? $report['js_block'] : 0;
            //draw reports rows
            $rows .= '<tr style="color:' . $color . '">'
                . '<td>' . Escape::escHtml((int)$key + 1) . '.</td>'
                . '<td>' . Escape::escHtml(date('m-d-y H:i:s', $report_date)) . '</td>'
                . '<td>' . Escape::escUrl($report_page_url) . '</td>'
                . '<td>' . Escape::escHtml($report_lib_report) . '</td>'
                . '<td>' . Escape::escHtml($report_failed_work_urls) . '</td>'
                . '<td>' . Escape::escHtml($report_js_block === '1' ? 'Yes' : 'No') . '</td>'
                . '<td>' . Escape::escHtml($status) . '</td>'
                . '</tr>';
        }
        //draw main report table
        $reports_html .= "
                <table id='negative_reports_table'>
                <th colspan='7'>Failed connection reports</th>
                <tr>
                    <td>#</td>
                    <td><b>Date</b></td>
                    <td><b>Page URL</b></td>
                    <td><b>Report</b></td>
                    <td><b>Server IP</b></td>
                    <td><b>Blocked via JS</b></td>
                    <td><b>Status</b></td>
                </tr>"
            //attach reports rows
            . $rows
            . "</table>"
            . "<br/>";

        return $reports_html;
    }

    /**
     * Check if there are reports kept
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function hasNegativeReports()
    {
        return count($this->reports_data) > 0;
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
        if ( $request_response->errno === 0 && empty($request_response->errstr) ) {
            if ( isset($this->reports_count['positive']) ) {
                $this->reports_count['positive']++;
            }

            // Failed to connect. Add a negative report
        } else {
            $this->rotateReports();
            if ( isset($this->reports_count['negative']) ) {
                $this->reports_count['negative']++;
            }
            $this->addReportToDb(
                $request_response->errstr,
                $request_response->failed_connections_urls_string,
                Helper::arrayObjectToArray($request),
                $post_blocked_via_js_check
            );
            $this->getReportsDataFromDb();
        }
        $this->updateStats();
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

        if ( empty($selection) ) {
            return false;
        }

        $to = $apbct->data['wl_support_email'];
        $subject = "Connection report for " . TT::toString(Server::get('HTTP_HOST'));
        $stat_since = isset($this->reports_count['stat_since']) ? $this->reports_count['stat_since'] : '';
        $total = isset($this->reports_count['total']) ? $this->reports_count['total'] : '';
        $positive = isset($this->reports_count['positive']) ? $this->reports_count['positive'] : '';
        $negative = isset($this->reports_count['negative']) ? $this->reports_count['negative'] : '';
        $message = '
            <html lang="en">
                <head>
                    <title></title>
                </head>
                <body>
                    <p>From '
            . $stat_since
            . ' to ' . date('d M') . ' has been made '
            . $total
            . ' calls, where ' . $positive . ' were success and '
            . $negative . ' were negative
                    </p>
                    <p>Negative report:</p>
                    <table>  <tr>
                <td>&nbsp;</td>
                <td><b>Date</b></td>
                <td><b>Page URL</b></td>
                <td><b>Library report</b></td>
                <td><b>Server IP</b></td>
                <td><b>Blocked via JS</b></td>
              </tr>
              ';
        $counter = 0;

        foreach ( $selection as $_key => $report ) {
            $message .= '<tr>'
                . '<td>' . (++$counter) . '.</td>'
                . '<td>' . TT::toString(date('m-d-y H:i:s', $report['date'])) . '</td>'
                . '<td>' . $report['page_url'] . '</td>'
                . '<td>' . $report['lib_report'] . '</td>'
                . '<td>' . $report['failed_work_urls'] . '</td>'
                . '<td>' . ($report['js_block'] === '1' ? 'Yes' : 'No') . '</td>'
                . '</tr>';
        }

        $message .= '</table>';
        $message .= '<br>';

        $show_connection_reports_link =
            substr(get_option('home'), -1) === '/' ? get_option('home') : get_option('home') . '/'
                . '?'
                . http_build_query([
                    'plugin_name' => 'apbct',
                    'spbc_remote_call_token' => md5($apbct->api_key),
                    'spbc_remote_call_action' => 'debug',
                    'show_only' => 'connection_reports',
                ]);

        $message .= '<a href="' . $show_connection_reports_link . '" target="_blank">Show connection reports with remote call</a>';
        $message .= '<br>';

        $message .= $is_cron_task ? 'This is a cron task.' : 'This is a manual task.';
        $message .= '<br>';

        $message .= '</body></html>';

        $headers = "Content-type: text/html; charset=utf-8 \r\n";
        $headers .= 'From: ' . ct_get_admin_email();

        /** @psalm-suppress UnusedFunctionCall */
        if ( wp_mail($to, $subject, $message, $headers) ) {
            return true;
        }
        return false;
    }

    /**
     * Init reports sending
     * @param bool $is_cron_task Set if this is a cron task
     * @return string Used just to debug CRON task
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function sendUnsentReports($is_cron_task = false)
    {
        $this->getReportsDataFromDb();
        $unsent_reports_ids = $this->getUnsentReportsIds();
        if ( !empty($unsent_reports_ids) ) {
            /**
             * collect email data by IDs here
             **/
            if ( $this->sendEmail($unsent_reports_ids, $is_cron_task) ) {
                foreach ( $unsent_reports_ids as $report_id ) {
                    $this->setReportAsSent($report_id);
                }
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
        return (bool)$this->getUnsentReportsIds();
    }

    /**
     * Prepare data for remote call answer
     * @return array
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function remoteCallOutput()
    {
        /*
         * We can beatyfy it there if needed
         */
        return $this->reports_data;
    }
    /**
     * Truncate connection reports DB
     *  @psalm-suppress PossiblyUnusedMethod
     */
    public function wipeReportsData()
    {
        $this->db->execute("TRUNCATE TABLE " . $this->cr_table_name);
    }
}
