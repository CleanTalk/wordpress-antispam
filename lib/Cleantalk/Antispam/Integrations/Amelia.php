<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class Amelia extends IntegrationBase
{
    public function doPrepareActions($argument)
    {
        // Amelia call path
        $call = isset($_GET['call']) && is_string($_GET['call']) ? $_GET['call'] : '';
        $call = strtolower('/' . ltrim(rawurldecode($call), '/'));

        return $call === '/bookings' || $call === '/payment/wc';
    }

    public function getDataForChecking($argument)
    {
        // Amelia accepts JSON and form data
        $payload = json_decode((string)file_get_contents('php://input'), true);
        if ( ! is_array($payload) ) {
            $payload = $_POST;
        }

        // The customer of the booking is created with the bookings customer email
        $emails_array = array();
        if ( ! empty($payload['bookings']) && is_array($payload['bookings']) ) {
            foreach ( $payload['bookings'] as $booking ) {
                if ( ! empty($booking['customer']['email']) && is_string($booking['customer']['email']) ) {
                    $emails_array[] = $booking['customer']['email'];
                }
            }
        }
        if ( ! empty($payload['customer']['email']) && is_string($payload['customer']['email']) ) {
            $emails_array[] = $payload['customer']['email'];
        }
        if ( ! empty($payload['email']) && is_string($payload['email']) ) {
            $emails_array[] = $payload['email'];
        }
        $email = $emails_array ? reset($emails_array) : '';

        $event_token = ! empty($payload['ct_bot_detector_event_token'])
            ? (string) $payload['ct_bot_detector_event_token']
            : Post::getString('ct_bot_detector_event_token');

        if ( isset($payload['ct_no_cookie_hidden_field']) ) {
            apbct_form__get_no_cookie_data(
                ['ct_no_cookie_hidden_field' => $payload['ct_no_cookie_hidden_field']]
            );
        }

        return array(
            'email'        => $email,
            'emails_array' => array_values(array_unique($emails_array)),
            'event_token'  => $event_token,
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
