<?php

/*
  Plugin Name: Anti-Spam by CleanTalk
  Plugin URI: https://cleantalk.org
  Description: Max power, all-in-one, no Captcha, premium anti-spam plugin. No comment spam, no registration spam, no contact spam, protects any WordPress forms.
  Version: 5.178
  Author: СleanTalk <welcome@cleantalk.org>
  Author URI: https://cleantalk.org
  Text Domain: cleantalk-spam-protect
  Domain Path: /i18n
*/

use Cleantalk\ApbctWP\Activator;
use Cleantalk\ApbctWP\AdminNotices;
use Cleantalk\ApbctWP\API;
use Cleantalk\ApbctWP\CleantalkUpgrader;
use Cleantalk\ApbctWP\CleantalkUpgraderSkin;
use Cleantalk\ApbctWP\CleantalkUpgraderSkinDeprecated;
use Cleantalk\ApbctWP\Cron;
use Cleantalk\ApbctWP\DB;
use Cleantalk\ApbctWP\Deactivator;
use Cleantalk\ApbctWP\Firewall\AntiCrawler;
use Cleantalk\ApbctWP\Firewall\AntiFlood;
use Cleantalk\ApbctWP\Firewall\SFW;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\RemoteCalls;
use Cleantalk\ApbctWP\RestController;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Transaction;
use Cleantalk\ApbctWP\UpdatePlugin\DbTablesCreator;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\Common\DNS;
use Cleantalk\Common\Firewall;
use Cleantalk\Common\Schema;
use Cleantalk\Variables\Get;
use Cleantalk\Variables\Post;
use Cleantalk\Variables\Request;
use Cleantalk\Variables\Server;

global $apbct, $wpdb, $pagenow;

$cleantalk_executed = false;

// Getting version form main file (look above)
$plugin_info           = get_file_data(__FILE__, array('Version' => 'Version', 'Name' => 'Plugin Name',));
$plugin_version__agent = $plugin_info['Version'];
// Converts xxx.xxx.xx-dev to xxx.xxx.2xx
// And xxx.xxx.xx-fix to xxx.xxx.1xx
if ( preg_match('@^(\d+)\.(\d+)\.(\d{1,2})-(dev|fix)$@', $plugin_version__agent, $m) ) {
    $plugin_version__agent =
        $m[1]
        . '.'
        . $m[2]
        . '.'
        . ($m[4] === 'dev' ? '2' : '1')
        . str_pad($m[3], 2, '0', STR_PAD_LEFT);
}

// Common params
define('APBCT_NAME', $plugin_info['Name']);
define('APBCT_VERSION', $plugin_info['Version']);
define('APBCT_URL_PATH', plugins_url('', __FILE__));  //HTTP path.   Plugin root folder without '/'.
define('APBCT_CSS_ASSETS_PATH', plugins_url('css', __FILE__));  //CSS assets path.   Plugin root folder without '/'.
define('APBCT_JS_ASSETS_PATH', plugins_url('js', __FILE__));  //JS assets path.   Plugin root folder without '/'.
define('APBCT_IMG_ASSETS_PATH', plugins_url('inc/images', __FILE__));  //IMAGES assets path.   Plugin root folder without '/'.
define('APBCT_DIR_PATH', dirname(__FILE__) . '/');          //System path. Plugin root folder with '/'.
define('APBCT_PLUGIN_BASE_NAME', plugin_basename(__FILE__));          //Plugin base name.
define(
    'APBCT_CASERT_PATH',
    file_exists(ABSPATH . WPINC . '/certificates/ca-bundle.crt') ? ABSPATH . WPINC . '/certificates/ca-bundle.crt' : ''
); // SSL Serttificate path

// API params
define('APBCT_AGENT', 'wordpress-' . $plugin_version__agent);
define('APBCT_MODERATE_URL', 'https://moderate.cleantalk.org'); //Api URL

// Option names
define('APBCT_DATA', 'cleantalk_data');             //Option name with different plugin data.
define('APBCT_SETTINGS', 'cleantalk_settings');         //Option name with plugin settings.
define('APBCT_NETWORK_SETTINGS', 'cleantalk_network_settings'); //Option name with plugin network settings.
define('APBCT_DEBUG', 'cleantalk_debug');            //Option name with a debug data. Empty by default.

// WordPress Multisite
define('APBCT_WPMS', (is_multisite() ? true : false)); // WMPS is enabled

// Different params
define('APBCT_REMOTE_CALL_SLEEP', 5); // Minimum time between remote call

if ( ! defined('CLEANTALK_PLUGIN_DIR') ) {
    define('CLEANTALK_PLUGIN_DIR', dirname(__FILE__) . '/');
}

// PHP functions patches
require_once(CLEANTALK_PLUGIN_DIR . 'lib/cleantalk-php-patch.php');  // Pathces fpr different functions which not exists

// Base classes
require_once(CLEANTALK_PLUGIN_DIR . 'lib/autoloader.php');                // Autoloader

require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-pluggable.php');  // Pluggable functions
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-common.php');

// Global ArrayObject with settings and other global variables
$apbct = new State('cleantalk', array('settings', 'data', 'debug', 'errors', 'remote_calls', 'stats', 'fw_stats'));

$apbct->base_name = 'cleantalk-spam-protect/cleantalk.php';

$apbct->plugin_request_id = md5(microtime()); // Identify plugin execution

$apbct->logo                 = plugin_dir_url(__FILE__) . 'inc/images/logo.png';
$apbct->logo__small          = plugin_dir_url(__FILE__) . 'inc/images/logo_small.png';
$apbct->logo__small__colored = plugin_dir_url(__FILE__) . 'inc/images/logo_color.png';

// Customize \Cleantalk\ApbctWP\State
// Account status

$apbct->white_label      = $apbct->network_settings['multisite__white_label'];
$apbct->allow_custom_key = $apbct->network_settings['multisite__work_mode'] != 2;
$apbct->plugin_name      = $apbct->network_settings['multisite__white_label__plugin_name'] ? $apbct->network_settings['multisite__white_label__plugin_name'] : APBCT_NAME;
$apbct->api_key          = ! APBCT_WPMS || $apbct->allow_custom_key || $apbct->white_label ? $apbct->settings['apikey'] : $apbct->network_settings['apikey'];
$apbct->key_is_ok        = ! APBCT_WPMS || $apbct->allow_custom_key || $apbct->white_label ? $apbct->data['key_is_ok'] : $apbct->network_data['key_is_ok'];
$apbct->moderate         = ! APBCT_WPMS || $apbct->allow_custom_key || $apbct->white_label ? $apbct->data['moderate'] : $apbct->network_data['moderate'];

$apbct->data['user_counter']['since']       = isset($apbct->data['user_counter']['since']) ? $apbct->data['user_counter']['since'] : date(
    'd M'
);
$apbct->data['connection_reports']['since'] = isset($apbct->data['connection_reports']['since']) ? $apbct->data['user_counter']['since'] : date(
    'd M'
);

$apbct->firewall_updating = (bool)$apbct->fw_stats['firewall_updating_id'];

$apbct->settings_link = is_network_admin() ? 'settings.php?page=cleantalk' : 'options-general.php?page=cleantalk';

if ( ! $apbct->white_label ) {
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalkWidget.php');
}

// Disabling comments
if ( $apbct->settings['comments__disable_comments__all'] || $apbct->settings['comments__disable_comments__posts'] || $apbct->settings['comments__disable_comments__pages'] || $apbct->settings['comments__disable_comments__media'] ) {
    \Cleantalk\Antispam\DisableComments::getInstance();
}

// Email encoder
if (
    $apbct->key_is_ok &&
    ( ! is_admin() || apbct_is_ajax() ) &&
    $apbct->settings['data__email_decoder'] ) {
    \Cleantalk\Antispam\EmailEncoder::getInstance();
}

add_action('rest_api_init', 'apbct_register_my_rest_routes');
function apbct_register_my_rest_routes()
{
    $controller = new RestController();
    $controller->register_routes();
}

// Alt cookies via WP ajax handler
add_action('wp_ajax_nopriv_apbct_alt_session__save__AJAX', 'apbct_alt_session__save__WP_AJAX');
add_action('wp_ajax_apbct_alt_session__save__AJAX', 'apbct_alt_session__save__WP_AJAX');
function apbct_alt_session__save__WP_AJAX()
{
    Cleantalk\ApbctWP\Variables\AltSessions::setFromRemote();
}

// Get JS via WP ajax handler
add_action('wp_ajax_nopriv_apbct_js_keys__get', 'apbct_js_keys__get__ajax');
add_action('wp_ajax_apbct_js_keys__get', 'apbct_js_keys__get__ajax');

// Get Pixel URL via WP ajax handler
add_action('wp_ajax_nopriv_apbct_get_pixel_url', 'apbct_get_pixel_url__ajax');
add_action('wp_ajax_apbct_apbct_get_pixel_url', 'apbct_get_pixel_url__ajax');

// Force ajax checking for external forms
add_action('wp_ajax_nopriv_cleantalk_force_ajax_check', 'ct_ajax_hook');
add_action('wp_ajax_cleantalk_force_ajax_check', 'ct_ajax_hook');

// Checking email before POST
add_action('wp_ajax_nopriv_apbct_email_check_before_post', 'apbct_email_check_before_post');

// Database prefix
global $wpdb;
$apbct->db_prefix = ! APBCT_WPMS || $apbct->allow_custom_key || $apbct->white_label ? $wpdb->prefix : $wpdb->base_prefix;
$apbct->db_prefix = ! $apbct->white_label && defined('CLEANTALK_ACCESS_KEY') ? $wpdb->base_prefix : $wpdb->prefix;

/** @todo HARDCODE FIX */
if ( $apbct->plugin_version === '1.0.0' ) {
    $apbct->plugin_version = '5.100';
}
// Do update actions if version is changed
apbct_update_actions();

/**
 * @psalm-suppress TypeDoesNotContainType
 */
add_action('init', function () {
    global $apbct;
    // Self cron
    $ct_cron = new Cron();
    $tasks_to_run = $ct_cron->checkTasks(); // Check for current tasks. Drop tasks inner counters.
    if (
        $tasks_to_run && // There are tasks to run
        ! RemoteCalls::check() && // Do not do CRON in remote call action
        (
            ! defined('DOING_CRON') ||
            (defined('DOING_CRON') && DOING_CRON !== true)
        )
    ) {
        $cron_res = $ct_cron->runTasks($tasks_to_run);
        if ( is_array($cron_res) ) {
            foreach ( $cron_res as $_task => $res ) {
                if ( $res === true ) {
                    $apbct->errorDelete('cron', 'save_data');
                } else {
                    $apbct->errorAdd('cron', $res);
                }
            }
        }
    }
});

if ( $apbct->settings && $apbct->key_is_ok ) {
    // Remote calls
    if ( RemoteCalls::check() ) {
        RemoteCalls::perform();
    }
}

//Delete cookie for admin trial notice
add_action('wp_logout', 'apbct__hook__wp_logout__delete_trial_notice_cookie');

