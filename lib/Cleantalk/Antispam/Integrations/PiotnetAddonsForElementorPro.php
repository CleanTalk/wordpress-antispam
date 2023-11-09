<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;

class PiotnetAddonsForElementorPro extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( Post::get('fields') ) {
            $fields = Post::get('fields');
            $fields = stripslashes($fields);
            $fields = json_decode($fields, true);
            $fields = array_unique($fields, SORT_REGULAR);
            if ( $fields ) {
                $form_data = [];
                foreach ( $fields as $field ) {
                    if ( isset($field['name'], $field['value']) ) {
                        $form_data[$field['name']] = $field['value'];
                    }
                }
                if ( $event_token = Cookie::get('ct_bot_detector_event_token') ) {
                    $form_data['event_token'] = $event_token;
                }
                return $form_data;
            }
        }
    }

    public function doBlock($message)
    {
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked'     => true,
                        'comment'     => $message,
                        'stop_script' => apbct__stop_script_after_ajax_checking()
                    )
                )
            )
        );
    }
}
