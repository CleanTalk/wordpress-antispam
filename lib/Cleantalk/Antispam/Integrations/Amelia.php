<?php

namespace Cleantalk\Antispam\Integrations;

class Amelia extends IntegrationBase
{
    public function doPrepareActions($argument)
    {
        $call = isset($_GET['call']) ? (string) $_GET['call'] : '';

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

        return array('email' => $email);
    }

    public function doBlock($message)
    {
        remove_action('wp_ajax_wpamelia_api', array('AmeliaBooking\\Plugin', 'wpAmeliaApiCall'));
        remove_action('wp_ajax_nopriv_wpamelia_api', array('AmeliaBooking\\Plugin', 'wpAmeliaApiCall'));

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