// Set cookie only for public pages and for non-AJAX requests
if ( ! is_admin() && ! apbct_is_ajax() && ! defined('DOING_CRON')
     && empty(Post::get('ct_checkjs_register_form')) // Buddy press registration fix
     && empty(Get::get('ct_checkjs_search_default')) // Search form fix
     && empty(Post::get('action')) //bbPress
) {
    add_action('template_redirect', 'apbct_cookie', 2);
    add_action('template_redirect', 'apbct_store__urls', 2);
    if ( empty($_POST) && empty($_GET) ) {
        apbct_cookie();
        apbct_store__urls();
    }
}

// Early checks

// Iphorm
if (
    Post::get('iphorm_ajax') !== '' &&
    Post::get('iphorm_id') !== '' &&
    Post::get('iphorm_uid') !== ''
) {
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-validate.php');
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public.php');
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-integrations.php');
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-ajax.php');
    ct_ajax_hook();
}

// Facebook
if ( $apbct->settings['forms__general_contact_forms_test'] == 1
     && ( Post::get('action') === 'fb_intialize')
     && ! empty(Post::get('FB_userdata'))
) {
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-validate.php');
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public.php');
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-integrations.php');
    if ( apbct_is_user_enable() ) {
        ct_registration_errors(null);
    }
}

