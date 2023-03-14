<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\Antispam\CleantalkRequest;
use Cleantalk\Antispam\CleantalkResponse;
use Cleantalk\ApbctWP\Variables\Server;
use CleantalkSP\SpbctWP\Variables\Cookie;

class JsErrorsReport
{
    /**
     * Instance of DB object
     * @var DB
     */
    private $db;
    
    /**
     * DB table name
     * @var
     */
    private $js_table_name;

    /**
     * JsErrorsReport constructor.
     * @param DB $db
     * @param $js_table_name
     */
    public function __construct(DB $db, $js_table_name)
    {
        global $apbct;
        $this->db = $db;
        $this->js_table_name = $js_table_name;
    }

    /**
     * Send email to support@cleantlk.org about js errors
     * @param bool $is_cron_task Set if this is a cron task
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function sendEmail($is_cron_task = false)
    {
        global $apbct;

        $data = $this->getData();

        if (empty($data)) {
            return false;
        }

        $to = "support@cleantalk.org";
        $subject = "JS errors report for " . Server::get('HTTP_HOST');
        $message = '
            <html lang="en">
                <head>
                    <title></title>
                </head>
                <body>
                    <p>From ddd
                    </p>'
                    . $data
                    . '<p>Negative report:</p>
                    <table>  <tr>
                <td>&nbsp;</td>
                <td><b>Date</b></td>
                <td><b>Page URL</b></td>
                <td><b>Library report</b></td>
                <td><b>Server IP</b></td>
                <td><b>Blocked via JS</b></td>
              </tr>
              ';

        $message .= '</table>';
        $message .= '<br>';

        // $show_connection_reports_link =
        //     substr(get_option('home'), -1) === '/' ? get_option('home') : get_option('home') . '/'
        //         . '?'
        //         . http_build_query([
        //             'plugin_name' => 'apbct',
        //             'spbc_remote_call_token' => md5($apbct->api_key),
        //             'spbc_remote_call_action' => 'debug',
        //             'show_only' => 'connection_reports',
        //         ]);

        // $message .= '<a href="' . $show_connection_reports_link . '" target="_blank">Show connection reports with remote call</a>';
        $message .= '<br>';

        $message .= $is_cron_task ? 'This is a cron task.' : 'This is a manual task.';
        $message .= '<br>';

        $message .= '</body></html>';

        $headers = "Content-type: text/html; charset=utf-8 \r\n";
        $headers .= 'From: ' . ct_get_admin_email();

        /** @psalm-suppress UnusedFunctionCall */
        // if ( wp_mail($to, $subject, $message, $headers) ) {
        //     return true;
        // }

        // return false;
    }

    private function getData()
    {
        return "test text";
    }
}
