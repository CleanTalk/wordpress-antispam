<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TextPlate;
use Cleantalk\Common\TT;

class SFWUpdateSentinel
{
    use TextPlate;

    /**
     * @var array
     */
    private $sentinel_ids = array();
    /**
     * @var array
     */
    private $last_fw_stats = array();
    /**
     * @var int
     */
    private $number_of_failed_updates_to_check;
    /**
     * @var int
     */
    private $watchdog_cron_period;

    /**
     * SFWUpdateSentinel constructor.
     * @param int $number_of_failed_updates_to_check Default is 3
     */
    public function __construct($number_of_failed_updates_to_check = 3)
    {
        $this->number_of_failed_updates_to_check = $number_of_failed_updates_to_check;
        $this->watchdog_cron_period = 43200;
    }

    /**
     * Add a firewall updating ID to the sentinel.
     * @param string $id firewall_updating_id
     * @return bool True if added, false if id already exists.
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function seekId($id)
    {
        $this->getSentinelData();
        if ( $this->hasIdAdded($id) ) {
            return false;
        }
        $this->sentinel_ids[$id] = array(
            'started' => current_time('timestamp')
        );
        $this->saveSentinelData();
        return true;
    }


    /**
     * Check if a firewall_updating_id is already seeking.
     * @param string $id firewall_updating_id
     * @return bool
     */
    public function hasIdAdded($id)
    {
        return isset($this->sentinel_ids[$id]);
    }

    /**
     * Return list of seeking ids.
     * @return array
     */
    public function getSeekingIdsList()
    {
        $this->getSentinelData();
        return $this->sentinel_ids;
    }

    /**
     * Send email with seeking id failed and last fw_stats
     * @param array $ids_list
     */
    public function sendSentinelEmail(array $ids_list)
    {
        global $apbct;
        $this->getSentinelData();
        $from_email = ct_get_admin_email();
        $to_email = $apbct->data['email_for_reports'];
        $subject = self::textPlateRender('CleanTalk Service Report: SFW v{{version}} for {{host}}', [
            'host' => Server::getString('HTTP_HOST'),
            'version' => APBCT_VERSION,
        ]);
        $message = $this->prepareEmailContent($ids_list);
        $headers = self::textPlateRender("Content-type: text/html; charset=utf-8\r\nFrom: {{mail}}", [
            'mail' => $from_email,
        ]);
        $sent = @wp_mail($to_email, $subject, $message, $headers);
        $this->updateSentinelStats($sent);
    }