$apbct_active_integrations = array(
    'ContactBank'         => array(
        'hook'    => 'contact_bank_frontend_ajax_call',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'FluentForm'          => array(
        'hook'    => 'fluentform_before_insert_submission',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'ElfsightContactForm' => array(
        'hook'    => 'elfsight_contact_form_mail',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'EstimationForm'      => array('hook' => 'send_email', 'setting' => 'forms__contact_forms_test', 'ajax' => true),
    'LandingPageBuilder'  => array(
        'hook'    => 'ulpb_formBuilderEmail_ajax',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'Rafflepress'         => array(
        'hook'    => 'rafflepress_lite_giveaway_api',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'SimpleMembership'    => array(
        'hook'    => 'swpm_front_end_registration_complete_user_data',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'WpMembers'           => array(
        'hook'    => 'wpmem_pre_register_data',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'Wpdiscuz'            => array(
        'hook'    => array('wpdAddComment', 'wpdAddInlineComment'),
        'setting' => 'forms__comments_test',
        'ajax'    => true
    ),
    'Forminator'          => array(
        'hook'    => 'forminator_submit_form_custom-forms',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'EaelLoginRegister'   => array(
        'hook'    => array(
            'eael/login-register/before-register',
            'wp_ajax_nopriv_eael/login-register/before-register',
            'wp_ajax_eael/login-register/before-register'
        ),
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'CalculatedFieldsForm' => array(
        'hook'    => 'cpcff_process_data',
        'setting' => 'forms__general_contact_forms_test',
        'ajax'    => false
    ),
    'OvaLogin' => array(
        'hook'    => 'login_form_register',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'GiveWP' => array(
        'hook'    => 'give_checkout_error_checks',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'VisualFormBuilder' => array(
        'hook'    => array('vfb_isbot','vfb_isBot'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'EventsManager' => array(
        'hook'    => 'em_booking_validate',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'PlansoFormBuilder' => array(
        'hook'    => 'psfb_validate_form_request',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'NextendSocialLogin' => array(
        'hook'    => 'nsl_before_register',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
);
new  \Cleantalk\Antispam\Integrations($apbct_active_integrations, (array)$apbct->settings);

// Ninja Forms. Making GET action to POST action
if (
    apbct_is_in_uri('admin-ajax.php') &&
    sizeof($_POST) > 0 &&
    Get::get('action') === 'ninja_forms_ajax_submit'
) {
    $_POST['action'] = 'ninja_forms_ajax_submit';
}

add_action('wp_ajax_nopriv_ninja_forms_ajax_submit', 'apbct_form__ninjaForms__testSpam', 1);
add_action('wp_ajax_ninja_forms_ajax_submit', 'apbct_form__ninjaForms__testSpam', 1);
add_action('wp_ajax_nopriv_nf_ajax_submit', 'apbct_form__ninjaForms__testSpam', 1);
add_action('wp_ajax_nf_ajax_submit', 'apbct_form__ninjaForms__testSpam', 1);
add_action('ninja_forms_process', 'apbct_form__ninjaForms__testSpam', 1); // Depricated ?

// SeedProd Coming Soon Page Pro integration
add_action('wp_ajax_seed_cspv5_subscribe_callback', 'apbct_form__seedprod_coming_soon__testSpam', 1);
add_action('wp_ajax_nopriv_seed_cspv5_subscribe_callback', 'apbct_form__seedprod_coming_soon__testSpam', 1);
add_action('wp_ajax_seed_cspv5_contactform_callback', 'apbct_form__seedprod_coming_soon__testSpam', 1);
add_action('wp_ajax_nopriv_seed_cspv5_contactform_callback', 'apbct_form__seedprod_coming_soon__testSpam', 1);

// The 7 theme contact form integration
add_action('wp_ajax_nopriv_dt_send_mail', 'apbct_form__the7_contact_form', 1);
add_action('wp_ajax_dt_send_mail', 'apbct_form__the7_contact_form', 1);

// Custom register form (ticket_id=13668)
add_action('website_neotrends_signup_fields_check', function ($username, $fields) {
    $ip        = Helper::ipGet('real', false);
    $ct_result = ct_test_registration($username, $fields['email'], $ip);
    if ( $ct_result['allow'] == 0 ) {
        ct_die_extended($ct_result['comment']);
    }
}, 1, 2);

// INEVIO theme integration
add_action('wp_ajax_contact_form_handler', 'apbct_form__inevio__testSpam', 1);
add_action('wp_ajax_nopriv_contact_form_handler', 'apbct_form__inevio__testSpam', 1);

// Enfold Theme contact form
add_filter('avf_form_send', 'apbct_form__enfold_contact_form__test_spam', 4, 10);

// Profile Builder integration
add_filter('wppb_output_field_errors_filter', 'apbct_form_profile_builder__check_register', 1, 3);

// Advanced Classifieds & Directory Pro
add_filter('acadp_is_spam', 'apbct_advanced_classifieds_directory_pro__check_register', 1, 2);

// WP Foro register system integration
add_filter('wpforo_create_profile', 'wpforo_create_profile__check_register', 1, 1);

// HappyForms integration
add_filter('happyforms_validate_submission', 'apbct_form_happyforms_test_spam', 1, 3);

// WPForms
// Adding fields
add_action('wpforms_frontend_output', 'apbct_form__WPForms__addField', 1000, 5);
// Gathering data to validate
add_filter('wpforms_process_before_filter', 'apbct_from__WPForms__gatherData', 100, 2);
// Do spam check
add_filter('wpforms_process_initial_errors', 'apbct_form__WPForms__showResponse', 100, 2);

// Formidable
add_filter('frm_entries_before_create', 'apbct_form__formidable__testSpam', 999999, 2);
add_action('frm_entries_footer_scripts', 'apbct_form__formidable__footerScripts', 20, 2);

// Public actions
if ( ! is_admin() && ! apbct_is_ajax() && ! apbct_is_customize_preview() ) {
    // Default search
    //add_filter( 'get_search_form',  'apbct_forms__search__addField' );
    add_filter('get_search_query', 'apbct_forms__search__testSpam');
    add_action('wp_head', 'apbct_search_add_noindex', 1);

    // SpamFireWall check
    if ( $apbct->plugin_version == APBCT_VERSION && // Do not call with first start
         $apbct->settings['sfw__enabled'] == 1 &&
         $apbct->stats['sfw']['last_update_time'] &&
         apbct_is_get() &&
         ! apbct_wp_doing_cron() &&
         ! \Cleantalk\Variables\Server::inUri('/favicon.ico') &&
         ! apbct_is_cli()
    ) {
        wp_suspend_cache_addition(true);
        apbct_sfw__check();
        wp_suspend_cache_addition(false);
    }
}

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

// Hook for newly added blog
add_action('wpmu_new_blog', 'apbct_activation__new_blog', 10, 6);
function apbct_activation__new_blog($blog_id, $_user_id, $_domain, $_path, $_site_id, $_meta)
{
    Activator::activation(false, $blog_id);
}

// Async loading for JavaScript
add_filter('script_loader_tag', 'apbct_add_async_attribute', 10, 3);

// Redirect admin to plugin settings.
if ( ! defined('WP_ALLOW_MULTISITE') || (defined('WP_ALLOW_MULTISITE') && WP_ALLOW_MULTISITE == false) ) {
    add_action('admin_init', 'apbct_plugin_redirect');
}

// Deleting SFW tables when deleting websites
if ( defined('WP_ALLOW_MULTISITE') && WP_ALLOW_MULTISITE === true ) {
    add_action('delete_blog', 'apbct_sfw__delete_tables', 10, 2);
}

// After plugin loaded - to load locale as described in manual
add_action('plugins_loaded', 'apbct_plugin_loaded');

if ( ! empty($apbct->settings['data__use_ajax']) &&
     ! apbct_is_in_uri('.xml') &&
     ! apbct_is_in_uri('.xsl') ) {
    add_action('wp_ajax_nopriv_ct_get_cookie', 'ct_get_cookie', 1);
    add_action('wp_ajax_ct_get_cookie', 'ct_get_cookie', 1);
}

// Admin panel actions
if ( is_admin() || is_network_admin() ) {
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-find-spam.php');
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-admin.php');
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-settings.php');

    add_action('admin_init', 'apbct_admin__init', 1);

    // Show notices
    add_action('admin_init', array(AdminNotices::class, 'showAdminNotices'));

    if ( ! (defined('DOING_AJAX') && DOING_AJAX) ) {
        add_action('admin_enqueue_scripts', 'apbct_admin__enqueue_scripts');

        add_action('admin_menu', 'apbct_settings_add_page');
        add_action('network_admin_menu', 'apbct_settings_add_page');

        //Show widget only if enables and not IP license
        if ( $apbct->settings['wp__dashboard_widget__show'] && ! $apbct->moderate_ip ) {
            add_action('wp_dashboard_setup', 'ct_dashboard_statistics_widget');
        }
    }

    if ( apbct_is_ajax() || Post::get('cma-action') !== '' ) {
        $_cleantalk_hooked_actions        = array();
        $_cleantalk_ajax_actions_to_check = array();

        require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-validate.php');
        require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public.php');
        require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-integrations.php');
        require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-ajax.php');

        // Feedback for comments
        if ( Post::get('action') === 'ct_feedback_comment' ) {
            add_action('wp_ajax_nopriv_ct_feedback_comment', 'apbct_comment__send_feedback', 1);
            add_action('wp_ajax_ct_feedback_comment', 'apbct_comment__send_feedback', 1);
        }
        if ( Post::get('action') === 'ct_feedback_user' ) {
            add_action('wp_ajax_nopriv_ct_feedback_user', 'apbct_user__send_feedback', 1);
            add_action('wp_ajax_ct_feedback_user', 'apbct_user__send_feedback', 1);
        }

        // Check AJAX requests
        // if User is not logged in
        // if Unknown action or Known action with mandatory check
        if (
            ( ! apbct_is_user_logged_in() || $apbct->settings['data__protect_logged_in'] == 1) &&
            Post::get('action') !== '' &&
            (
                ! in_array(Post::get('action'), $_cleantalk_hooked_actions) ||
                in_array(Post::get('action'), $_cleantalk_ajax_actions_to_check)
            ) &&
            ! in_array(Post::get('action'), array_column($apbct_active_integrations, 'hook'))
        ) {
            add_action('plugins_loaded', 'ct_ajax_hook');
        }

        //QAEngine Theme answers
        if ( intval($apbct->settings['forms__general_contact_forms_test']) ) {
            add_filter('et_pre_insert_question', 'ct_ajax_hook', 1, 1);
        } // Questions
        add_filter('et_pre_insert_answer', 'ct_ajax_hook', 1, 1); // Answers

        // Some of plugins to register a users use AJAX context.
        add_filter('registration_errors', 'ct_registration_errors', 1, 3);
        add_filter('registration_errors', 'ct_check_registration_erros', 999999, 3);
        add_action('user_register', 'apbct_user_register');

        if ( class_exists('BuddyPress') ) {
            require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-validate.php');
            require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public.php');
            require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-integrations.php');
            add_filter(
                'bp_activity_is_spam_before_save',
                'apbct_integration__buddyPres__activityWall',
                999,
                2
            ); /* ActivityWall */
            add_action('bp_locate_template', 'apbct_integration__buddyPres__getTemplateName', 10, 6);
        }
    }

    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-validate.php');
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public.php');
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-integrations.php');
    //Bitrix24 contact form
    if ( $apbct->settings['forms__general_contact_forms_test'] == 1 &&
         ! empty(Post::get('your-phone')) &&
         ! empty(Post::get('your-email')) &&
         ! empty(Post::get('your-message'))
    ) {
        ct_contact_form_validate();
    }

    // Sends feedback to the cloud about comments
    // add_action('wp_set_comment_status', 'ct_comment_send_feedback', 10, 2);

    // Sends feedback to the cloud about deleted users
    if ( $pagenow === 'users.php' ) {
        add_action('delete_user', 'apbct_user__delete__hook', 10, 2);
    }

    if ( $pagenow === 'plugins.php' || apbct_is_in_uri('plugins.php') ) {
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'apbct_admin__plugin_action_links', 10, 2);
        add_filter(
            'network_admin_plugin_action_links_' . plugin_basename(__FILE__),
            'apbct_admin__plugin_action_links',
            10,
            2
        );

        add_filter('plugin_row_meta', 'apbct_admin__register_plugin_links', 10, 3);
    }
// Public pages actions
} else {
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-validate.php');
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public.php');
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-integrations.php');

    add_action('wp_enqueue_scripts', 'ct_enqueue_scripts_public');

    // Init action.
    add_action('plugins_loaded', 'apbct_init', 1);

    // Comments
    add_filter('preprocess_comment', 'ct_preprocess_comment', 1, 1);     // param - comment data array
    add_filter('comment_text', 'ct_comment_text');
    add_filter('wp_die_handler', 'apbct_comment__sanitize_data__before_wp_die', 1); // Check comments after validation

    // Registrations
    if ( ! Post::get('wp-submit') ) {
        add_action('login_form_register', 'apbct_cookie');
        add_action('login_form_register', 'apbct_store__urls');
    }
    add_action('login_enqueue_scripts', 'apbct_login__scripts');
    add_action('register_form', 'ct_register_form');
    add_filter('registration_errors', 'ct_registration_errors', 1, 3);
    add_filter('registration_errors', 'ct_check_registration_erros', 999999, 3);
    add_action('user_register', 'apbct_user_register');

    // WordPress Multisite registrations
    add_action('signup_extra_fields', 'ct_register_form');
    add_filter('wpmu_validate_user_signup', 'ct_registration_errors_wpmu', 10, 3);

    // Login form - for notifications only
    add_filter('login_message', 'ct_login_message');

    // Comments output hook
    add_filter('wp_list_comments_args', 'ct_wp_list_comments_args');

    // Ait-Themes fix
    if ( Get::get('ait-action') === 'register' ) {
        $tmp = Post::get('redirect_to');
        unset($_POST['redirect_to']);
        ct_contact_form_validate();
        $_POST['redirect_to'] = $tmp;
    }
}

// Short code for GDPR
if ( $apbct->settings['gdpr__enabled'] ) {
    add_shortcode('cleantalk_gdpr_form', 'apbct_shrotcode_handler__GDPR_public_notice__form');
}

/**
 * Function for SpamFireWall check
 */
function apbct_sfw__check()
{
    global $apbct, $spbc, $cleantalk_url_exclusions;

    // Turn off the SpamFireWall if current url in the exceptions list and WordPress core pages
    if ( ! empty($cleantalk_url_exclusions) && is_array($cleantalk_url_exclusions) ) {
        $core_page_to_skip_check = array('/feed');
        foreach ( array_merge($cleantalk_url_exclusions, $core_page_to_skip_check) as $v ) {
            if ( apbct_is_in_uri($v) ) {
                return;
            }
        }
    }

    // Skip the check
    if ( ! empty(Get::get('access')) ) {
        $spbc_settings = get_option('spbc_settings');
        $spbc_key      = ! empty($spbc_settings['spbc_key']) ? $spbc_settings['spbc_key'] : false;
        if ( Get::get('access') === $apbct->api_key || ($spbc_key !== false && Get::get('access') === $spbc_key) ) {
            \Cleantalk\Variables\Cookie::set(
                'spbc_firewall_pass_key',
                md5(apbct_get_server_variable('REMOTE_ADDR') . $spbc_key),
                time() + 1200,
                '/',
                ''
            );
            \Cleantalk\Variables\Cookie::set(
                'ct_sfw_pass_key',
                md5(apbct_get_server_variable('REMOTE_ADDR') . $apbct->api_key),
                time() + 1200,
                '/',
                ''
            );

            return;
        }
        unset($spbc_settings, $spbc_key);
    }

    // Turn off the SpamFireWall if Remote Call is in progress
    if ( $apbct->rc_running || ( ! empty($spbc) && $spbc->rc_running) ) {
        return;
    }

    // update mode - skip checking
    if ( isset($apbct->fw_stats['update_mode']) && $apbct->fw_stats['update_mode'] === 1 ) {
        return;
    }

    // Checking if database was outdated
    $is_sfw_outdated = $apbct->stats['sfw']['last_update_time'] + $apbct->stats['sfw']['update_period'] * 3 < time();

    $apbct->errorToggle(
        $is_sfw_outdated,
        'sfw_outdated',
        esc_html__(
            'SpamFireWall database is outdated. Please, try to synchronize with the cloud.',
            'cleantalk-spam-protect'
        )
    );

    if ( $is_sfw_outdated ) {
        return;
    }

    $firewall = new Firewall(
        DB::getInstance()
    );

    $firewall->loadFwModule(
        new SFW(
            APBCT_TBL_FIREWALL_LOG,
            APBCT_TBL_FIREWALL_DATA,
            array(
                'sfw_counter'       => $apbct->settings['admin_bar__sfw_counter'],
                'api_key'           => $apbct->api_key,
                'apbct'             => $apbct,
                'cookie_domain'     => parse_url(get_option('home'), PHP_URL_HOST),
                'data__cookies_type' => $apbct->data['cookies_type'],
            )
        )
    );

    if ( $apbct->settings['sfw__anti_crawler'] && $apbct->stats['sfw']['entries'] > 50 && $apbct->data['cookies_type'] !== 'none' ) {
        $firewall->loadFwModule(
            new \Cleantalk\ApbctWP\Firewall\AntiCrawler(
                APBCT_TBL_FIREWALL_LOG,
                APBCT_TBL_AC_LOG,
                array(
                    'api_key' => $apbct->api_key,
                    'apbct'   => $apbct,
                )
            )
        );
    }

    if ( $apbct->settings['sfw__anti_flood'] && is_null(apbct_wp_get_current_user()) ) {
        $firewall->loadFwModule(
            new AntiFlood(
                APBCT_TBL_FIREWALL_LOG,
                APBCT_TBL_AC_LOG,
                array(
                    'api_key'    => $apbct->api_key,
                    'view_limit' => $apbct->settings['sfw__anti_flood__view_limit'],
                    'apbct'      => $apbct,
                )
            )
        );
    }

    $firewall->run();
}

/**
 * Redirects admin to plugin settings after activation.
 * @psalm-suppress UnusedVariable
 */
function apbct_plugin_redirect()
{
    global $apbct;
    wp_suspend_cache_addition(true);
    if (
        get_option('ct_plugin_do_activation_redirect', false) &&
        delete_option('ct_plugin_do_activation_redirect') &&
        ! Get::get('activate-multi')
    ) {
        ct_account_status_check(null, false);
        apbct_sfw_update__init(3); // Updating SFW
        wp_redirect($apbct->settings_link);
    }
    wp_suspend_cache_addition(false);
}

/**
 * @param $event_type
 *
 * @psalm-suppress  UnusedVariable
 */
function ct_add_event($event_type)
{
    global $apbct, $cleantalk_executed;

    //
    // To migrate on the new version of ct_add_event().
    //
    switch ( $event_type ) {
        case '0':
            $event_type = 'no';
            break;
        case '1':
            $event_type = 'yes';
            break;
    }

    $current_hour = (int)date('G');

    // Updating current hour
    if ( $current_hour != $apbct->data['current_hour'] ) {
        $apbct->data['current_hour']                  = $current_hour;
        $apbct->data['array_accepted'][$current_hour] = 0;
        $apbct->data['array_blocked'][$current_hour]  = 0;
    }

    //Add 1 to counters
    if ( $event_type === 'yes' ) {
        $apbct->data['array_accepted'][$current_hour]++;
        $apbct->data['admin_bar__all_time_counter']['accepted']++;
        $apbct->data['user_counter']['accepted']++;
    }
    if ( $event_type === 'no' ) {
        $apbct->data['array_blocked'][$current_hour]++;
        $apbct->data['admin_bar__all_time_counter']['blocked']++;
        $apbct->data['user_counter']['blocked']++;
    }

    $apbct->saveData();

    $cleantalk_executed = true;
}

/**
 * return new cookie value
 */
function ct_get_cookie()
{
    $ct_checkjs_key = ct_get_checkjs_value();
    print $ct_checkjs_key;
    die();
}

// Clears
function apbct_sfw__clear()
{
    global $apbct, $wpdb;

    $wpdb->query('DELETE FROM ' . APBCT_TBL_FIREWALL_DATA . ';');

    $apbct->stats['sfw']['entries'] = 0;
    $apbct->save('stats');
}

// This action triggered by  wp_schedule_single_event( time() + 720, 'apbct_sfw_update__init' );
add_action('apbct_sfw_update__init', 'apbct_sfw_update__init');

/**
 * Called by sfw_update remote call
 * Starts SFW update and could use a delay before start
 *
 * @param int $delay
 *
 * @return bool|string|string[]
 */
function apbct_sfw_update__init($delay = 0)
{
    global $apbct;

    // Prevent start an update if update is already running and started less than 10 minutes ago
    if (
        $apbct->fw_stats['firewall_updating_id'] &&
        time() - $apbct->fw_stats['firewall_updating_last_start'] < 600 &&
        apbct_sfw_update__is_in_progress()
    ) {
        return false;
    }

    if ( ! $apbct->settings['sfw__enabled'] ) {
        return false;
    }

    // The Access key is empty
    if ( ! $apbct->api_key && ! $apbct->ip_license ) {
        return array('error' => 'SFW UPDATE INIT: KEY_IS_EMPTY');
    }

    if ( ! $apbct->data['key_is_ok'] ) {
        return array('error' => 'SFW UPDATE INIT: KEY_IS_NOT_VALID');
    }

    // Get update period for server
    $update_period = DNS::getRecord('spamfirewall-ttl-txt.cleantalk.org', true, DNS_TXT);
    $update_period = isset($update_period['txt']) ? $update_period['txt'] : 0;
    $update_period = (int)$update_period > 14400 ? (int)$update_period : 14400;
    if ( $apbct->stats['sfw']['update_period'] != $update_period ) {
        $apbct->stats['sfw']['update_period'] = $update_period;
        $apbct->save('stats');
    }

    $wp_upload_dir = wp_upload_dir();
    $apbct->fw_stats['updating_folder'] = $wp_upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'cleantalk_fw_files_for_blog_' . get_current_blog_id() . DIRECTORY_SEPARATOR;

    $prepare_dir__result = apbct_prepare_upd_dir();
    $test_rc_result = Helper::httpRequestRcToHostTest(
        'sfw_update__worker',
        array(
            'spbc_remote_call_token' => md5($apbct->api_key),
            'spbc_remote_call_action' => 'sfw_update__worker',
            'plugin_name' => 'apbct'
        )
    );
    if ( ! empty($prepare_dir__result['error']) || ! empty($test_rc_result['error']) ) {
        return apbct_sfw_direct_update();
    }

    // Set a new update ID and an update time start
    $apbct->fw_stats['calls']                        = 0;
    $apbct->fw_stats['firewall_updating_id']         = md5((string)rand(0, 100000));
    $apbct->fw_stats['firewall_updating_last_start'] = time();
    $apbct->save('fw_stats');

    // Delete update errors
    $apbct->errorDelete('sfw_update', 'save_data');
    $apbct->errorDelete('sfw_update', 'save_data', 'cron');

    \Cleantalk\ApbctWP\Queue::clearQueue();

    $queue = new \Cleantalk\ApbctWP\Queue();
    $queue->addStage('apbct_sfw_update__get_multifiles');

    $cron = new Cron();
    $cron->addTask('sfw_update_checker', 'apbct_sfw_update__checker', 15);

    return Helper::httpRequestRcToHost(
        'sfw_update__worker',
        array(
            'firewall_updating_id' => $apbct->fw_stats['firewall_updating_id'],
            'delay'                => $delay
        ),
        array('async')
    );
}

/**
 * Called by sfw_update__worker remote call
 * gather all process about SFW updating
 *
 * @param null|string $updating_id
 * @param null|string $multifile_url
 * @param null|string|int $url_count
 * @param null|string|int $current_url
 * @param string $useragent_url
 *
 * @return array|bool|int|string|string[]
 */
function apbct_sfw_update__worker($checker_work = false)
{
    global $apbct;

    usleep(10000);

    if ( ! $apbct->data['key_is_ok'] ) {
        return array('error' => 'Worker: KEY_IS_NOT_VALID');
    }

    if ( ! $checker_work ) {
        if (
            Request::equal('firewall_updating_id', '') ||
            ! Request::equal('firewall_updating_id', $apbct->fw_stats['firewall_updating_id'])
        ) {
            return array('error' => 'Worker: WRONG_UPDATE_ID');
        }
    }

    if ( ! isset($apbct->fw_stats['calls']) ) {
        $apbct->fw_stats['calls'] = 0;
    }

    $apbct->fw_stats['calls']++;
    $apbct->save('fw_stats');

    if ( $apbct->fw_stats['calls'] > 600 ) {
        $apbct->errorAdd('sfw_update', 'WORKER_CALL_LIMIT_EXCEEDED');
        $apbct->saveErrors();

        return 'WORKER_CALL_LIMIT_EXCEEDED';
    }

    $queue = new \Cleantalk\ApbctWP\Queue();

    if ( count($queue->queue['stages']) === 0 ) {
        // Queue is already empty. Exit.
        return true;
    }

    $result = $queue->executeStage();

    if ( isset($result['error']) && $result['status'] === 'FINISHED' ) {
        $apbct->errorAdd('sfw_update', $result['error']);
        $apbct->saveErrors();

        apbct_sfw_update__fallback();

        return $result['error'];
    }

    if ( $queue->isQueueFinished() ) {
        $queue->queue['finished'] = time();
        $queue->saveQueue($queue->queue);
        foreach ( $queue->queue['stages'] as $stage ) {
            if ( isset($stage['error']) ) {
                //there could be an array of errors of files processed
                if (is_array($stage['error'])){
                    $error = implode(" ",array_values($stage['error']));
                } else {
                    $error = $result['error'];
                }
                $apbct->errorAdd('sfw_update', $error);
            }
        }

        // Do logging the queue process here
        return true;
    }

    // This is the repeat stage request, do not generate any new RC
    if ( stripos(Request::get('stage'), 'Repeat') !== false ) {
        return true;
    }

    return Helper::httpRequestRcToHost(
        'sfw_update__worker',
        array('firewall_updating_id' => $apbct->fw_stats['firewall_updating_id']),
        array('async')
    );
}

function apbct_sfw_update__get_multifiles()
{
    global $apbct;

    if ( ! $apbct->data['key_is_ok'] ) {
        return array('error' => 'Get multifiles: KEY_IS_NOT_VALID');
    }

    // Getting remote file name
    $result = API::methodGet2sBlacklistsDb($apbct->api_key, 'multifiles', '3_1');

    if ( empty($result['error']) ) {
        if ( ! empty($result['file_url']) ) {
            $file_urls = Helper::httpGetDataFromRemoteGzAndParseCsv($result['file_url']);
            if ( empty($file_urls['error']) ) {
                if ( ! empty($result['file_ua_url']) ) {
                    $file_urls[][0] = $result['file_ua_url'];
                }
                if ( ! empty($result['file_ck_url']) ) {
                    $file_urls[][0] = $result['file_ck_url'];
                }
                $urls = array();
                foreach ( $file_urls as $value ) {
                    $urls[] = $value[0];
                }

                $apbct->fw_stats['firewall_update_percent'] = round(100 / count($urls), 2);
                $apbct->save('fw_stats');

                return array(
                    'next_stage' => array(
                        'name'    => 'apbct_sfw_update__download_files',
                        'args'    => $urls,
                        'is_last' => '0'
                    )
                );
            }

            return array('error' => $file_urls['error']);
        }
    } else {
        return $result;
    }
}

function apbct_sfw_update__download_files($urls)
{
    global $apbct;

    sleep(3);

    //Reset keys
    $urls          = array_values($urls);
    $results       = Helper::httpMultiRequest($urls, $apbct->fw_stats['updating_folder']);
    $count_urls    = count($urls);
    $count_results = count($results);

    if ( empty($results['error']) && ($count_urls === $count_results) ) {
        $download_again = array();
        $results        = array_values($results);
        for ( $i = 0; $i < $count_results; $i++ ) {
            if ( $results[$i] === 'error' ) {
                $download_again[] = $urls[$i];
            }
        }

        if ( count($download_again) !== 0 ) {
            return array(
                'error'       => 'Files download not completed.',
                'update_args' => array(
                    'args' => $download_again
                )
            );
        }

        return array(
            'next_stage' => array(
                'name' => 'apbct_sfw_update__create_tables'
            )
        );
    }

    if ( ! empty($results['error']) ) {
        return $results;
    }

    return array('error' => 'Files download not completed.');
}

function apbct_sfw_update__create_tables()
{
    global $apbct;
    // Preparing database infrastructure
    // Creating SFW tables to make sure that they are exists
    $db_tables_creator = new DbTablesCreator();
    $table_name = $apbct->db_prefix . Schema::getSchemaTablePrefix() . 'sfw';
    $db_tables_creator->createTable($table_name);

    return array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__create_temp_tables',
        )
    );
}

function apbct_sfw_update__create_temp_tables()
{
    // Preparing temporary tables
    $result = SFW::createTempTables(DB::getInstance(), APBCT_TBL_FIREWALL_DATA);
    if ( ! empty($result['error']) ) {
        return $result;
    }

    return array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__process_files',
        )
    );
}

function apbct_sfw_update__process_files()
{
    global $apbct;

    $files = glob($apbct->fw_stats['updating_folder'] . '/*csv.gz');
    $files = array_filter($files, static function ($element) {
        return strpos($element, 'list') !== false;
    });

    if ( count($files) ) {
        reset($files);
        $concrete_file = current($files);

        if ( strpos($concrete_file, 'bl_list') !== false ) {
            $result = apbct_sfw_update__process_file($concrete_file);
        }

        if ( strpos($concrete_file, 'ua_list') !== false ) {
            $result = apbct_sfw_update__process_ua($concrete_file);
        }

        if ( strpos($concrete_file, 'ck_list') !== false ) {
            $result = apbct_sfw_update__process_ck($concrete_file);
        }

        if ( ! empty($result['error']) ) {
            return $result;
        }

        $apbct->fw_stats['firewall_update_percent'] = round(100 / count($files), 2);
        $apbct->save('fw_stats');

        return array(
            'next_stage' => array(
                'name' => 'apbct_sfw_update__process_files',
            )
        );
    }

    return array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__process_exclusions',
        )
    );
}

function apbct_sfw_update__process_file($file_path)
{
    if ( ! file_exists($file_path) ) {
        return array('error' => 'PROCESS FILE: ' . $file_path . ' is not exists.');
    }

    $result = SFW::updateWriteToDb(
        DB::getInstance(),
        APBCT_TBL_FIREWALL_DATA . '_temp',
        $file_path
    );

    if ( ! empty($result['error']) ) {
        return array('error' => 'PROCESS FILE: ' . $result['error']);
    }

    if ( ! is_int($result) ) {
        return array('error' => 'PROCESS FILE: WRONG RESPONSE FROM update__write_to_db');
    }

    return $result;
}

function apbct_sfw_update__process_ua($file_path)
{
    $result = AntiCrawler::update($file_path);

    if ( ! empty($result['error']) ) {
        return array('error' => 'UPDATING UA LIST: ' . $result['error']);
    }

    if ( ! is_int($result) ) {
        return array('error' => 'UPDATING UA LIST: : WRONG_RESPONSE AntiCrawler::update');
    }

    return $result;
}

function apbct_sfw_update__process_ck($file_path)
{
    global $apbct;

    // Save expected_networks_count and expected_ua_count if exists
    $file_content = file_get_contents($file_path);

    if ( function_exists('gzdecode') ) {
        $unzipped_content = gzdecode($file_content);

        if ( $unzipped_content !== false ) {
            $file_ck_url__data = Helper::bufferParseCsv($unzipped_content);

            if ( ! empty($file_ck_url__data['error']) ) {
                return array('error' => 'GET EXPECTED RECORDS COUNT DATA: ' . $file_ck_url__data['error']);
            }

            $expected_networks_count = 0;
            $expected_ua_count       = 0;

            foreach ( $file_ck_url__data as $value ) {
                if ( trim($value[0], '"') === 'networks_count' ) {
                    $expected_networks_count = $value[1];
                }
                if ( trim($value[0], '"') === 'ua_count' ) {
                    $expected_ua_count = $value[1];
                }
            }

            $apbct->fw_stats['expected_networks_count'] = $expected_networks_count;
            $apbct->fw_stats['expected_ua_count']       = $expected_ua_count;
            $apbct->save('fw_stats');

            if ( file_exists($file_path) ) {
                unlink($file_path);
            }
        } else {
            return array('error' => 'Can not unpack datafile');
        }
    } else {
        return array('error' => 'Function gzdecode not exists. Please update your PHP at least to version 5.4 ');
    }
}

function apbct_sfw_update__process_exclusions()
{
    global $apbct;

    $result = SFW::updateWriteToDbExclusions(
        DB::getInstance(),
        APBCT_TBL_FIREWALL_DATA . '_temp'
    );

    if ( ! empty($result['error']) ) {
        return array('error' => 'EXCLUSIONS: ' . $result['error']);
    }

    if ( ! is_int($result) ) {
        return array('error' => 'EXCLUSIONS: WRONG_RESPONSE update__write_to_db__exclusions');
    }

    /**
     * Update expected_networks_count
     */
    if ( $result > 0 ) {
        $apbct->fw_stats['expected_networks_count'] += $result;
        $apbct->save('fw_stats');
    }

    return array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__end_of_update__renaming_tables',
            'accepted_tries' => 1
        )
    );
}

function apbct_sfw_update__end_of_update__renaming_tables()
{
    global $apbct;

    if ( ! DB::getInstance()->isTableExists(APBCT_TBL_FIREWALL_DATA) ) {
        return array('error' => 'Error while completing data: SFW main table does not exist.');
    }

    if ( ! DB::getInstance()->isTableExists(APBCT_TBL_FIREWALL_DATA . '_temp') ) {
        return array('error' => 'Error while completing data: SFW temp table does not exist.');
    }

    $apbct->fw_stats['update_mode'] = 1;
    $apbct->save('fw_stats');
    usleep(10000);

    // REMOVE AND RENAME
    $result = SFW::dataTablesDelete(DB::getInstance(), APBCT_TBL_FIREWALL_DATA);
    if ( empty($result['error']) ) {
        $result = SFW::renameDataTablesFromTempToMain(DB::getInstance(), APBCT_TBL_FIREWALL_DATA);
    }

    $apbct->fw_stats['update_mode'] = 0;
    $apbct->save('fw_stats');

    if ( ! empty($result['error']) ) {
        return $result;
    }

    return array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__end_of_update__checking_data',
            'accepted_tries' => 1
        )
    );
}

function apbct_sfw_update__end_of_update__checking_data()
{
    global $apbct, $wpdb;

    if ( ! DB::getInstance()->isTableExists(APBCT_TBL_FIREWALL_DATA) ) {
        return array('error' => 'Error while checking data: SFW main table does not exist.');
    }

    $apbct->stats['sfw']['entries'] = $wpdb->get_var('SELECT COUNT(*) FROM ' . APBCT_TBL_FIREWALL_DATA);
    $apbct->save('stats');

    /**
     * Checking the integrity of the sfw database update
     */
    if ( $apbct->stats['sfw']['entries'] != $apbct->fw_stats['expected_networks_count'] ) {
        return array(
            'error' =>
                'The discrepancy between the amount of data received for the update and in the final table: '
                . APBCT_TBL_FIREWALL_DATA
                . '. RECEIVED: ' . $apbct->fw_stats['expected_networks_count']
                . '. ADDED: ' . $apbct->stats['sfw']['entries']
        );
    }

    return array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__end_of_update__updating_stats',
            'accepted_tries' => 1
        )
    );
}

function apbct_sfw_update__end_of_update__updating_stats($is_direct_update = false)
{
    global $apbct;

    $is_first_updating = ! $apbct->stats['sfw']['last_update_time'];
    $apbct->stats['sfw']['last_update_time'] = time();
    $apbct->stats['sfw']['last_update_way']  = $is_direct_update ? 'Direct update' : 'Queue update';
    $apbct->save('stats');

    return array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__end_of_update',
            'accepted_tries' => 1,
            'args' => $is_first_updating
        )
    );
}

function apbct_sfw_update__end_of_update($is_first_updating = false)
{
    global $apbct;

    // Delete update errors
    $apbct->errorDelete('sfw_update', 'save_settings');

    // Running sfw update once again in 12 min if entries is < 4000
    if ( $is_first_updating &&
         $apbct->stats['sfw']['entries'] < 4000
    ) {
        wp_schedule_single_event(time() + 720, 'apbct_sfw_update__init');
    }

    $cron = new Cron();
    $cron->updateTask('sfw_update', 'apbct_sfw_update__init', $apbct->stats['sfw']['update_period']);
    $cron->removeTask('sfw_update_checker');

    apbct_remove_upd_folder($apbct->fw_stats['updating_folder']);

    // Reset all FW stats
    $apbct->fw_stats['firewall_update_percent'] = 0;
    $apbct->fw_stats['firewall_updating_id']    = null;
    $apbct->fw_stats['expected_networks_count'] = false;
    $apbct->fw_stats['expected_ua_count'] = false;
    $apbct->save('fw_stats');

    return true;
}


function apbct_sfw_update__is_in_progress()
{
    $queue = new \Cleantalk\ApbctWP\Queue();

    return $queue->isQueueInProgress();
}

function apbct_prepare_upd_dir()
{
    global $apbct;

    $dir_name = $apbct->fw_stats['updating_folder'];

    if ( $dir_name === '' ) {
        return array('error' => 'FW dir can not be blank.');
    }

    if ( ! is_dir($dir_name) ) {
        if ( ! mkdir($dir_name) && ! is_dir($dir_name) ) {
            return array('error' => 'Can not to make FW dir.');
        }
    } else {
        $files = glob($dir_name . '/*');
        if ( $files === false ) {
            return array('error' => 'Can not find FW files.');
        }
        if ( count($files) === 0 ) {
            return (bool)file_put_contents($dir_name . 'index.php', '<?php' . PHP_EOL);
        }
        foreach ( $files as $file ) {
            if ( is_file($file) && unlink($file) === false ) {
                return array('error' => 'Can not delete the FW file: ' . $file);
            }
        }
    }

    return (bool)file_put_contents($dir_name . 'index.php', '<?php');
}

function apbct_remove_upd_folder($dir_name)
{
    if ( is_dir($dir_name) ) {
        $files = glob($dir_name . '/*');

        if ( ! empty($files) ) {
            foreach ( $files as $file ) {
                if ( is_file($file) ) {
                    unlink($file);
                }
                if ( is_dir($file) ) {
                    apbct_remove_upd_folder($file);
                }
            }
        }

        rmdir($dir_name);
    }
}

function apbct_sfw_update__checker()
{
    $queue = new \Cleantalk\ApbctWP\Queue();
    if ( count($queue->queue['stages']) ) {
        foreach ( $queue->queue['stages'] as $stage ) {
            if ( $stage['status'] === 'NULL' ) {
                return apbct_sfw_update__worker(true);
            }
        }
    }

    return true;
}

function apbct_sfw_direct_update()
{
    global $apbct;

    $api_key = $apbct->api_key;

    // The Access key is empty
    if ( empty($api_key) ) {
        return array('error' => 'SFW DIRECT UPDATE: KEY_IS_EMPTY');
    }

    // Getting BL
    $result = SFW::directUpdateGetBlackLists($api_key);

    if ( empty($result['error']) ) {
        $blacklists = $result['blacklist'];
        $useragents = $result['useragents'];
        $bl_count   = $result['bl_count'];
        $ua_count   = $result['ua_count'];

        if ( isset($bl_count, $ua_count) ) {
            $apbct->fw_stats['expected_networks_count'] = $bl_count;
            $apbct->fw_stats['expected_ua_count']       = $ua_count;
            $apbct->save('fw_stats');
        }

        // Preparing database infrastructure
        // @ToDo need to implement returning result of the Activator::createTables work.
        $db_tables_creator = new DbTablesCreator();
        $table_name = $apbct->db_prefix . Schema::getSchemaTablePrefix() . 'sfw';
        $db_tables_creator->createTable($table_name);

        $result__creating_tmp_table = SFW::createTempTables(DB::getInstance(), APBCT_TBL_FIREWALL_DATA);
        if ( ! empty($result__creating_tmp_table['error']) ) {
            return array('error' => 'DIRECT UPDATING CREATE TMP TABLE: ' . $result__creating_tmp_table['error']);
        }

        /**
         * UPDATING UA LIST
         */
        if ( $useragents && ($apbct->settings['sfw__anti_crawler'] || $apbct->settings['sfw__anti_flood']) ) {
            $ua_result = AntiCrawler::directUpdate($useragents);

            if ( ! empty($ua_result['error']) ) {
                return array('error' => 'DIRECT UPDATING UA LIST: ' . $result['error']);
            }

            if ( ! is_int($ua_result) ) {
                return array('error' => 'DIRECT UPDATING UA LIST: : WRONG_RESPONSE AntiCrawler::directUpdate');
            }
        }

        /**
         * UPDATING BLACK LIST
         */
        $upd_result = SFW::directUpdate(
            DB::getInstance(),
            APBCT_TBL_FIREWALL_DATA . '_temp',
            $blacklists
        );

        if ( ! empty($upd_result['error']) ) {
            return array('error' => 'DIRECT UPDATING BLACK LIST: ' . $upd_result['error']);
        }

        if ( ! is_int($upd_result) ) {
            return array('error' => 'DIRECT UPDATING BLACK LIST: WRONG RESPONSE FROM SFW::directUpdate');
        }

        /**
         * UPDATING EXCLUSIONS LIST
         */
        $excl_result = apbct_sfw_update__process_exclusions();

        if ( ! empty($excl_result['error']) ) {
            return array('error' => 'DIRECT UPDATING EXCLUSIONS: ' . $excl_result['error']);
        }

        /**
         * DELETING AND RENAMING THE TABLES
         */
        $rename_tables_res = apbct_sfw_update__end_of_update__renaming_tables();
        if ( ! empty($rename_tables_res['error']) ) {
            return array('error' => 'DIRECT UPDATING BLACK LIST: ' . $rename_tables_res['error']);
        }

        /**
         * CHECKING THE UPDATE
         */
        $check_data_res = apbct_sfw_update__end_of_update__checking_data();
        if ( ! empty($check_data_res['error']) ) {
            return array('error' => 'DIRECT UPDATING BLACK LIST: ' . $check_data_res['error']);
        }

        /**
         * WRITE UPDATING STATS
         */
        $update_stats_res = apbct_sfw_update__end_of_update__updating_stats(true);
        if ( ! empty($update_stats_res['error']) ) {
            return array('error' => 'DIRECT UPDATING BLACK LIST: ' . $update_stats_res['error']);
        }

        /**
         * END OF UPDATE
         */
        return apbct_sfw_update__end_of_update();
    }

    return $result;
}

function apbct_sfw_update__cleanData()
{
    global $apbct;

    SFW::dataTablesDelete(DB::getInstance(), APBCT_TBL_FIREWALL_DATA . '_temp');

    $apbct->fw_stats['firewall_update_percent'] = 0;
    $apbct->fw_stats['firewall_updating_id']    = null;
    $apbct->save('fw_stats');
}

function apbct_sfw_update__fallback()
{
    global $apbct;

    /**
     * Remove the upd folder
     */
    if ( $apbct->fw_stats['updating_folder'] ) {
        apbct_remove_upd_folder($apbct->fw_stats['updating_folder']);
    }

    /**
     * Remove SFW updating checker cron-task
     */
    $cron = new Cron();
    $cron->removeTask('sfw_update_checker');
    $cron->updateTask('sfw_update', 'apbct_sfw_update__init', $apbct->stats['sfw']['update_period']);

    /**
     * Remove _temp table
     */
    apbct_sfw_update__cleanData();

    /**
     * Create SFW table if not exists
     */
    apbct_sfw_update__create_tables();
}

function ct_sfw_send_logs($api_key = '')
{
    global $apbct;

    $api_key = ! empty($apbct->api_key) ? $apbct->api_key : $api_key;

    if (
        time() - $apbct->stats['sfw']['sending_logs__timestamp'] < 180 ||
        empty($api_key) ||
        $apbct->settings['sfw__enabled'] != 1
    ) {
        return true;
    }

    $apbct->stats['sfw']['sending_logs__timestamp'] = time();
    $apbct->save('stats');

    $result = SFW::sendLog(
        DB::getInstance(),
        APBCT_TBL_FIREWALL_LOG,
        $api_key,
        (bool)$apbct->settings['sfw__use_delete_to_clear_table']
    );

    if ( empty($result['error']) ) {
        $apbct->stats['sfw']['last_send_time']   = time();
        $apbct->stats['sfw']['last_send_amount'] = $result['rows'];
        $apbct->errorDelete('sfw_send_logs', 'save_settings');
        $apbct->save('stats');
    }

    return $result;
}

function apbct_antiflood__clear_table()
{
    global $apbct;

    if ( $apbct->settings['sfw__anti_flood'] || $apbct->settings['sfw__anti_crawler'] ) {
        $anti_flood = new AntiFlood(
            APBCT_TBL_FIREWALL_LOG,
            APBCT_TBL_AC_LOG,
            array(
                'chance_to_clean' => 100,
            )
        );
        $anti_flood->setDb(DB::getInstance());
        $anti_flood->clearTable();
        unset($anti_flood);
    }
}

/**
 * Install plugin from WordPress catalog
 *
 * @param null|WP $_wp
 * @param null|string|array $plugin
 *
 * @psalm-suppress UndefinedClass
 */
function apbct_rc__install_plugin($_wp = null, $plugin = null)
{
    global $wp_version;

    if ( is_null($plugin) ) {
        $plugin = Get::get('plugin') ? (string) Get::get('plugin') : null;
    }

    if ( $plugin ) {
        if ( preg_match('/[a-zA-Z-\d]+[\/\\][a-zA-Z-\d]+\.php/', $plugin) ) {
            $plugin_slug = preg_replace('@([a-zA-Z-\d]+)[\\\/].*@', '$1', $plugin);

            if ( $plugin_slug ) {
                require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
                $result = plugins_api(
                    'plugin_information',
                    array(
                        'slug'   => $plugin_slug,
                        'fields' => array('version' => true, 'download_link' => true,),
                    )
                );

                if ( ! is_wp_error($result) ) {
                    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
                    include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
                    include_once(ABSPATH . 'wp-admin/includes/file.php');
                    include_once(ABSPATH . 'wp-admin/includes/misc.php');

                    if ( version_compare(PHP_VERSION, '5.6.0') >= 0 && version_compare($wp_version, '5.3') >= 0 ) {
                        $installer = new CleantalkUpgrader(new CleantalkUpgraderSkin());
                    } else {
                        $installer = new CleantalkUpgrader(new CleantalkUpgraderSkinDeprecated());
                    }

                    $installer->install($result->download_link);

                    if ( $installer->apbct_result === 'OK' ) {
                        die('OK');
                    } else {
                        die('FAIL ' . json_encode(array('error' => $installer->apbct_result)));
                    }
                } else {
                    die(
                        'FAIL ' . json_encode(array(
                            'error'   => 'FAIL_TO_GET_LATEST_VERSION',
                            'details' => $result->get_error_message(),
                        ))
                    );
                }
            } else {
                die('FAIL ' . json_encode(array('error' => 'PLUGIN_SLUG_INCORRECT')));
            }
        } else {
            die('FAIL ' . json_encode(array('error' => 'PLUGIN_NAME_IS_INCORRECT')));
        }
    } else {
        die('FAIL ' . json_encode(array('error' => 'PLUGIN_NAME_IS_UNSET')));
    }
}

function apbct_rc__activate_plugin($plugin)
{
    if ( ! $plugin ) {
        $plugin = Get::get('plugin') ? (string) Get::get('plugin') : null;
    }

    if ( $plugin ) {
        if ( preg_match('@[a-zA-Z-\d]+[\\\/][a-zA-Z-\d]+\.php@', $plugin) ) {
            require_once(ABSPATH . '/wp-admin/includes/plugin.php');

            $result = activate_plugins($plugin);

            if ( $result && ! is_wp_error($result) ) {
                return array('success' => true);
            } else {
                return array(
                    'error'   => 'FAIL_TO_ACTIVATE',
                    'details' => (is_wp_error($result) ? ' ' . $result->get_error_message() : '')
                );
            }
        } else {
            return array('error' => 'PLUGIN_NAME_IS_INCORRECT');
        }
    } else {
        return array('error' => 'PLUGIN_NAME_IS_UNSET');
    }
}

/**
 * Uninstall plugin from WordPress catalog
 *
 * @param null $plugin
 */
function apbct_rc__deactivate_plugin($plugin = null)
{
    global $apbct;

    if ( is_null($plugin) ) {
        $plugin = Get::get('plugin') ? (string) Get::get('plugin') : null;
    }

    if ( $plugin ) {
        // Switching complete deactivation for security
        if ( $plugin === 'security-malware-firewall/security-malware-firewall.php' && ! empty(Get::get('misc__complete_deactivation')) ) {
            $spbc_settings                                = get_option('spbc_settings');
            $spbc_settings['misc__complete_deactivation'] = (int) Get::get('misc__complete_deactivation');
            update_option('spbc_settings', $spbc_settings);
        }

        require_once(ABSPATH . '/wp-admin/includes/plugin.php');

        if ( is_plugin_active($plugin) ) {
            // Hook to set flag if the plugin is deactivated
            add_action('deactivate_' . $plugin, 'apbct_rc__uninstall_plugin__check_deactivate');
            deactivate_plugins($plugin, false, is_multisite());
        } else {
            $apbct->plugin_deactivated = true;
        }

        // Hook to set flag if the plugin is deactivated
        add_action('deactivate_' . $plugin, 'apbct_rc__uninstall_plugin__check_deactivate');
        deactivate_plugins($plugin, false, is_multisite());

        if ( $apbct->plugin_deactivated ) {
            die('OK');
        } else {
            die('FAIL ' . json_encode(array('error' => 'PLUGIN_STILL_ACTIVE')));
        }
    } else {
        die('FAIL ' . json_encode(array('error' => 'PLUGIN_NAME_IS_UNSET')));
    }
}


/**
 * Uninstall plugin from WordPress. Delete files.
 *
 * @param null $plugin
 */
function apbct_rc__uninstall_plugin($plugin = null)
{
    global $apbct;

    if ( is_null($plugin) ) {
        $plugin = Get::get('plugin') ? (string) Get::get('plugin') : null;
    }

    if ( $plugin ) {
        // Switching complete deactivation for security
        if ( $plugin === 'security-malware-firewall/security-malware-firewall.php' && ! empty(Get::get('misc__complete_deactivation')) ) {
            $spbc_settings                                = get_option('spbc_settings');
            $spbc_settings['misc__complete_deactivation'] = (int) Get::get('misc__complete_deactivation');
            update_option('spbc_settings', $spbc_settings);
        }

        require_once(ABSPATH . '/wp-admin/includes/plugin.php');

        if ( is_plugin_active($plugin) ) {
            // Hook to set flag if the plugin is deactivated
            add_action('deactivate_' . $plugin, 'apbct_rc__uninstall_plugin__check_deactivate');
            deactivate_plugins($plugin, false, is_multisite());
        } else {
            $apbct->plugin_deactivated = true;
        }

        if ( $apbct->plugin_deactivated ) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');

            $result = delete_plugins(array($plugin));

            if ( $result && ! is_wp_error($result) ) {
                die('OK');
            } else {
                die(
                    'FAIL ' . json_encode(array(
                        'error'   => 'PLUGIN_STILL_EXISTS',
                        'details' => (is_wp_error($result) ? ' ' . $result->get_error_message() : '')
                    ))
                );
            }
        } else {
            die('FAIL ' . json_encode(array('error' => 'PLUGIN_STILL_ACTIVE')));
        }
    } else {
        die('FAIL ' . json_encode(array('error' => 'PLUGIN_NAME_IS_UNSET')));
    }
}

