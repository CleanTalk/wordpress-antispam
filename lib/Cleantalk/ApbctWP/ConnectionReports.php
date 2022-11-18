<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\Antispam\Cleantalk;
use Cleantalk\Antispam\CleantalkRequest;
use Cleantalk\Antispam\CleantalkResponse;
use Cleantalk\ApbctWP\Variables\Server;

class ConnectionReports
{
    public $reports_count = array(
        'good' => 0,
        'bad' => 0,
        'total' => 0,
        'stat_since' => 0
    );

    private $db;
    private $cr_table_name;
    private $reports_limit = 20;
    private $reports_data;

    public function __construct(DB $db, $cr_table_name)
    {
        global $apbct;
        $this->db = $db;
        $this->cr_table_name = $cr_table_name;
        $this->reports_count['good'] = isset($apbct->data['reports_count']['good'])
            ? $apbct->data['reports_count']['good']
            : $this->reports_count['good'];
        $this->reports_count['bad'] = isset($apbct->data['reports_count']['bad'])
            ? $apbct->data['reports_count']['bad']
            : $this->reports_count['good'];
        $this->reports_count['total'] = isset($apbct->data['reports_count']['total'])
            ? $apbct->data['reports_count']['total']
            : $this->reports_count['good'];
        $this->reports_count['stat_since']  = isset($apbct->data['reports_count']['stat_since'])
            ? $apbct->data['reports_count']['stat_since']
            : date('d M');
        $this->getReportsData();
    }

    private function getReportsData()
    {
        $sql =
            "SELECT * FROM " . $this->cr_table_name .
            " ORDER BY date;";

        $this->reports_data = $this->db->fetchAll($sql);
    }

    private function updateStats()
    {
        global $apbct;
        $this->reports_count['total'] = (int)$this->reports_count['good'] + (int)$this->reports_count['bad'];
        $apbct->data['reports_count'] = $this->reports_count;
        $apbct->saveData();
    }

    private function getUnsentReportsIds()
    {
        $result = array();

        if ( !empty($this->reports_data) ) {
            foreach ( $this->reports_data as $row ) {
                if ( empty($row['sent_on']) || $row['sent_on'] === 'NULL' ) {
                    $result[] = $row['id'];
                }
            }
        }

        return $result;
    }

    private function setReportAsSent($id)
    {
        return $this->db->execute(
            "INSERT INTO " . $this->cr_table_name . " SET
                id = " . $id . ",
                date = date,
                page_url = page_url,
                lib_report = lib_report,
                work_url = work_url,
                request_content = request_content
				ON DUPLICATE KEY UPDATE
                sent_on = " . time() . ";"
        );
    }

    private function rotateReports()
    {
        /**
         * keep 20 newest records
         */

        $deletion = 0;

        if ( count($this->reports_data) >= $this->reports_limit ) {
            $overlimit =  count($this->reports_data) - $this->reports_limit;
            $reports_to_del = array_slice($this->reports_data, 0, $overlimit);

            $ids = array_column($reports_to_del, 'id');

            $ids = '(' . implode(',', $ids) . ')';

            $deletion = $this->db->execute(
                "DELETE FROM "  . $this->cr_table_name . " WHERE id IN " . $ids . ";"
            );
        }
    }

    private function getReportsDataByIds($ids)
    {

        $reports = array();

        foreach ( $this->reports_data as $report ) {
            if ( in_array($report['id'], $ids, false) ) {
                $reports[] = $report;
            }
        }

        return $reports;
    }

    private function addReport(
        $lib_report = '',
        $work_url = '',
        $request_content = ''
    ) {
        $cr_data = array(
            'date' => time(),
            'page_url' => get_site_url() . Server::get('REQUEST_URI'),
            'lib_report' => $lib_report,
            'work_url' => $work_url,
            'request_content' => json_encode(esc_sql($request_content)),
        );

        $this->db->prepare(
            "INSERT INTO " . $this->cr_table_name . "
            SET
                date = %s,
                page_url = %s,
                lib_report = %s,
                work_url = %s,
                request_content = %s",
            array(
                $cr_data['date'],
                $cr_data['page_url'],
                $cr_data['lib_report'],
                $cr_data['work_url'],
                $cr_data['request_content'],
            )
        );

        return $this->db->execute($this->db->getQuery());
    }

