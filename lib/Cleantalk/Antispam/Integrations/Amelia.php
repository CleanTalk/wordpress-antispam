<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;

class Amelia extends IntegrationBase
{
    public function doPrepareActions($argument)
    {
        $call = Get::getString('call');

        if ( $call !== '/bookings' ) {
            return false;
        }

        return true;
    }

    public function getDataForChecking($argument)
    {
        $raw = file_get_contents('php://input');
        if ( ! $raw ) {
            return null;
        }

        $payload = json_decode($raw, true);
        if ( ! is_array($payload) ) {
            return null;
        }

        $email = '';
        if ( ! empty($payload['customer']['email']) ) {
            $email = (string) $payload['customer']['email'];
        } elseif ( ! empty($payload['bookings'][0]['customer']['email']) ) {
            $email = (string) $payload['bookings'][0]['customer']['email'];
        } elseif ( ! empty($payload['email']) ) {
            $email = (string) $payload['email'];
        }

        if ( ! $email ) {
            return null;
        }

        $event_token = ! empty($payload['ct_bot_detector_event_token'])
            ? (string) $payload['ct_bot_detector_event_token']
            : Post::getString('ct_bot_detector_event_token');

        return array(
            'email'       => $email,
            'event_token' => $event_token,
        );
    }

    public function doBlock($message)
    {
        status_header(403);
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode(array(
            'message' => $message,
            'data'    => array(
                'message' => $message,
                'reason'  => $message,
            ),
        ));
        exit;
    }
}