function apbct_rc__uninstall_plugin__check_deactivate()
{
    global $apbct;
    $apbct->plugin_deactivated = true;
}

/**
 * @param $source
 *
 * @return bool
 */
function apbct_rc__update_settings($source)
{
    global $apbct;

    foreach ( $apbct->def_settings as $setting => $def_value ) {
        if ( array_key_exists($setting, $source) ) {
            $var  = $source[$setting];
            $type = gettype($def_value);
            settype($var, $type);
            if ( $type === 'string' ) {
                $var = preg_replace(array('/=/', '/`/'), '', $var);
            }
            $apbct->settings[$setting] = $var;
        }
    }

    $apbct->save('settings');

    return true;
}

/**
 * @param string $key
 * @param string $plugin
 *
 * @return array|string
 */
function apbct_rc__insert_auth_key($key, $plugin)
{
    if ( $plugin === 'security-malware-firewall/security-malware-firewall.php' ) {
        require_once(ABSPATH . '/wp-admin/includes/plugin.php');

        if ( is_plugin_active($plugin) ) {
            $key = trim($key);

            if ( $key && preg_match('/^[a-z\d]{3,15}$/', $key) ) {
                $result = API::methodNoticePaidTill(
                    $key,
                    preg_replace('/http[s]?:\/\//', '', get_option('home'), 1), // Site URL
                    'security'
                );

                if ( empty($result['error']) ) {
                    if ( $result['valid'] ) {
                        // Set account params
                        $data                     = get_option('spbc_data', array());
                        $data['user_token']       = $result['user_token'];
                        $data['notice_show']      = $result['show_notice'];
                        $data['notice_renew']     = $result['renew'];
                        $data['notice_trial']     = $result['trial'];
                        $data['auto_update_app']  = isset($result['show_auto_update_notice']) ? $result['show_auto_update_notice'] : 0;
                        $data['service_id']       = $result['service_id'];
                        $data['moderate']         = $result['moderate'];
                        $data['auto_update_app '] = isset($result['auto_update_app']) ? $result['auto_update_app'] : 0;
                        $data['license_trial']    = isset($result['license_trial']) ? $result['license_trial'] : 0;
                        $data['account_name_ob']  = isset($result['account_name_ob']) ? $result['account_name_ob'] : '';
                        $data['key_is_ok']        = true;
                        update_option('spbc_data', $data);

                        // Set Access key
                        $settings             = get_option('spbc_settings', array());
                        $settings['spbc_key'] = $key;
                        update_option('spbc_settings', $settings);

                        return 'OK';
                    } else {
                        return array('error' => 'KEY_IS_NOT_VALID');
                    }
                } else {
                    return array('error' => $result);
                }
            } else {
                return array('error' => 'KEY_IS_NOT_CORRECT');
            }
        } else {
            return array('error' => 'PLUGIN_IS_NOT_ACTIVE_OR_NOT_INSTALLED');
        }
    } else {
        return array('error' => 'PLUGIN_SLUG_INCORRECT');
    }
}

