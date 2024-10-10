<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\TT;

class PiotnetAddonsForElementorPro extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        Cookie::$force_alt_cookies_global = true;
        $nickname = '';
        $email = '';
        if ( Post::get('fields') ) {
            $fields = TT::toString(Post::get('fields'));
            $fields = stripslashes($fields);
            $fields = json_decode($fields, true);
            $fields = array_unique($fields, SORT_REGULAR);
            if ( $fields ) {
                $form_data = [];
                foreach ( $fields as $field ) {
                    if ( isset($field['name'], $field['value']) ) {
                        $form_data[$field['name']] = $field['value'];
                        if (empty($nickname) && strpos($field['name'], 'name') !== false) {
                            $nickname = $field['value'];
                        }
                        if (empty($email) && strpos($field['name'], 'email') !== false) {
                            $email = $field['value'];
                        }
                    }
                }
                $gfa_result = ct_gfa($form_data, $email, $nickname);
                if ( $event_token = Cookie::get('ct_bot_detector_event_token') ) {
                    $gfa_result['event_token'] = $event_token;
                }
                return $gfa_result;
            }
        }
        return $argument;
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
