<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;

class SFWUpdateSentinel
{
    /**
     * @var array
     */
    private $sentinel_ids = array();
    /**
     * @var array
     */
    private $last_fw_stats = array();    /**
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
    private function hasIdAdded($id)
    {
        return isset($this->sentinel_ids[$id]);
    }

    /**
     * Return list of seeking ids.
     * @return array
     */
    private function getSeekingIdsList()
    {
        $this->getSentinelData();
        return $this->sentinel_ids;
    }

    /**
     * Send email with seeking id failed and last fw_stats
     */
    private function sendSentinelEmail()
    {
        global $apbct;

        $ids_list = $this->getSeekingIdsList();

        if ( empty($ids_list) ) {
            return false;
        }

        $to = $apbct->data['wl_support_email'];
        $subject = "SFW failed updates report for " . TT::toString(Server::get('HTTP_HOST'));
        $message = '
            <html lang="en">
                <head>
                    <title></title>
                    <style type="text/css">
                    table {
                        background: #eee; 
                        border: 1px solid #000; 
                    }
                    td, th {
                        background: #eee; 
                        border: 1px solid #000; 
                    }
                    </style>
                </head>
                <body>
                    <p>
                    There were ' . count($ids_list) . ' unsuccesful SFW updates in a row: 
                    </p>
                    <p>Negative report:</p>
                    <table><tr>
                    <th>&nbsp;</th>
                    <th><b>FW update ID</b></th>
                    <th><b>Started date</b></th>
              </tr>
              ';
        $counter = 0;

        foreach ( $ids_list as $_id => $data ) {
            $date = date('m-d-y H:i:s', TT::getArrayValueAsInt($data, 'started'));
            $date = is_string($date) ? $date : 'Unknown date';
            $message .= '<tr>'
                . '<td>' . (++$counter) . '.</td>'
                . '<td>' . $_id . '</td>'
                . '<td>' . $date . '</td>'
                . '</tr>';
        }

        $message .= '</table>';
        $message .= '<br>';

        $last_fw_stats_html = '<table>';

        foreach ( $this->last_fw_stats as $row_key => $value ) {
            $last_fw_stats_html .= '<tr><td> ' . esc_html($row_key) . ': </td>';
            //clear root path
            if ( $row_key === 'updating_folder' && !empty($value) ) {
                preg_match('/^(.*?)[\/\\\]wp-content.*$/', $value, $to_delete);
                if ( !empty($to_delete[1]) ) {
                    $value = str_replace($to_delete[1], "", $value);
                }
            }
            if ( !is_array($value) && !empty($value) ) {
                $last_fw_stats_html .= '<td>' . esc_html($value) . '</td>';
            } else {
                $last_fw_stats_html .= '<td>No data</td>';
            }
            $last_fw_stats_html .= '</tr>';
        }

        $last_fw_stats_html .= '</table>';


        $show_connection_reports_link =
            substr(get_option('home'), -1) === '/' ? get_option('home') : get_option('home') . '/'
                . '?'
                . http_build_query([
                    'plugin_name' => 'apbct',
                    'spbc_remote_call_token' => md5($apbct->api_key),
                    'spbc_remote_call_action' => 'debug',
                    'show_only' => 'connection_reports',
                ]);
        $message .= '<p>Last FW stats:</p>';
        $message .= '<p>' . $last_fw_stats_html . '</p>';
        $message .= '<a href="' . $show_connection_reports_link . '" target="_blank">Show connection reports with remote call</a>';
        $message .= '<br>';

        $message .= '<p>This report is sent by cron task on: ' . current_time('m-d-y H:i:s') . '</p>';

        $prev_date = !empty($apbct->data['sentinel_data']['prev_sent_try']['date'])
            ? date('m-d-y H:i:s', $apbct->data['sentinel_data']['prev_sent_try']['date'])
            : '';

        if ( !empty($prev_date) ) {
            $message .= '<p>Previous SFW failed update report were sent on '  . $prev_date . '</p>';
        } else {
            $message .= '<p>There is no previous SFW failed update report.</p>';
        }

        $message .= '</body></html>';

        $headers = "Content-type: text/html; charset=utf-8 \r\n";
        $headers .= 'From: ' . ct_get_admin_email();

        $sent = false;

        /** @psalm-suppress UnusedFunctionCall */
        if ( wp_mail($to, $subject, $message, $headers) ) {
            $sent = true;
        }

        $apbct->data['sentinel_data']['prev_sent_try'] = !empty($apbct->data['sentinel_data']['last_sent_try'])
            ? $apbct->data['sentinel_data']['last_sent_try']
            : false;

        $apbct->data['sentinel_data']['last_sent_try'] = array(
            'date' => current_time('timestamp'),
            'success' => $sent
        );
        $apbct->saveData();
    }

    /**
     * Check if there are a number of unfinished firewall_updating_id on seek.
     * @return bool
     */
    private function hasNumberOfFailedUpdates($number)
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
            if ( isset($apbct->settings['misc__send_connection_reports'])
                && $apbct->settings['misc__send_connection_reports'] == 1 ) {
                $this->sendSentinelEmail();
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