/**
 * Putting WordPress to maintenance mode.
 * For given duration in seconds
 *
 * @param $duration
 *
 * @return bool
 */
function apbct_maintenance_mode__enable($duration)
{
    apbct_maintenance_mode__disable();
    $content = "<?php\n\n"
               . '$upgrading = ' . (time() - (60 * 10) + $duration) . ';';

    return (bool)file_put_contents(ABSPATH . '.maintenance', $content);
}

/**
 * Disabling maintenance mode by deleting .maintenance file.
 *
 * @return void
 */
function apbct_maintenance_mode__disable()
{
    $maintenance_file = ABSPATH . '.maintenance';
    if ( file_exists($maintenance_file) ) {
        unlink($maintenance_file);
    }
}

/**
 * @param $stage
 * @param $result
 * @param array $response
 *
 * @return void
 */
function apbct_update__outputResult($stage, $result, $response = array())
{
    $response['stage'] = $stage;
    $response['error'] = isset($response['error']) ? $response['error'] : '';

    if ( $result === true ) {
        $result = 'OK';
    }
    if ( $result === false ) {
        $result = 'FAIL';
    }

    $response['error'] = $response['error'] ?: '';
    $response['error'] = $result !== 'OK' && empty($response['error']) ? $result : $response['error'];
    $response['agent'] = APBCT_AGENT;

    echo $result . ' ' . json_encode($response);

    if ( $result === 'FAIL' ) {
        die();
    }

    echo '<br>';
}