    public function prepareReportsHtmlForSettings()
    {
        $rows = '';

        foreach ( $this->reports_data as $key => $report ) {
            //colorize
            if ( isset($report['sent_on']) && $report['sent_on'] ) {
                $status = 'Sent';
                $color = 'gray';
            } else {
                $status = 'New';
                $color = 'black';
            }
            //draw reports rows
            $rows .= '<tr style="color:' . $color . '">'
                . '<td>' . Escape::escHtml($key + 1) . '.</td>'
                . '<td>' . Escape::escHtml(date('m-d-y H:i:s', $report['date'])) . '</td>'
                . '<td>' . Escape::escUrl($report['page_url']) . '</td>'
                . '<td>' . Escape::escHtml($report['lib_report']) . '</td>'
                . '<td>' . Escape::escUrl($report['work_url']) . '</td>'
                . '<td>' . Escape::escHtml($status) . '</td>'
                . '</tr>';
        }
        //draw main report table
        $reports_html = "
                <div id='negative_reports_div'>
                <table id='negative_reports_table'>
                <th colspan='6'>Failed connection reports</th>
                <tr>
                    <td>#</td>
                    <td><b>Date</b></td>
                    <td><b>Page URL</b></td>
                    <td><b>Report</b></td>
                    <td><b>Server IP</b></td>
                    <td><b>Status</b></td>
                </tr>"
            //attach reports rows
            . $rows
            . "</table>"
            . "</div>"
            . "<br/>";

        return $reports_html;
    }

    public function hasNegativeReports()
    {
        return $this->reports_count['bad'] > 0;
    }

    public function handleRequest(
        Cleantalk $cleantalk,
        CleantalkRequest $request,
        CleantalkResponse $request_response
    ) {

        // Succeeded connection
        if ( $request_response->errno === 0 && empty($request_response->errstr) ) {
            $this->reports_count['good']++;

            // Failed to connect. Add a negative report
        } else {
            $this->rotateReports();
            $this->reports_count['bad']++;
            $this->addReport(
                $request_response->errstr,
                $cleantalk->work_url,
                Helper::arrayObjectToArray($request)
            );
        }
        $this->updateStats();
    }

    public function sendEmail(array $unsent_reports_ids)
    {
        global $apbct;

        $selection = $this->getReportsDataByIds($unsent_reports_ids);

        if ( empty($selection) ) {
            return false;
        }

        $to = "welcome@cleantalk.org";
        $subject = "Connection report for " . Server::get('HTTP_HOST');
        $message = '
            <html lang="en">
                <head>
                    <title></title>
                </head>
                <body>
                    <p>From '
            . $this->reports_count['stat_since']
            . ' to ' . date('d M') . ' has been made '
            . $this->reports_count['total']
            . ' calls, where ' . $this->reports_count['good'] . ' were success and '
            . $this->reports_count['bad'] . ' were negative
                    </p>
                    <p>Negative report:</p>
                    <table>  <tr>
                <td>&nbsp;</td>
                <td><b>Date</b></td>
                <td><b>Page URL</b></td>
                <td><b>Library report</b></td>
                <td><b>Server IP</b></td>
              </tr>
              ';
        $counter = 0;

        foreach ( $selection as $key => $report ) {
            $message .= '<tr>'
                . '<td>' . ( ++$counter ) . '.</td>'
                . '<td>' . $report['date'] . '</td>'
                . '<td>' . $report['page_url'] . '</td>'
                . '<td>' . $report['lib_report'] . '</td>'
                . '<td>' . $report['work_url'] . '</td>'
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

        $message .= '</body></html>';

        $headers = "Content-type: text/html; charset=windows-1251 \r\n";
        $headers .= 'From: ' . ct_get_admin_email();

        $test = array(
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers
        );

        /** @psalm-suppress UnusedFunctionCall */
        if ( wp_mail($to, $subject, $message, $headers) ) {
            return true;
        }
        return false;
    }

    public function sendUnsentReports()
    {
        $unsent_reports_ids = $this->getUnsentReportsIds();
        if ( !empty($unsent_reports_ids) ) {
            /**
             * collect email data by IDs here
             **/
            if ( $this->sendEmail($unsent_reports_ids) ) {
                foreach ( $unsent_reports_ids as $report_id ) {
                    $this->setReportAsSent($report_id);
                }
            }
        }
    }

    public function hasUnsentReports()
    {
        return (bool)$this->getUnsentReportsIds();
    }

    public function wipeData()
    {
        global $apbct;
        $apbct->data['reports_count'] = array();
        $apbct->saveData();
    }

    public function remoteCallOutput()
    {
        /*
         * We can beatyfy it there if needed
         */
        return $this->reports_data;
    }
}
