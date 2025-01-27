<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;

class JsErrorsReport
{
    /**
     * Send email to support@cleantlk.org about js errors
     * @param bool $is_cron_task Set if this is a cron task
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function sendEmail($is_cron_task = false)
    {
        $data = $this->getData();

        if (empty($data)) {
            return false;
        }

        $to = "support@cleantalk.org";
        $subject = "JS errors report for " . TT::toString(Server::get('HTTP_HOST'));
        $message = '
            <html lang="en">
                <head>
                    <title></title>
                </head>
                <body>
                <p>Negative report:</p>
                    <table border="1">  <tr>
                <td><b>Error</b></td>
                <td><b>Url</b></td>
                <td><b>userAgent</b></td>
              </tr>
              ';

        $message .= $data;

        $message .= '</table>';
        $message .= '<br>';

        $message .= '</body></html>';

        $headers = "Content-type: text/html; charset=utf-8 \r\n";
        $headers .= 'From: ' . ct_get_admin_email();

        // /** @psalm-suppress UnusedFunctionCall */
        if ( wp_mail($to, $subject, $message, $headers) ) {
            delete_option(APBCT_JS_ERRORS);
            return true;
        }

        return false;
    }

    /**
     * @psalm-suppress UnusedVariable
     */
    private function getData()
    {
        $errors = get_option(APBCT_JS_ERRORS);

        if (!$errors) {
            return false;
        }

        $result = '';
        foreach ($errors as $errIndex => $errValue) {
            $result .= '<tr>';

            $result .= '<td>';
            $result .= isset($errValue['err']['msg']) ? $errValue['err']['msg'] : '';
            $result .= '</td>';

            $result .= '<td>';
            $result .= isset($errValue['url']) ? $errValue['url'] : '';
            $result .= '</td>';

            $result .= '<td>';
            $result .= isset($errValue['userAgent']) ? $errValue['userAgent'] : '';
            $result .= '</td>';

            $result .= '</tr>';
        }

        return $result;
    }
}