/**
 * Getting brief data
 *
 * @param null|string $api_key
 */
function cleantalk_get_brief_data($api_key = null)
{
    global $apbct;

    $api_key = is_null($api_key) ? $apbct->api_key : $api_key;

    $apbct->data['brief_data'] = API::methodGetAntispamReportBreif($api_key);

    # expanding data about the country
    if ( isset($apbct->data['brief_data']['top5_spam_ip']) && ! empty($apbct->data['brief_data']['top5_spam_ip']) ) {
        foreach ( $apbct->data['brief_data']['top5_spam_ip'] as $key => $ip_data ) {
            $ip         = $ip_data[0];
            $ip_data[1] = array(
                'country_name' => 'Unknown',
                'country_code' => 'cleantalk'
            );

            if ( isset($ip) ) {
                $country_data       = API::methodIpInfo($ip);
                $country_data_clear = current($country_data);

                if (
                    is_array($country_data_clear) &&
                    isset($country_data_clear['country_name']) &&
                    isset($country_data_clear['country_code'])
                ) {
                    $ip_data[1] = array(
                        'country_name' => $country_data_clear['country_name'],
                        'country_code' => ( ! preg_match(
                            '/[^A-Za-z0-9]/',
                            $country_data_clear['country_code']
                        )) ? $country_data_clear['country_code'] : 'cleantalk'
                    );
                }
            }

            $apbct->data['brief_data']['top5_spam_ip'][$key] = $ip_data;
        }
    }

    $apbct->saveData();
}

