<?php

/**
 * @psalm-suppress InvalidGlobal
 */

global $apbct;

use Cleantalk\Variables\Post;

// If this file is called directly, abort.
if ( ! defined('DOING_AJAX') ) {
    http_response_code(403);
    die('Not allowed.');
}

require_once(__DIR__ . '/../lib/autoloader.php');

$plugin_info = get_file_data(__DIR__ . '/../cleantalk.php', array('Version' => 'Version', 'Name' => 'Plugin Name'));
if ( ! defined('APBCT_VERSION') ) {
    define('APBCT_VERSION', $plugin_info['Version']);
}

$apbct                   = new \Cleantalk\ApbctWP\State('cleantalk', array('settings', 'data'));
$apbct->white_label      = $apbct->network_settings['multisite__white_label'];
$apbct->allow_custom_key = $apbct->network_settings['multisite__work_mode'] != 2;
$apbct->api_key          = ! is_multisite(
) || $apbct->allow_custom_key || $apbct->white_label ? $apbct->settings['apikey'] : $apbct->network_settings['apikey'];

/*******************************************************************/
/*******************  H A N D L E R S   H E R E  *******************/
/*******************************************************************/
function apbct_js_keys__get()
{
    require_once(__DIR__ . '/cleantalk-common.php');
    require_once(__DIR__ . '/cleantalk-pluggable.php');
    apbct_js_keys__get__ajax();
}

function apbct_get_pixel_url()
{
    require_once(__DIR__ . '/cleantalk-common.php');
    require_once(__DIR__ . '/cleantalk-pluggable.php');
    apbct_get_pixel_url__ajax();
}


function apbct_alt_session__save__AJAX()
{
    Cleantalk\ApbctWP\Variables\AltSessions::setFromRemote();
}

function apbct_alt_session__get__AJAX()
{
    Cleantalk\ApbctWP\Variables\AltSessions::getFromRemote();
}

/**
 * Checking email before POST from Ajax.php
 */
function apbct_email_check_before_post_from_custom_ajax()
{
    $email = trim(Post::get('email'));

    if ( $email ) {
        $result = \Cleantalk\ApbctWP\API::methodEmailCheck($email);
        if ( isset($result['data']) ) {
            die(json_encode(array('result' => $result['data'])));
        }
        die(json_encode(array('error' => 'ERROR_CHECKING_EMAIL')));
    }
    die(json_encode(array('error' => 'EMPTY_DATA')));
}