    /**
     * Prepare content of email.
     * @param array $idsList
     * @return string
     */
    public function prepareEmailContent(array $idsList): string
    {
        /**
         * @var State $apbct
         */
        global $apbct;

        // HTML
        $template = '
            <html lang="en">
            <head>
                <title>CleanTalk SFW Report</title>
                <style>
                    table { background: #eee; border: 1px solid #000; }
                    td, th { background: #eee; border: 1px solid #000; }
                    pre {background: #eee; border: 1px solid #000; }
                </style>
            </head>
            <body>
                <p>There were {{failed_count}} unsuccessful SFW updates in a row:</p>
                <p>Negative report:</p>
                <table>
                    <tr><th>&nbsp;</th><th>FW update ID</th><th>Started date</th></tr>
                    {{failed_rows}}
                </table>
            
                <p>Last FW stats:</p>
                <table>{{fw_stats}}</table>
                
                <p>Last queue details</p>
                <pre>{{queue}}</pre>
                
                <p>Remote SFW worker call test:{{test_rc_result}}</p>
                <p>Key is OK:{{key_is_ok}}</p>
                <p>License:{{license_status}}</p>
            
                <p>This report is sent by cron task on: {{current_time}}</p>
                <div>{{prev_report}}</div>
                <p>Site service_id: {{service_id}}</p>
            </body>
            </html>
        ';

        //test RC
        $test_rc_result = Helper::httpRequestRcToHostTest(
            'sfw_update__worker',
            array(
                'spbc_remote_call_token' => md5(TT::toString($apbct->api_key)),
                'spbc_remote_call_action' => 'sfw_update__worker',
                'plugin_name' => 'apbct'
            )
        );
        $test_rc_result = substr(
            TT::toString($test_rc_result, 'INVALID RC RESULT'),
            0,
            300
        );

        //license is active
        $license_status = $apbct->data['moderate'] ? 'ACTIVE' : '<b>INACTIVE</b>';

        //key is ok
        $key_is_ok = $apbct->data['key_is_ok'] ? 'TRUE' : '<b>FALSE</b>';

        return self::textPlateRender($template, [
            'failed_count' => (string)count($idsList),
            'failed_rows'  => $this->getFailedUpdatesHTML($idsList),
            'fw_stats'     => $this->getFWStatsHTML(),
            'queue'     => $this->getQueueJSONPretty(),
            'test_rc_result' => $test_rc_result,
            'license_status' => $license_status,
            'key_is_ok' => $key_is_ok,
            'current_time' => current_time('m-d-y H:i:s'),
            'prev_report'  => $this->getPrevReportHTML($apbct->data),
            'service_id'   => TT::toString($apbct->data['service_id']),
        ]);
    }

    /**
     * Get failed updates HTML chunk.
     * @param array $idsList
     * @return string
     */
    public function getFailedUpdatesHTML($idsList)
    {
        $failedRowsHtml = '';
        $counter = 0;
        foreach ($idsList as $id => $data) {
            $date = date('m-d-y H:i:s', TT::getArrayValueAsInt($data, 'started')) ?: 'Unknown date';
            $failedRowsHtml .= self::textPlateRender(
                '<tr><td>{{index}}.</td><td>{{id}}</td><td>{{date}}</td></tr>',
                [
                    'index' => (string)($counter + 1),
                    'id'    => (string)$id,
                    'date'  => $date,
                ]
            );
        }
        return $failedRowsHtml;
    }

    /**
     * Get Firewall Stats HTML chunk.
     * @return string
     */
    public function getFWStatsHTML()
    {
        $fwStatsHtml = '';
        foreach ($this->last_fw_stats as $key => $value) {
            if ($key === 'updating_folder' && !empty($value)) {
                preg_match('/^(.*?)[\/\\\]wp-content.*$/', $value, $matches);
                if (!empty($matches[1])) {
                    $value = str_replace($matches[1], '', $value);
                }
            }
            $fwStatsHtml .= self::textPlateRender(
                '<tr><td>{{key}}:</td><td>{{value}}</td></tr>',
                [
                    'key'   => $key,
                    'value' => !is_array($value) && !empty($value) ? (string)$value : 'No data',
                ]
            );
        }
        return $fwStatsHtml;
    }

    /**
     * Get previous reports HTML chunk.
     * @return string
     */
    public function getPrevReportHTML($apbct_data)
    {
        $prevDate = !empty($apbct_data['sentinel_data']['prev_sent_try']['date'])
            ? date('m-d-y H:i:s', $apbct_data['sentinel_data']['prev_sent_try']['date'])
            : 'unknown date';
        return !empty($prevDate)
            ? "<p>Previous SFW failed update report was sent on {$prevDate}</p>"
            : '<p>There is no previous SFW failed update report.</p>';
    }

    /**
     * Get JSON pretty string to show queue status.
     * @return string
     */
    public function getQueueJSONPretty()
    {
        $queue = get_option('cleantalk_sfw_update_queue');
        $queue = is_array($queue) ? $queue : array('Last queue not found or invalid.');
        $queue = json_encode($queue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return $queue !== false ? $queue : 'Can not construct queue JSON.';
    }

    public function updateSentinelStats($sent)
    {
        global $apbct;

        $apbct->data['sentinel_data']['prev_sent_try'] = $apbct->data['sentinel_data']['last_sent_try'] ?? false;
        $apbct->data['sentinel_data']['last_sent_try'] = [
            'date'    => current_time('timestamp'),
            'success' => $sent,
        ];
        $apbct->saveData();
    }

    /**
     * Check if there are a number of unfinished firewall_updating_id on seek.
     * @return bool
     */
    public function hasNumberOfFailedUpdates($number)
    {
        if ( count($this->sentinel_ids) >= $number ) {
            return true;
        }
        return false;
    }

    /**
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function runWatchDog()
    {
        global $apbct;
        $this->getSentinelData();
        if ( $this->hasNumberOfFailedUpdates($this->number_of_failed_updates_to_check) ) {
            $ids_list = $this->getSeekingIdsList();
            if (
                !empty($ids_list) &&
                isset($apbct->settings['misc__send_connection_reports']) &&
                $apbct->settings['misc__send_connection_reports'] == 1
            ) {
                $this->sendSentinelEmail($ids_list);
            }
            //Clear and waiting for next unsucces FW updates
            $this->clearSentinelData();
        }
    }

    /**
     * Get cron period in seconds.
     * @return int
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getWatchDogCronPeriod()
    {
        return !empty($this->watchdog_cron_period) ? $this->watchdog_cron_period : 43200;
    }

    /**
     * Save data to global State object.
     */
    private function saveSentinelData()
    {
        global $apbct;
        $apbct->data['sentinel_data']['ids'] = $this->sentinel_ids;
        $apbct->saveData();
    }

    /**
     * Get data from global State object.
     */
    private function getSentinelData()
    {
        global $apbct;
        $this->sentinel_ids = $apbct->data['sentinel_data']['ids'];
        $this->last_fw_stats = $apbct->fw_stats;
    }

    /**
     * Clear data in the State object and class vars.
     */
    public function clearSentinelData()
    {
        $this->sentinel_ids = array();
        $this->last_fw_stats = array();
        $this->saveSentinelData();
    }
}