/**
 * Delete cookie for admin trial notice
 */
function apbct__hook__wp_logout__delete_trial_notice_cookie()
{
    if ( ! headers_sent() ) {
        Cookie::setNativeCookie('ct_trial_banner_closed', '', time() - 3600);
    }
}

/**
 * Store URLs
 */
function apbct_store__urls()
{
    global $apbct;

    if (
        $apbct->data['cookies_type'] === 'none' || // Do not set cookies if option is disabled (for Varnish cache).
        ! empty($apbct->headers_sent)              // Headers sent
    ) {
        return false;
    }

    if ( $apbct->settings['misc__store_urls'] && empty($apbct->flags__url_stored) && ! headers_sent() ) {
        // URLs HISTORY
        // Get current url
        $current_url = Server::get('HTTP_HOST') . Server::get('REQUEST_URI');
        $current_url = $current_url ? substr($current_url, 0, 128) : 'UNKNOWN';
        $site_url    = parse_url(get_option('home'), PHP_URL_HOST);

        // Get already stored URLs
        $urls = Cookie::get('apbct_urls');
        $urls = $urls === '' ? [] : json_decode($urls, true);

        $urls[$current_url][] = time();

        // Rotating. Saving only latest 10
        $urls[$current_url] = count($urls[$current_url]) > 5 ? array_slice(
            $urls[$current_url],
            1,
            5
        ) : $urls[$current_url];
        $urls               = count($urls) > 5 ? array_slice($urls, 1, 5) : $urls;

        // Saving
        Cookie::set('apbct_urls', json_encode($urls, JSON_UNESCAPED_SLASHES), time() + 86400 * 3, '/', $site_url, null, true, 'Lax');

        // REFERER
        // Get current referer
        $new_site_referer = apbct_get_server_variable('HTTP_REFERER');
        $new_site_referer = $new_site_referer ?: 'UNKNOWN';

        // Get already stored referer
        $site_referer = Cookie::get('apbct_site_referer');

        // Save if empty
        if (
            $site_url &&
            (
                ! $site_referer ||
                parse_url($new_site_referer, PHP_URL_HOST) !== apbct_get_server_variable('HTTP_HOST')
            )
        ) {
            Cookie::set('apbct_site_referer', $new_site_referer, time() + 86400 * 3, '/', $site_url, null, true, 'Lax');
        }

        $apbct->flags__url_stored = true;
    }
}

/**
 * Set Cookies test for cookie test
 * Sets cookies with params timestamp && landing_timestamp && previous_referer
 * Sets test cookie with all other cookies
 * @return bool
 */
function apbct_cookie()
{
    global $apbct;

    if (
        $apbct->data['cookies_type'] === 'none' || // Do not set cookies if option is disabled (for Varnish cache).
        ! empty($apbct->flags__cookies_setuped) || // Cookies already set
        ! empty($apbct->headers_sent)              // Headers sent
    ) {
        return false;
    }

    // Prevent headers sent error
    if ( headers_sent($file, $line) ) {
        $apbct->headers_sent        = true;
        $apbct->headers_sent__hook  = current_filter();
        $apbct->headers_sent__where = $file . ':' . $line;

        return false;
    }


    // Cookie names to validate
    $cookie_test_value = array(
        'cookies_names' => array(),
        'check_value'   => $apbct->api_key,
    );

    // We need to skip the domain attribute for prevent including the dot to the cookie's domain on the client.
    $domain = '';

    // Submit time
    if ( empty(Post::get('ct_multipage_form')) ) { // Do not start/reset page timer if it is multi page form (Gravity forms))
        $apbct_timestamp = time();
        Cookie::set('apbct_timestamp', (string)$apbct_timestamp, 0, '/', $domain, null, true);
        $cookie_test_value['cookies_names'][] = 'apbct_timestamp';
        $cookie_test_value['check_value']     .= $apbct_timestamp;
    }

    // Previous referer
    if ( Server::get('HTTP_REFERER') ) {
        Cookie::set('apbct_prev_referer', Server::get('HTTP_REFERER'), 0, '/', $domain, null, true);
        $cookie_test_value['cookies_names'][] = 'apbct_prev_referer';
        $cookie_test_value['check_value']     .= apbct_get_server_variable('HTTP_REFERER');
    }

    // Landing time
    $site_landing_timestamp = Cookie::get('apbct_site_landing_ts');
    if ( ! $site_landing_timestamp ) {
        $site_landing_timestamp = time();
        Cookie::set('apbct_site_landing_ts', (string)$site_landing_timestamp, 0, '/', $domain, null, true);
    }
    $cookie_test_value['cookies_names'][] = 'apbct_site_landing_ts';
    $cookie_test_value['check_value']     .= $site_landing_timestamp;

    // Page hits
    // Get
    $page_hits = Cookie::get('apbct_page_hits');
    // Set / Increase
    $page_hits = (int)$page_hits ? (int)$page_hits + 1 : 1;

    Cookie::set('apbct_page_hits', (string)$page_hits, 0, '/', $domain, null, true);

    $cookie_test_value['cookies_names'][] = 'apbct_page_hits';
    $cookie_test_value['check_value']     .= $page_hits;

    // Cookies test
    $cookie_test_value['check_value'] = md5($cookie_test_value['check_value']);
    if ( $apbct->data['cookies_type'] === 'native' ) {
        Cookie::set('apbct_cookies_test', urlencode(json_encode($cookie_test_value)), 0, '/', $domain, null, true);
    }

    $apbct->flags__cookies_setuped = true;

    return $apbct->flags__cookies_setuped;
}

/**
 * Cookies test for sender
 * Also checks for valid timestamp in $_COOKIE['apbct_timestamp'] and other apbct_ COOKIES
 * @return null|int null|0|1
 * @throws JsonException
 */
function apbct_cookies_test()
{
    global $apbct;

    if ( $apbct->data['cookies_type'] === 'alternative' ) {
        return 1;
    }

    if ( Cookie::get('apbct_cookies_test') ) {
        $cookie_test = json_decode(urldecode(Cookie::get('apbct_cookies_test')), true);

        if ( ! is_array($cookie_test) ) {
            return 0;
        }

        $check_string = $apbct->api_key;
        foreach ( $cookie_test['cookies_names'] as $cookie_name ) {
            $check_string .= Cookie::get($cookie_name);
        }

        if ( $cookie_test['check_value'] == md5($check_string) ) {
            return 1;
        }

        return 0;
    }

    return null;
}

