<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\ApbctWP\Variables\Server;

class SFWUpdateSentinel
{
    /**
     * @var array
     */
    private $sentinel_ids = array();
    /**
     * @var array
     */
    private $last_fw_stats = array();

    /**
     * Add a firewall updating ID to the sentinel. The process set as started on current date.
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
            'started' => current_time('timestamp'),
            'finished' => false
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
     * Save firewall_updating_id as finished.
     * @param $id
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setIdAsFinished($id)
    {
        $this->getSentinelData();
        $this->sentinel_ids[$id]['finished'] = current_time('timestamp');
        $this->saveSentinelData();
    }

    private function getSeekingIdsList()
    {
        $this->getSentinelData();
        return $this->sentinel_ids;
    }

    private function sendSentinelEmail()
    {
        global $apbct;

        $ids_list = $this->getSeekingIdsList();

        if ( empty($ids_list) ) {
            return false;
        }

        $to = "support@cleantalk.org";
        $subject = "SFW failed updates report for " . Server::get('HTTP_HOST');
        $message = '
            <html lang="en">
                <head>
                    <title></title>
                </head>
                <body>
                    <p>
                    There were 3 unsuccesful SFW updates: 
                    </p>
                    <p>Negative report:</p>
                    <table style="border: 1px solid grey">  <tr style="border: 1px solid grey">
                    <td>&nbsp;</td>
                    <td><b>FW update ID</b></td>
                    <td><b>Started date</b></td>
                    <td><b>Finished date</b></td>
              </tr>
              ';
        $counter = 0;

        foreach ( $ids_list as $_id => $data ) {
            $finished = $data['finished'] ? date('m-d-y H:i:s', $data['finished']) : 'NO';
            $message .= '<tr style="border: 1px solid grey">'
                . '<td>' . (++$counter) . '.</td>'
                . '<td>' . $_id . '</td>'
                . '<td>' . date('m-d-y H:i:s', $data['started']) . '</td>'
                . '<td>' . $finished . '</td>'
                . '</tr>';
        }

        $message .= '</table>';
        $message .= '<br>';

        $last_fw_stats_html = '<table>';

        foreach ( $this->last_fw_stats as $row_key => $value ) {
            $last_fw_stats_html .= '<tr style="border: 1px solid grey"><td> ' . esc_html($row_key) . ': </td>';
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
     * Check if there are three or more unfinished firewall_updating_id on seek.
     * @return bool
     */
    private function hasTripleFailedUpdate()
    {
        $counter = 0;
        foreach ( $this->sentinel_ids as $id ) {
            if ( isset($id['started'], $id['finished'])
                &&
                ($id['started'] && !$id['finished']) ) {
                $counter++;
            }
        }
        if ( $counter >= 3 ) {
            return true;
        }
        return false;
    }

    /**
     * Remove firewall_updating_id`s that are started and finished.
     */
    private function unseekFinishedIds()
    {
        foreach ( $this->sentinel_ids as $id => $data ) {
            if ( isset($data['started'], $data['finished']) && $data['started'] && $data['finished'] ) {
                unset($this->sentinel_ids[$id]);
            }
        }
        $this->saveSentinelData();
    }

    /**
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function runWatchDog()
    {
        global $apbct;
        $this->getSentinelData();
        $this->unseekFinishedIds();
        if ( $this->hasTripleFailedUpdate() ) {
            if ( isset($apbct->settings['misc__send_connection_reports'])
                && $apbct->settings['misc__send_connection_reports'] == 1 ) {
                $this->sendSentinelEmail();
            }
            //Clear and waiting for next 3 unsucces FW updates
            $this->clearSentinelData();
        }
    }

    /**
     * Save data to global State object.
     */
    private function saveSentinelData()
    {
        global $apbct;
        $apbct->data['sentinel_data'] = $this->sentinel_ids;
        $apbct->saveData();
    }

    /**
     * Get data from global State object.
     */
    private function getSentinelData()
    {
        global $apbct;
        $this->sentinel_ids = $apbct->data['sentinel_data'];
        $this->last_fw_stats = $apbct->fw_stats;
    }

    /**
     * Clear data in the State object and class vars.
     */
    private function clearSentinelData()
    {
        $this->sentinel_ids = array();
        $this->last_fw_stats = array();
        $this->saveSentinelData();
    }
}
