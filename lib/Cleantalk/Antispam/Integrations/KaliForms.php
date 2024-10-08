<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\TT;

class KaliForms extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( ! isset($_POST['data']) || !Post::hasString('action', 'kaliforms_form_process')) {
            return null;
        }

        // Kali form integration
        $data = TT::toArray(Post::get('data'));
        apbct_form__get_no_cookie_data($data);
        $gfa_checked_data = ct_get_fields_any($data);
        if (isset($data['ct_bot_detector_event_token'])) {
            $gfa_checked_data['event_token'] = $data['ct_bot_detector_event_token'];
        }

        $gfa_checked_data['message'] = isset($gfa_checked_data['message']) ? apbct__filter_form_data($gfa_checked_data['message']) : '';

        return $gfa_checked_data;
    }

    public function doBlock($message)
    {
        // Kali Form Integration
        if ( Post::hasString('action', 'kaliforms_form_process') ) {
            die(
                json_encode(array(
                    'status'            => 'ok',
                    'thank_you_message' => $message
                ))
            );
        }
    }
}
