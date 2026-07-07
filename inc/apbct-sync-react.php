<?php

use Cleantalk\ApbctWP\AdjustToEnvironmentModule\AdjustToEnvironmentHandler;
use Cleantalk\ApbctWP\AJAXService;

// Prevent direct call
/** @psalm-suppress ParadoxicalCondition */
if ( ! defined('ABSPATH') ) {
    die('Not allowed!');
}

add_action('wp_ajax_apbct_react_access_key_check', 'apbct_react_access_key_check');
add_action('wp_ajax_apbct_react_sfw_update', 'apbct_react_sfw_update');
add_action('wp_ajax_apbct_react_send_feedback', 'apbct_react_send_feedback');
add_action('wp_ajax_apbct_react_brief_data', 'apbct_react_brief_data');
add_action('wp_ajax_apbct_react_run_adjusting_env', 'apbct_react_run_adjusting_env');
add_action('wp_ajax_apbct_react_sync_end', 'apbct_react_sync_end');

/**
 * @param bool   $success
 * @param string $message
 */
function apbct_react_sync_json_response($success = true, $message = '')
{
    wp_send_json(array(
        'error'   => ! $success,
        'message' => $message,
        'success' => $success,
    ));
}

function apbct_react_access_key_check()
{
    global $apbct;

    AJAXService::checkAdminNonce();

    if ( ! current_user_can('activate_plugins') ) {
        apbct_react_sync_json_response(
            false,
            __('You do not have sufficient permissions to access this page.', 'cleantalk-spam-protect')
        );
    }

    $apbct->errorDeleteAll(true);

    $account_is_ok = (bool) ct_account_status_check($apbct->settings['apikey']);

    if ( $account_is_ok ) {
        $apbct->data['key_is_ok'] = true;
        $apbct->errorDelete('key_invalid key_get', 'save');
    } else {
        $apbct->data['key_is_ok'] = false;
        $apbct->errorAdd(
            'key_invalid',
            __('Testing failed. Please check the Access key.', 'cleantalk-spam-protect')
        );
    }

    $apbct->data['key_changed'] = false;
    $apbct->saveData();

    apbct_react_sync_json_response(
        $account_is_ok,
        $account_is_ok
            ? ''
            : __('Testing failed. Please check the Access key.', 'cleantalk-spam-protect')
    );
}

function apbct_react_sfw_update()
{
    global $apbct;

    AJAXService::checkAdminNonce();

    if ( ! current_user_can('activate_plugins') ) {
        apbct_react_sync_json_response(false);
    }

    if ( (int) $apbct->settings['sfw__enabled'] === 1 ) {
        $result = apbct_sfw_update__init(5);
        if ( ! empty($result['error']) ) {
            $apbct->errorAdd('sfw_update', $result['error']);
        }

        $result = ct_sfw_send_logs($apbct->settings['apikey']);
        if ( ! empty($result['error']) ) {
            $apbct->errorAdd('sfw_send_logs', $result['error']);
        }
    }

    $apbct->saveData();

    apbct_react_sync_json_response(true);
}

function apbct_react_send_feedback()
{
    AJAXService::checkAdminNonce();

    if ( ! current_user_can('activate_plugins') ) {
        apbct_react_sync_json_response(false);
    }

    ct_send_feedback('0:' . APBCT_AGENT);

    apbct_react_sync_json_response(true);
}

function apbct_react_brief_data()
{
    global $apbct;

    AJAXService::checkAdminNonce();

    if ( ! current_user_can('activate_plugins') ) {
        apbct_react_sync_json_response(false);
    }

    cleantalk_get_brief_data($apbct->settings['apikey']);

    apbct_react_sync_json_response(true);
}

function apbct_react_run_adjusting_env()
{
    AJAXService::checkAdminNonce();

    if ( ! current_user_can('activate_plugins') ) {
        apbct_react_sync_json_response(false);
    }

    $adjust = new AdjustToEnvironmentHandler();
    $adjust->handle();

    apbct_react_sync_json_response(true);
}

function apbct_react_sync_end()
{
    global $apbct;

    AJAXService::checkAdminNonce();

    if ( ! current_user_can('activate_plugins') ) {
        apbct_react_sync_json_response(false);
    }

    $apbct->saveData();

    apbct_react_sync_json_response(true);
}
