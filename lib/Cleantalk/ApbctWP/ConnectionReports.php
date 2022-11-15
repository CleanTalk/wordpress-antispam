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
        'total' => 0
    );
    public $stat_since = null;
    private $db;
    private $cr_table_name;

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
        $this->stat_since = isset($apbct->data['reports_count']['stat_since'])
            ? $apbct->data['reports_count']['stat_since']
            : date('d M');
    }

    public function hasUnsentReports()
    {
        return (bool)$this->getUnsentReportsIds();
    }

    public function addReport(
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
            $this->reports_count['bad']++;
            $result = $this->addReport(
                $request_response->errstr,
                $cleantalk->work_url,
                Helper::arrayObjectToArray($request)
            );
        }
        $this->rotateReports();
        $this->updateStats();
    }

    public function sendReports()
    {
        $unsent_reports_ids = $this->getUnsentReportsIds();
        if ( !empty($unsent_reports_ids) ) {
            /**
             * collect email data by IDs here
             **/
            foreach ( $unsent_reports_ids as $report_id ) {
                $result = $this->setReportAsSent($report_id);
            }
        }
    }

    private function updateStats()
    {
        global $apbct;
        $this->reports_count['total'] = (int)$this->reports_count['good'] + (int)$this->reports_count['bad'];
        $apbct->data['reports_count'] = $this->reports_count;
        $apbct->data['reports_count']['stat_since'] = $this->stat_since;
        $apbct->saveData();
    }

    private function getUnsentReportsIds()
    {
        $result = array();
        $sql_result = $this->db->fetchAll(
            "SELECT id FROM " . $this->cr_table_name . "
            WHERE sent_on IS NULL
            "
        );

        if ( !empty($sql_result) ) {
            foreach ( $sql_result as $row ) {
                $result[] = $row['id'];
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

    private function rotateReports(){
        /**
         * keep 20 newest records
         */
        return true;
    }
}
