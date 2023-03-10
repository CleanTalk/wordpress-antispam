<?php


namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\ApbctWP\Variables\Server;

class SFWUpdateSentinel
{
    private $sentinel_ids = array();
    private $last_fw_stats = array();

    public function __construct()
    {
    }

    public function seekUpdatingID($id)
    {
        $this->getData();
        if ( $this->getUpdatingIDData($id) ) {
            return false;
        }
        $this->sentinel_ids[$id] = array(
            'started' => current_time('timestamp'),
            'finished' => false
        );
        $this->saveData();
        return true;
    }


    private function getUpdatingIDData($id)
    {
        return isset($this->sentinel_ids[$id]) ? $this->sentinel_ids[$id] : false;
    }

    public function setUpdatingIDFinished($id)
    {
        $this->getData();
        $this->sentinel_ids[$id]['finished'] = current_time('timestamp');
        $this->saveData();
    }

    private function getSeekingUpdatingIDList()
    {
        $this->getData();
        return $this->sentinel_ids;
    }

    private function sendEmail()
    {
        global $apbct;

        $ids_list = $this->getSeekingUpdatingIDList();

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

    private function hasFailedUpdates()
    {
        $counter = 0;
        foreach ( $this->sentinel_ids as $id ) {
            if ( $id['started'] && !$id['finished'] ) {
                $counter++;
            }
        }
        if ( $counter >= 3 ) {
            return true;
        }
        return false;
    }

    private function unseekSucessUpdatingIDs()
    {
        foreach ( $this->sentinel_ids as $id => $data ) {
            if ( isset($data['started'], $data['finished']) && $data['started'] && $data['finished'] ) {
                unset($this->sentinel_ids[$id]);
            }
        }
        $this->saveData();
    }

    public function watchDog()
    {
        $this->getData();
        $this->unseekSucessUpdatingIDs();
        if ( $this->hasFailedUpdates() ) {
            global $apbct;
            if ( isset($apbct->settings['misc__send_connection_reports'])
                && $apbct->settings['misc__send_connection_reports'] == 1 ) {
                $this->sendEmail();
            }
            //waiting for next 3 unsucces FW updates
            $this->clearData();
        }
    }

    private function saveData()
    {
        global $apbct;
        $apbct->data['sentinel_data'] = $this->sentinel_ids;
        $apbct->saveData();
    }

    private function getData()
    {
        global $apbct;
        error_log('CTDEBUG: [' . __FUNCTION__ . '] []: ' . var_export(1,true));
        $this->sentinel_ids = $apbct->data['sentinel_data'];
        $this->last_fw_stats = $apbct->fw_stats;
    }

    private function clearData()
    {
        global $apbct;
        $apbct->data['sentinel_data'] = array();
        $this->sentinel_ids = array();
        $this->last_fw_stats = array();
        $apbct->saveData();
    }
}