/**
 * Gets submit time
 * Uses Cookies with check via apbct_cookies_test()
 * @return null|int
 * @throws JsonException
 */
function apbct_get_submit_time()
{
    $apbct_timestamp = (int)Cookie::get('apbct_timestamp');

    return apbct_cookies_test() === 1 && $apbct_timestamp !== 0 ? time() - $apbct_timestamp : null;
}

/*
 * Inner function - Account status check
 * Scheduled in 1800 seconds for default!
 */
function ct_account_status_check($api_key = null, $process_errors = true)
{
    global $apbct;

    $api_key = $api_key ?: $apbct->api_key;
    $result  = API::methodNoticePaidTill(
        $api_key,
        preg_replace('/http[s]?:\/\//', '', get_option('home'), 1),
        ! is_main_site() && $apbct->white_label ? 'anti-spam-hosting' : 'antispam'
    );

    if ( empty($result['error']) || ! empty($result['valid']) ) {
        // Notices
        $apbct->data['notice_show']        = isset($result['show_notice']) ? (int)$result['show_notice'] : 0;
        $apbct->data['notice_renew']       = isset($result['renew']) ? (int)$result['renew'] : 0;
        $apbct->data['notice_trial']       = isset($result['trial']) ? (int)$result['trial'] : 0;
        $apbct->data['notice_review']      = isset($result['show_review']) ? (int)$result['show_review'] : 0;
        $apbct->data['notice_auto_update'] = isset($result['show_auto_update_notice']) ? (int)$result['show_auto_update_notice'] : 0;

        // Other
        $apbct->data['service_id']      = isset($result['service_id']) ? (int)$result['service_id'] : 0;
        $apbct->data['valid']           = isset($result['valid']) ? (int)$result['valid'] : 0;
        $apbct->data['moderate']        = isset($result['moderate']) ? (int)$result['moderate'] : 0;
        $apbct->data['ip_license']      = isset($result['ip_license']) ? (int)$result['ip_license'] : 0;
        $apbct->data['moderate_ip']     = isset($result['moderate_ip'], $result['ip_license']) ? (int)$result['moderate_ip'] : 0;
        $apbct->data['spam_count']      = isset($result['spam_count']) ? (int)$result['spam_count'] : 0;
        $apbct->data['auto_update']     = isset($result['auto_update_app']) ? (int)$result['auto_update_app'] : 0;
        $apbct->data['user_token']      = isset($result['user_token']) ? (string)$result['user_token'] : '';
        $apbct->data['license_trial']   = isset($result['license_trial']) ? (int)$result['license_trial'] : 0;
        $apbct->data['account_name_ob'] = isset($result['account_name_ob']) ? (string)$result['account_name_ob'] : '';

        $cron = new Cron();
        $cron->updateTask('check_account_status', 'ct_account_status_check', 86400);

        $apbct->errorDelete('account_check', 'save');

        $apbct->saveData();
    } elseif ( $process_errors ) {
        $apbct->errorAdd('account_check', $result);
    }

    if ( ! empty($result['valid']) ) {
        $apbct->data['key_is_ok'] = true;
        $result                   = true;
    } else {
        $apbct->data['key_is_ok'] = false;
        $result                   = false;
    }

    return $result;
}

function ct_mail_send_connection_report()
{
    global $apbct;

    if ( ($apbct->settings['misc__send_connection_reports'] == 1 && $apbct->connection_reports['negative'] > 0) || ! empty(Get::get('ct_send_connection_report')) ) {
        $to      = "welcome@cleantalk.org";
        $subject = "Connection report for " . apbct_get_server_variable('HTTP_HOST');
        $message = '
				<html lang="en">
				    <head>
				        <title></title>
				    </head>
				    <body>
				        <p>From '
                            . $apbct->connection_reports['since']
                            . ' to ' . date('d M') . ' has been made '
                            . ($apbct->connection_reports['success'] + $apbct->connection_reports['negative'])
                            . ' calls, where ' . $apbct->connection_reports['success'] . ' were success and '
                            . $apbct->connection_reports['negative'] . ' were negative
				        </p>
				        <p>Negative report:</p>
				        <table>  <tr>
				    <td>&nbsp;</td>
				    <td><b>Date</b></td>
				    <td><b>Page URL</b></td>
				    <td><b>Library report</b></td>
				    <td><b>Server IP</b></td>
				  </tr>
				  ';
        foreach ( $apbct->connection_reports['negative_report'] as $key => $report ) {
            $message .= '<tr>'
                        . '<td>' . ($key + 1) . '.</td>'
                        . '<td>' . $report['date'] . '</td>'
                        . '<td>' . $report['page_url'] . '</td>'
                        . '<td>' . $report['lib_report'] . '</td>'
                        . '<td>' . $report['work_url'] . '</td>'
                        . '</tr>';
        }
        $message .= '</table></body></html>';

        $headers = "Content-type: text/html; charset=windows-1251 \r\n";
        $headers .= 'From: ' . ct_get_admin_email();
        /** @psalm-suppress UnusedFunctionCall */
        mail($to, $subject, $message, $headers);
    }

    $apbct->data['connection_reports']          = $apbct->def_data['connection_reports'];
    $apbct->data['connection_reports']['since'] = date('d M');
    $apbct->saveData();
}

/**
 * Write $message to the plugin's debug option
 *
 * @param string|array|object $message
 * @param null|string $func
 * @param array $params
 *
 * @return void
 */
function apbct_log($message = 'empty', $func = null, $params = array())
{
    global $apbct;

    $debug = get_option(APBCT_DEBUG);

    $function = $func ?: '';
    $cron     = in_array('cron', $params);
    $data     = in_array('data', $params);
    $settings = in_array('settings', $params);

    if ( is_array($message) || is_object($message) ) {
        $message = print_r($message, true);
    }

    if ( $message ) {
        $debug[date("H:i:s") . (int)microtime() . "_ACTION_" . current_filter() . "_FUNCTION_" . $function] = $message;
    }
    if ( $cron ) {
        $debug[date("H:i:s") . (int)microtime() . "_ACTION_" . current_filter(
        ) . "_FUNCTION_" . $function . '_cron'] = $apbct->cron;
    }
    if ( $data ) {
        $debug[date("H:i:s") . (int)microtime() . "_ACTION_" . current_filter(
        ) . "_FUNCTION_" . $function . '_data'] = $apbct->data;
    }
    if ( $settings ) {
        $debug[date("H:i:s") . (int)microtime() . "_ACTION_" . current_filter(
        ) . "_FUNCTION_" . $function . '_settings'] = $apbct->settings;
    }

    update_option(APBCT_DEBUG, $debug);
}

function apbct_sfw__delete_tables($blog_id, $_drop)
{
    global $wpdb;

    $initial_blog = get_current_blog_id();

    switch_to_blog($blog_id);
    $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . 'cleantalk_sfw`;');       // Deleting SFW data
    $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . 'cleantalk_sfw_logs`;');  // Deleting SFW logs
    $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . 'cleantalk_ac_log`;');  // Deleting SFW logs
    $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . 'cleantalk_ua_bl`;');   // Deleting AC UA black lists

    switch_to_blog($initial_blog);
}

/**
 * Is enable for user group
 *
 * @param WP_User $user
 *
 * @return boolean
 */
function apbct_is_user_enable($user = null)
{
    global $current_user;

    $user = $user !== null ? $user : $current_user;

    return ! (apbct_is_user_role_in(array('administrator', 'editor', 'author'), $user) || apbct_is_super_admin());
}

/**
 * Checks if the current user has role
 *
 * @param array $roles array of strings
 * @param int|string|WP_User|mixed $user User ID to check|user_login|WP_User
 *
 * @return boolean Does the user has this role|roles
 */
function apbct_is_user_role_in($roles, $user = false)
{
    if ( is_numeric($user) && function_exists('get_userdata') ) {
        $user = get_userdata((int)$user);
    }
    if ( is_string($user) && function_exists('get_user_by') ) {
        $user = get_user_by('login', $user);
    }

    if ( ! $user && function_exists('wp_get_current_user') ) {
        $user = wp_get_current_user();
    }

    if ( ! $user ) {
        $user = apbct_wp_get_current_user();
    }

    if ( empty($user->ID) ) {
        return false;
    }

    foreach ( (array)$roles as $role ) {
        if ( isset($user->caps[strtolower($role)]) || in_array(strtolower($role), $user->roles) ) {
            return true;
        }
    }

    return false;
}

/**
 * Update and rotate statistics with requests execution time
 *
 * @param $exec_time
 */
function apbct_statistics__rotate($exec_time)
{
    global $apbct;

    // Delete old stats
    if ( min(array_keys($apbct->stats['requests'])) < time() - (86400 * 7) ) {
        unset($apbct->stats['requests'][min(array_keys($apbct->stats['requests']))]);
    }

    // Create new if newest older than 1 day
    if ( empty($apbct->stats['requests']) || max(array_keys($apbct->stats['requests'])) < time() - (86400 * 1) ) {
        $apbct->stats['requests'][time()] = array('amount' => 0, 'average_time' => 0);
    }

    // Update all existing stats
    foreach ( $apbct->stats['requests'] as &$weak_stat ) {
        $weak_stat['average_time'] = ($weak_stat['average_time'] * $weak_stat['amount'] + $exec_time) / ++$weak_stat['amount'];
    }
    unset($weak_stat);

    $apbct->save('stats');
}

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
                apbct_update__set_version__from_plugin('from_plugin');
            }

            ct_send_feedback('0:' . APBCT_AGENT); // Send feedback to let cloud know about updated version.

            // Side blogs
        } else {
            apbct_update__set_version__from_plugin('from_plugin');
        }
    }
}

/**
 * Set version of plugin in database
 *
 * @param string $ver
 *
 * @return bool
 * @global State $apbct
 *
 */
function apbct_update__set_version__from_plugin($ver)
{
    global $apbct;
    switch ( true ) {
        case $ver === 'from_plugin':
            $apbct->data['plugin_version'] = APBCT_VERSION;
            break;
        case preg_match('/^\d+\.\d+(\.\d+)?(-[a-zA-Z0-9-_]+)?$/', $ver) === 1:
            $apbct->data['plugin_version'] = $ver;
            break;
        default:
            return false;
    }
    $apbct->saveData();

    return true;
}

/**
 * Check connection to the API servers
 *
 * @return array
 */
function apbct_test_connection()
{
    $out         = array();
    $url_to_test = array_keys(\Cleantalk\Common\Helper::$cleantalks_servers);

    foreach ( $url_to_test as $url ) {
        $start  = microtime(true);
        $result = \Cleantalk\ApbctWP\Helper::httpRequestGetResponseCode($url);

        $out[$url] = array(
            'result'    => ! empty($result['error']) ? $result['error'] : 'OK',
            'exec_time' => microtime(true) - $start,
        );
    }

    return $out;
}
