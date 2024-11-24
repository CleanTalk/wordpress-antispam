<?php

/*
  Plugin Name: Anti-Spam by CleanTalk
  Plugin URI: https://cleantalk.org
  Description: Max power, all-in-one, no Captcha, premium anti-spam plugin. No comment spam, no registration spam, no contact spam, protects any WordPress forms.
  Version: 6.45.3-dev
  Author: СleanTalk - Anti-Spam Protection <welcome@cleantalk.org>
  Author URI: https://cleantalk.org
  Text Domain: cleantalk-spam-protect
  Domain Path: /i18n
*/

use Cleantalk\ApbctWP\Activator;
use Cleantalk\ApbctWP\Deactivator;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Transaction;
use Cleantalk\ApbctWP\Variables\Get;

$plugin_info = get_file_data(__FILE__, array('Version' => 'Version', 'Name' => 'Plugin Name',));
define('APBCT_NAME', isset($plugin_info['Name']) ? $plugin_info['Name'] : 'Anti-Spam by CleanTalk');
define('APBCT_VERSION', isset($plugin_info['Version']) ? $plugin_info['Version'] : '1.0.0');

if ( ! defined('CLEANTALK_PLUGIN_DIR') ) {
    define('CLEANTALK_PLUGIN_DIR', dirname(__FILE__) . '/');
}

require_once(CLEANTALK_PLUGIN_DIR . 'lib/cleantalk-php-patch.php');  // Pathces fpr different functions which not exists
require_once(CLEANTALK_PLUGIN_DIR . 'lib/autoloader.php');

// attach bot detector to the public pages
if ( ! is_admin() && ! is_network_admin() ) {
    add_action('wp_enqueue_scripts', function () {
        if ( apbct_is_plugin_active('oxygen/functions.php') && Get::get('ct_builder') === 'true' ) {
            return;
        }

        $in_footer = defined('CLEANTALK_PLACE_PUBLIC_JS_SCRIPTS_IN_FOOTER') && CLEANTALK_PLACE_PUBLIC_JS_SCRIPTS_IN_FOOTER;
        wp_enqueue_script(
            'ct_bot_detector',
            'https://moderate.cleantalk.org/ct-bot-detector-wrapper.js',
            [],
            APBCT_VERSION,
            $in_footer
        );
    });
}

require_once(CLEANTALK_PLUGIN_DIR . 'cleantalk-full.php');

// Activation/deactivation functions must be in main plugin file.
// http://codex.wordpress.org/Function_Reference/register_activation_hook
register_activation_hook(__FILE__, 'apbct_activation');
function apbct_activation($network_wide)
{
    Activator::activation($network_wide);
}

register_deactivation_hook(__FILE__, 'apbct_deactivation');
function apbct_deactivation($network_wide)
{
    Deactivator::deactivation($network_wide);
}

register_uninstall_hook(__FILE__, 'apbct_uninstall');
function apbct_uninstall($network_wide)
{
    global $apbct;
    $apbct->settings['misc__complete_deactivation'] = 1;
    $apbct->saveSettings();
    Deactivator::deactivation($network_wide);
}

require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-functions-rc.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-functions.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-functions-cron.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-functions-sfw.php');

/**
 * Checks if the plugin is active
 *
 * @param string $plugin relative path from plugin folder like cleantalk-spam-protect/cleantalk.php
 *
 * @return bool
 */
function apbct_is_plugin_active($plugin)
{
    return in_array($plugin, (array)get_option('active_plugins', array())) || apbct_is_plugin_active_for_network($plugin);
}

/**
 * Do update actions if version is changed
 * ! we can`t place this function to the hook "upgrader_process_complete" !
 */
apbct_update_actions();

/**
 * Runs update actions for new version.
 *
 * @global State $apbct
 */
function apbct_update_actions()
{
    global $apbct;

    // Update logic
    if ( $apbct->plugin_version !== APBCT_VERSION ) {
        // Perform a transaction and exit transaction ID isn't match
        if ( ! Transaction::get('updater')->perform() ) {
            return;
        }

        // Main blog
        if ( is_main_site() ) {
            require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-updater.php');

            $result = apbct_run_update_actions($apbct->plugin_version, APBCT_VERSION);

            //If update is successful
            if ( $result === true ) {
                $apbct->data['plugin_version'] = APBCT_VERSION;
            }

            ct_send_feedback('0:' . APBCT_AGENT); // Send feedback to let cloud know about updated version.

        // Side blogs
        } else {
            $apbct->data['plugin_version'] = APBCT_VERSION;
        }
        $apbct->saveData();
    }
}
