<?php

/*
  Plugin Name: Anti-Spam by CleanTalk
  Plugin URI: https://cleantalk.org
  Description: Max power, all-in-one, no Captcha, premium anti-spam plugin. No comment spam, no registration spam, no contact spam, protects any WordPress forms.
  Version: 6.59
  Author: CleanTalk - Anti-Spam Protection <welcome@cleantalk.org>
  Author URI: https://cleantalk.org
  Text Domain: cleantalk-spam-protect
  Domain Path: /i18n
*/

use Cleantalk\Antispam\ProtectByShortcode;
use Cleantalk\ApbctWP\Activator;
use Cleantalk\ApbctWP\AdminNotices;
use Cleantalk\ApbctWP\Antispam\EmailEncoder;
use Cleantalk\ApbctWP\Antispam\ForceProtection;
use Cleantalk\ApbctWP\API;
use Cleantalk\ApbctWP\CleantalkRealPerson;
use Cleantalk\ApbctWP\CleantalkUpgrader;
use Cleantalk\ApbctWP\CleantalkUpgraderSkin;
use Cleantalk\ApbctWP\CleantalkUpgraderSkinDeprecated;
use Cleantalk\ApbctWP\Cron;
use Cleantalk\ApbctWP\DB;
use Cleantalk\ApbctWP\Deactivator;
use Cleantalk\ApbctWP\Firewall\AntiCrawler;
use Cleantalk\ApbctWP\Firewall\AntiFlood;
use Cleantalk\ApbctWP\Firewall\SFW;
use Cleantalk\ApbctWP\Firewall\SFWUpdateHelper;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\RemoteCalls;
use Cleantalk\ApbctWP\RequestParameters\RequestParameters;
use Cleantalk\ApbctWP\RestController;
use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Transaction;
use Cleantalk\ApbctWP\UpdatePlugin\DbTablesCreator;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Request;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\DNS;
use Cleantalk\Common\Firewall;
use Cleantalk\Common\Schema;
use Cleantalk\Common\TT;

global $apbct, $wpdb, $pagenow;

$cleantalk_executed = false;

/**
 * Define common const.
 */
$plugin_info           = get_file_data(__FILE__, array('Version' => 'Version', 'Name' => 'Plugin Name',));
define('APBCT_NAME', isset($plugin_info['Name']) ? $plugin_info['Name'] : 'Anti-Spam by CleanTalk');
define('APBCT_VERSION', isset($plugin_info['Version']) ? $plugin_info['Version'] : '1.0.0');
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

/**
 * Define options names const.
 */
define('APBCT_DATA', 'cleantalk_data');             // Option name with different plugin data.
define('APBCT_SETTINGS', 'cleantalk_settings');         // Option name with plugin settings.
define('APBCT_NETWORK_SETTINGS', 'cleantalk_network_settings'); // Option name with plugin network settings.
define('APBCT_JS_ERRORS', 'cleantalk_js_errors');            // Option name with js errors. Empty by default.


/**
 * Define service const.
 */
define('APBCT_REMOTE_CALL_SLEEP', 5); // Minimum time between remote call
define('APBCT_WPMS', (is_multisite() ? true : false)); // WordPress Multisite - if WMPS is enabled
define('APBCT_LANG_REL_PATH', 'cleantalk-spam-protect/i18n');
if ( ! defined('CLEANTALK_PLUGIN_DIR') ) {
    define('CLEANTALK_PLUGIN_DIR', dirname(__FILE__) . '/');
}

/**
 * Require PHP patch.
 */
require_once(CLEANTALK_PLUGIN_DIR . 'lib/cleantalk-php-patch.php');  // Pathces fpr different functions which not exists

/**
 * Require the Autoloader
 */
require_once(CLEANTALK_PLUGIN_DIR . 'lib/autoloader.php');

if (!defined('APBCT_IS_LOCALHOST')) {
    define('APBCT_IS_LOCALHOST', in_array(Server::getDomain(), array('lc', 'loc', 'lh', 'test')));
}

/**
 * Define API params const.
 */
$plugin_version__agent = APBCT_VERSION;
// Converts version to xxx.xxx.xx-dev to xxx.xxx.2xx and xxx.xxx.xx-fix to xxx.xxx.1xx
if ( preg_match('@^(\d+)\.(\d+)\.(\d{1,2})-(dev|fix)$@', $plugin_version__agent, $m) ) {
    $major_version = TT::getArrayValueAsString($m, 1);
    $minor_version = TT::getArrayValueAsString($m, 2);
    $branch_sub = TT::getArrayValueAsString($m, 4) === 'dev' ? '2' : '1';
    $padded = str_pad(
        TT::getArrayValueAsString($m, 3),
        2,
        '0',
        STR_PAD_LEFT
    );
    $plugin_version__agent = $major_version . '.' . $minor_version . '.' . $branch_sub . $padded;
}
define('APBCT_AGENT', 'wordpress-' . $plugin_version__agent); // Prepared agent

if ( defined('CLEANTALK_SERVER') ) {
    define('APBCT_MODERATE_URL', 'https://moderate.' . CLEANTALK_SERVER);
    if ( ! defined('CLEANTALK_API_URL') ) {
        define('CLEANTALK_API_URL', 'https://api.' . CLEANTALK_SERVER);
    }
} else {
    define('APBCT_MODERATE_URL', 'https://moderate.cleantalk.org'); // Api URL
}

/**
 * Require base classes.
 */
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-pluggable.php');  // Pluggable functions
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-common.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-wpcli.php');

/**
 * Global state handle.
 */
// Global ArrayObject with settings and other global variables
$apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
// Init plugin basename.
$apbct->base_name = 'cleantalk-spam-protect/cleantalk.php';
// Identify plugin execution
$apbct->plugin_request_id = md5(microtime());
// Init logos.
$apbct->logo                 = plugin_dir_url(__FILE__) . 'inc/images/logo.png';
$apbct->logo__small          = plugin_dir_url(__FILE__) . 'inc/images/logo_small.png';
$apbct->logo__small__colored = plugin_dir_url(__FILE__) . 'inc/images/logo_color.png';

// Init Account status
$apbct->white_label      = $apbct->network_settings['multisite__white_label'];
$apbct->allow_custom_key = $apbct->network_settings['multisite__work_mode'] != 2;
$apbct->plugin_name      = $apbct->network_settings['multisite__white_label__plugin_name'] ? $apbct->network_settings['multisite__white_label__plugin_name'] : APBCT_NAME;
$apbct->api_key          = ! APBCT_WPMS || $apbct->allow_custom_key || $apbct->white_label ? $apbct->settings['apikey'] : $apbct->network_settings['apikey'];
$apbct->key_is_ok        = ! APBCT_WPMS || $apbct->allow_custom_key || $apbct->white_label ? $apbct->data['key_is_ok'] : $apbct->network_data['key_is_ok'];
$apbct->moderate         = ! APBCT_WPMS || $apbct->allow_custom_key || $apbct->white_label ? $apbct->data['moderate'] : $apbct->network_data['moderate'];
// Init counter
$apbct->data['user_counter']['since'] = isset($apbct->data['user_counter']['since'])
    ? $apbct->data['user_counter']['since']
    : date('d M');

// Init SFW update data
$apbct->firewall_updating = (bool)$apbct->fw_stats['firewall_updating_id'];
$apbct->data['sfw_load_type'] = (!APBCT_WPMS || is_main_site() || $apbct->network_settings['multisite__work_mode'] === 2) ? 'all' : 'personal';
$apbct->saveData();

// Init settings link
$apbct->settings_link = is_network_admin() ? 'settings.php?page=cleantalk' : 'options-general.php?page=cleantalk';

// Connection reports
$apbct->setConnectionReports();
// SFW update sentinel
$apbct->setSFWUpdateSentinel();
// User IP Keeper - used for checkers
$apbct->setLoginIPKeeper();

add_action('wp_login', 'apbct_wp_login_actions', 10, 2);

/**
 * Actions for hook 'wp-login'.
 * @param $user_login
 * @param $wp_user
 *
 * @return void
 */
function apbct_wp_login_actions($_user_login, $wp_user)
{
    global $apbct;
    $apbct->login_ip_keeper->addUserIP($wp_user);
    apbct_add_admin_ip_to_swf_whitelist($wp_user);
}

// Disabling comments
if ( $apbct->settings['comments__disable_comments__all'] || $apbct->settings['comments__disable_comments__posts'] || $apbct->settings['comments__disable_comments__pages'] || $apbct->settings['comments__disable_comments__media'] ) {
    \Cleantalk\Antispam\DisableComments::getInstance();
}

// Email encoder
if (
    $apbct->key_is_ok &&
    ( ! is_admin() || apbct_is_ajax() ) &&
    current_action() !== 'wp_ajax_delete-plugin'
) {
    $skip_email_encode = false;

    if (!empty($_POST)) {
        foreach ( $_POST as $param => $_value ) {
            if ( strpos((string)$param, 'et_pb_contactform_submit') === 0 ) {
                $skip_email_encode = true;
                break;
            }
        }
    }

    if (!$skip_email_encode && !apbct_is_amp_request()) {
        EmailEncoder::getInstance();

        // Email Encoder ajax handlers
        EmailEncoder::getInstance()->registerAjaxRoute();
    }

    // Force protection to avoid spam from bots without javascript
    if ($apbct->settings['forms__force_protection']) {
        ForceProtection::getInstance();
    }
}

if ( $apbct->settings['comments__the_real_person'] ) {
    new CleantalkRealPerson();
}

add_action('rest_api_init', 'apbct_register_my_rest_routes');
function apbct_register_my_rest_routes()
{
    $controller = new RestController();
    $controller->register_routes();
}

// Register hooks for AJAX requests
\Cleantalk\ApbctWP\HooksRegistrar::registerAjaxHooks($apbct->ajax_service);

// Database prefix
global $wpdb, $wp_version;
$apbct->db_prefix = ! APBCT_WPMS || $apbct->allow_custom_key || $apbct->white_label ? $wpdb->prefix : $wpdb->base_prefix;
$apbct->db_prefix = ! $apbct->white_label && defined('CLEANTALK_ACCESS_KEY') ? $wpdb->base_prefix : $wpdb->prefix;

/** @todo HARDCODE FIX */
if ( $apbct->plugin_version === '1.0.0' ) {
    $apbct->plugin_version = '5.100';
}

/**
 * Do update actions if version is changed
 * ! we can`t place this function to the hook "upgrader_process_complete" !
 */
apbct_update_actions();

add_action('init', function () {
    global $apbct;

    // Self cron
    $ct_cron = Cron::getInstance();
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
                    $apbct->errorDelete('cron', true);
                } else {
                    $apbct->errorAdd('cron', $res);
                }
            }
        }
    }
    // Remote calls
    if ( RemoteCalls::check() ) {
        try {
            /**
             * Needs to include apbct_settings__validate() for run_service_template_get remote call.
             * TODO:Probably we should refactor apbct_settings__validate() to a class feature to use it within autoloader
             */
            if ( Get::get('spbc_remote_call_action') === 'run_service_template_get' ) {
                require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-settings.php');
            }
            RemoteCalls::perform();
        } catch ( Exception $e ) {
            die(json_encode(array('ERROR' => $e->getMessage())));
        }
    }
});

//Delete cookie for admin trial notice
add_action('wp_logout', 'apbct__hook__wp_logout__delete_trial_notice_cookie');

// Set cookie only for public pages and for non-AJAX requests
if ( ! is_admin() && ! apbct_is_ajax() && ! defined('DOING_CRON')
     && ! apbct__is_rest_api_request()
     && empty(Post::get('ct_checkjs_register_form')) // Buddy press registration fix
     && empty(Get::get('ct_checkjs_search_default')) // Search form fix
     && empty(Post::get('action')) //bbPress
     && ! \Cleantalk\Variables\Server::inUri('/favicon.ico') // /favicon request rewritten cookies fix
     && ! apbct__is_wp_rocket_preloader_request()
) {
    if ( $apbct->data['cookies_type'] !== 'alternative' ) {
        if ( !$apbct->settings['forms__search_test'] && !Get::get('s') ) { //skip cookie set for search form redirect page
            add_action('template_redirect', 'apbct_cookie', 2);
        }
        add_action('template_redirect', 'apbct_store__urls', 2);
        add_action('template_redirect', 'apbct_store__page_hits', 2);
    }
    if (
        empty($_POST) &&
        $apbct->data['key_is_ok']
    ) {
        if ( (isset($_GET['q']) && $_GET['q'] !== '') || empty($_GET) ) {
            apbct_cookie();
        }
        //store url and hits ignoring GET containment
        apbct_store__page_hits();
        apbct_store__urls();
    }
}

require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-validate-skip-functions.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-validate.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-integrations.php');

// Early checks

if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'wpmlsubscribe') {
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-ajax.php');
    ct_ajax_hook();
}

// Iphorm
if (
    Post::get('iphorm_ajax') !== '' &&
    Post::get('iphorm_id') !== '' &&
    Post::get('iphorm_uid') !== ''
) {
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-ajax.php');
    ct_ajax_hook();
}

// Facebook
if ( $apbct->settings['forms__general_contact_forms_test'] == 1
     && ( Post::get('action') === 'fb_intialize')
     && ! empty(Post::get('FB_userdata'))
) {
    if ( apbct_is_user_enable() ) {
        ct_registration_errors(null);
    }
}

require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-integrations-by-hook.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-integrations-by-class.php');

// Form protection by shortcode
new ProtectByShortcode();

// WP Delicious integration
add_filter('delicious_recipes_process_registration_errors', 'apbct_wp_delicious', 10, 4);

$js_errors_arr = apbct_check_post_for_no_cookie_data();
if ($js_errors_arr && isset($js_errors_arr['data'])) {
    apbct_write_js_errors($js_errors_arr['data']);
}

/**
 * @param string $data
 * @psalm-suppress UnusedVariable
 */
function apbct_write_js_errors($data)
{
    if (!is_string($data) || empty($data)) {
        return false;
    }
    $tmp = substr($data, strlen('_ct_no_cookie_data_'));
    $errors = json_decode(base64_decode($tmp), true);
    if (!isset($errors['ct_js_errors'])) {
        return false;
    }
    $errors = $errors['ct_js_errors'];
    $exist_errors = get_option(APBCT_JS_ERRORS);

    if (!$exist_errors) {
        return update_option(APBCT_JS_ERRORS, $errors);
    }

    $errors_collection_msgs = [];
    foreach ($exist_errors as $err_index => $err_value) {
        array_push($errors_collection_msgs, $err_value['err']['msg']);
    }

    foreach ($errors as $err_index => $err_value) {
        if (!in_array($err_value['err']['msg'], $errors_collection_msgs)) {
            array_push($exist_errors, $err_value);
        }
    }

    return update_option(APBCT_JS_ERRORS, $exist_errors);
}

// LearnPress
if (
    apbct_is_plugin_active('learnpress/learnpress.php') &&
    apbct_is_in_uri('lp-ajax=checkout') &&
    sizeof($_POST) > 0
) {
    apbct_form__learnpress__testSpam();
}

// Appointment Booking Calendar
if (
    apbct_is_plugin_active('appointment-booking-calendar/cpabc_appointments.php') &&
    Post::getString('cpabc_appointments_post')
) {
    apbct_form__appointment_booking_calendar__testSpam();
}

// OptimizePress
if (
    apbct_is_plugin_active('op-dashboard/op-dashboard.php') &&
    apbct_is_in_uri('/optin/submit') &&
    sizeof($_POST) > 0
) {
    apbct_form__optimizepress__testSpam();
}

// Mailoptin. Pass without action because url for ajax request is domain.com/any-page/?mailoptin-ajax=subscribe_to_email_list
if (
    apbct_is_plugin_active('mailoptin/mailoptin.php') &&
    sizeof($_POST) > 0 &&
    Get::get('mailoptin-ajax') === 'subscribe_to_email_list'
) {
    apbct_form__mo_subscribe_to_email_list__testSpam();
}

// Metform
if (
    apbct_is_plugin_active('metform/metform.php') &&
    sizeof($_POST) > 0 &&
    (
        apbct_is_in_uri('/wp-json/metform/') ||
        (apbct_get_rest_url_only_path() !== 'index.php' && apbct_is_in_uri(apbct_get_rest_url_only_path() . 'metform/'))
    )
) {
    apbct_form__metform_subscribe__testSpam();
}

// Memberpress integration
if (
    !empty($_POST) &&
    apbct_is_plugin_active('memberpress/memberpress.php') &&
    Post::hasString('mepr_process_signup_form', '1') &&
    (int)$apbct->settings['forms__registrations_test'] === 1
) {
    apbct_memberpress_signup_request_test();
}

// Ninja Forms. Making GET action to POST action
if (
    apbct_is_in_uri('admin-ajax.php') &&
    sizeof($_POST) > 0 &&
    Get::get('action') === 'ninja_forms_ajax_submit'
) {
    $_POST['action'] = 'ninja_forms_ajax_submit';
}

// GiveWP without ajax
if (
    !empty($_POST) &&
    (int)$apbct->settings['forms__contact_forms_test'] === 1 &&
    apbct_is_plugin_active('give/give.php') &&
    !empty($_POST['give-form-hash']) &&
    !empty($_POST['give-form-id'])
) {
    apbct_givewp_donate_request_test();
}

// JetformBuilder
if (
    !empty($_POST) &&
    apbct_is_plugin_active('jetformbuilder/jet-form-builder.php') &&
    Get::get('jet_form_builder_submit') === 'submit'
) {
    apbct_jetformbuilder_request_test();
}

// DHVC Form
if (
    !empty($_POST) &&
    apbct_is_plugin_active('dhvc-form/dhvc-form.php') &&
    Post::get('dhvc_form') && Post::get('_dhvc_form_nonce')
) {
    apbct_dhvcform_request_test();
}

// SeedConfirmPro
if (!empty($_POST) &&
    apbct_is_plugin_active('seed-confirm-pro/seed-confirm-pro.php') &&
    Post::get('seed_confirm_nonce')
) {
    apbct_seedConfirmPro_request_test();
}

// LeakyPaywall
if (!empty($_POST) &&
    apbct_is_plugin_active('leaky-paywall/leaky-paywall.php') &&
    apbct_is_in_uri('registration-form') &&
    Post::get('level_id')
) {
    apbct_leakyPaywall_request_test();
}

add_action('wp_ajax_nopriv_ninja_forms_ajax_submit', 'apbct_form__ninjaForms__testSpam', 1);
add_action('wp_ajax_ninja_forms_ajax_submit', 'apbct_form__ninjaForms__testSpam', 1);
add_action('wp_ajax_nopriv_nf_ajax_submit', 'apbct_form__ninjaForms__testSpam', 1);
add_action('wp_ajax_nf_ajax_submit', 'apbct_form__ninjaForms__testSpam', 1);
add_action('ninja_forms_process', 'apbct_form__ninjaForms__testSpam', 1); // Depricated ?
add_action('ninja_forms_display_after_form', 'apbct_form__ninjaForms__addField', 1000, 10);

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
    if ( TT::getArrayValueAsInt($ct_result, 'allow') === 0 ) {
        ct_die_extended(TT::getArrayValueAsString($ct_result, 'comment'));
    }
}, 1, 2);

add_action('elementor/frontend/the_content', 'apbct_form__elementor_pro__addField', 10, 2);

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
add_filter('happyforms_use_hash_protection', '__return_false');

// Formidable
add_filter('frm_entries_before_create', 'apbct_form__formidable__testSpam', 999999, 2);
add_action('frm_entries_footer_scripts', 'apbct_form__formidable__footerScripts', 20, 2);


add_action('mec_booking_end_form_step_2', function () {
    echo "<script>
        if (typeof ctPublic.force_alt_cookies == 'undefined' || (ctPublic.force_alt_cookies !== 'undefined' && !ctPublic.force_alt_cookies)) {
			ctNoCookieAttachHiddenFieldsToForms();
		}
    </script>";
});

// Public actions
if ( ! is_admin() && ! apbct_is_ajax() && ! apbct_is_customize_preview() ) {
    if (apbct_is_plugin_active('fluentformpro/fluentformpro.php') && apbct_is_in_uri('ff_landing=')) {
        add_action('wp_head', function () {
            echo '<script data-pagespeed-no-defer="" src="'
                . APBCT_URL_PATH
                . '/js/apbct-public-bundle.min.js'
                . '?ver=' . APBCT_VERSION . '" id="ct_public_functions-js"></script>';
            echo '<script src="' . APBCT_MODERATE_URL . '/ct-bot-detector-wrapper.js?ver='
                . APBCT_VERSION . '" id="ct_bot_detector-js"></script>';
        }, 100);
    }

    // SpamFireWall check
    if ( $apbct->plugin_version == APBCT_VERSION && // Do not call with first start
         $apbct->settings['sfw__enabled'] == 1 &&
         $apbct->stats['sfw']['last_update_time'] &&
         apbct_is_get() &&
         ! apbct_wp_doing_cron() &&
         ! Server::inUri('/favicon.ico') &&
         ! apbct_is_cli()
    ) {
        add_action('init', 'apbct_sfw__init_wrapper', 1);
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

register_uninstall_hook(__FILE__, 'apbct_uninstall');
function apbct_uninstall($network_wide)
{
    global $apbct;
    $apbct->settings['misc__complete_deactivation'] = 1;
    $apbct->saveSettings();
    Deactivator::deactivation($network_wide);
}

// Hook for newly added blog
if ( version_compare($wp_version, '5.1') >= 0  ) {
    add_action('wp_initialize_site', 'apbct_activation__new_blog', 10, 2);
    add_action('wp_uninitialize_site', 'apbct_wpms__delete_blog', 10, 1);
} else {
    add_action('wpmu_new_blog', 'apbct_activation__new_blog__deprecated', 10, 6);
    add_action('delete_blog', 'apbct_wpms__delete_blog__deprecated', 10, 2);
}

function apbct_activation__new_blog__deprecated($blog_id, $_user_id, $_domain, $_path, $_site_id, $_meta)
{
    Activator::activation(false, $blog_id);
}
function apbct_activation__new_blog(WP_Site $new_site, $_args)
{
    Activator::activation(false, $new_site->blog_id);
}
function apbct_wpms__delete_blog__deprecated($blog_id, $_drop)
{
    apbct_sfw__delete_tables($blog_id);
}
function apbct_wpms__delete_blog(WP_Site $old_site)
{
    apbct_sfw__delete_tables($old_site->blog_id);
}

// Async loading for JavaScript
add_filter('script_loader_tag', 'apbct_add_async_attribute', 10, 3);

// Redirect admin to plugin settings.
if ( ! defined('WP_ALLOW_MULTISITE') || (defined('WP_ALLOW_MULTISITE') && WP_ALLOW_MULTISITE == false) ) {
    add_action('admin_init', 'apbct_plugin_redirect');
}

// After plugin loaded - to load locale as described in manual
add_action('init', 'apbct_plugin_loaded');

if ( ! empty($apbct->settings['data__use_ajax']) &&
     ! apbct_is_in_uri('.xml') &&
     ! apbct_is_in_uri('.xsl')
) {
    $apbct->ajax_service->addPublicAction('ct_get_cookie', 'ct_get_cookie');
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

        global $apbct_active_integrations;
        if (isset($apbct_active_integrations) && is_array($apbct_active_integrations)) {
            $integrated_hooks = array_column($apbct_active_integrations, 'hook');
            foreach ( $integrated_hooks as $hook ) {
                if ( is_array($hook) ) {
                    foreach ( $hook as $_item ) {
                        $_cleantalk_hooked_actions[] = $_item;
                    }
                } else {
                    $_cleantalk_hooked_actions[] = $hook;
                }
            }
        }

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
            )
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
        add_filter('registration_errors', 'ct_check_registration_errors', 999999, 3);
        add_action('user_register', 'apbct_user_register');
    }

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
        add_filter('all_plugins', 'apbct_admin__change_plugin_description');

        add_filter('plugin_row_meta', 'apbct_admin__register_plugin_links', 10, 3);
    }
// Public pages actions
} else {
    add_action('wp_enqueue_scripts', 'ct_enqueue_scripts_public');
    add_action('wp_enqueue_scripts', 'ct_enqueue_styles_public');
    add_action('login_enqueue_scripts', 'ct_enqueue_styles_public');

    // Init action.
    add_action('plugins_loaded', 'apbct_init', 1);

    // Comments
    add_filter('comment_text', 'ct_comment_text');

    // Registrations
    if ( ! Post::get('wp-submit') ) {
        add_action('login_form_register', 'apbct_cookie');
        add_action('login_form_register', 'apbct_store__urls');
        add_action('login_form_register', 'apbct_store__page_hits');
    }
    add_action('login_enqueue_scripts', 'apbct_login__scripts');
    add_action('register_form', 'ct_register_form');
    add_filter('registration_errors', 'ct_registration_errors', 1, 3);
    add_filter('registration_errors', 'ct_check_registration_errors', 999999, 3);
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

/**
 * Wrapper for SpamFireWall check to make if fire on 'init' hook
 * @return void
 */
function apbct_sfw__init_wrapper()
{
    wp_suspend_cache_addition(true);
    apbct_sfw__check();
    wp_suspend_cache_addition(false);
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
            Cookie::set(
                'spbc_firewall_pass_key',
                md5(Server::get('REMOTE_ADDR') . $spbc_key),
                time() + 1200,
                '/',
                ''
            );
            Cookie::set(
                'ct_sfw_pass_key',
                md5(Server::get('REMOTE_ADDR') . $apbct->api_key),
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

    add_action('init', function () use ($apbct, $is_sfw_outdated) {
        $apbct->errorToggle(
            $is_sfw_outdated,
            'sfw_outdated',
            esc_html__(
                'SpamFireWall database is outdated. Please, try to synchronize with the cloud.',
                'cleantalk-spam-protect'
            )
        );
    });

    if ( $is_sfw_outdated ) {
        return;
    }

    $firewall = new Firewall(
        DB::getInstance()
    );

    $sfw_tables_names = SFW::getSFWTablesNames();

    if (!$sfw_tables_names) {
        add_action('init', function () use ($apbct) {
            $apbct->errorAdd(
                'sfw',
                esc_html__(
                    'Can not get SFW table names from main blog options',
                    'cleantalk-spam-protect'
                )
            );
        });
        return;
    }

    $firewall->loadFwModule(
        new SFW(
            APBCT_TBL_FIREWALL_LOG,
            $sfw_tables_names['sfw_personal_table_name'],
            array(
                'sfw_counter'       => $apbct->settings['admin_bar__sfw_counter'],
                'api_key'           => $apbct->api_key,
                'apbct'             => $apbct,
                'cookie_domain'     => parse_url(get_option('home'), PHP_URL_HOST),
                'data__cookies_type' => $apbct->data['cookies_type'],
                'sfw_common_table_name'  => $sfw_tables_names['sfw_common_table_name'],
            )
        )
    );

    if ( $apbct->settings['sfw__anti_crawler'] && $apbct->stats['sfw']['entries'] > 50 ) {
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

// This action triggered by  wp_schedule_single_event( time() + 720, 'apbct_sfw_update__init' );
add_action('apbct_sfw_update__init', 'apbct_sfw_update__init');


/**
 * * * * * * SFW UPDATE ACTIONS * * * * * *
 */

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

    //do not run sfw update on subsites if mutual key is set
    if ($apbct->network_settings['multisite__work_mode'] === 2 && !is_main_site()) {
        return false;
    }

    // Prevent start an update if update is already running and started less than 10 minutes ago
    if (
        $apbct->fw_stats['firewall_updating_id'] &&
        time() - $apbct->fw_stats['firewall_updating_last_start'] < 600 &&
        SFWUpdateHelper::updateIsInProgress() &&
        ! SFWUpdateHelper::updateIsFrozen()
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
    $update_period = DNS::getRecord('spamfirewall-ttl-txt.cleantalk.org', true, true);
    $update_period = isset($update_period['txt']) ? $update_period['txt'] : 0;
    $update_period = (int)$update_period > 14400 ? (int)$update_period : 14400;
    if ( $apbct->stats['sfw']['update_period'] != $update_period ) {
        $apbct->stats['sfw']['update_period'] = $update_period;
        $apbct->save('stats');
    }

    $sfw_tables_names = SFW::getSFWTablesNames();

    if ( !$sfw_tables_names ) {
        //try to create tables
        $sfw_tables_names = apbct_sfw_update__create_tables(false, true);
        if (!$sfw_tables_names) {
            return array('error' => 'Can not get SFW table names from main blog options');
        }
    }

    $apbct->data['sfw_common_table_name'] = TT::getArrayValueAsString($sfw_tables_names, 'sfw_common_table_name');
    $apbct->data['sfw_personal_table_name'] = TT::getArrayValueAsString($sfw_tables_names, 'sfw_personal_table_name');
    $apbct->save('data');

    $wp_upload_dir = wp_upload_dir();
    $base_dir = TT::getArrayValueAsString($wp_upload_dir, 'basedir');
    $apbct->fw_stats['updating_folder'] = $base_dir . DIRECTORY_SEPARATOR . 'cleantalk_fw_files_for_blog_' . get_current_blog_id() . DIRECTORY_SEPARATOR;
    //update only common tables if moderate 0
    if ( ! $apbct->moderate ) {
        $apbct->data['sfw_load_type'] = 'common';
    }

    if ( $apbct->network_settings['multisite__work_mode'] == 3) {
        $apbct->data['sfw_load_type'] = 'all';
        $apbct->save('data');
    }

    if (apbct_sfw_update__switch_to_direct()) {
        return SFWUpdateHelper::directUpdate();
    }

    // Set a new update ID and an update time start
    $apbct->fw_stats['calls']                        = 0;
    $apbct->fw_stats['firewall_updating_id']         = md5((string)rand(0, 100000));
    $apbct->fw_stats['firewall_updating_last_start'] = time();
    $apbct->fw_stats['common_lists_url_id'] = '';
    $apbct->fw_stats['personal_lists_url_id'] = '';
    $apbct->save('fw_stats');

    $apbct->sfw_update_sentinel->seekId($apbct->fw_stats['firewall_updating_id']);

    // Delete update errors
    $apbct->errorDelete('sfw_update', 'save_data');
    $apbct->errorDelete('sfw_update', 'save_data', 'cron');

    \Cleantalk\ApbctWP\Queue::clearQueue();

    $queue = new \Cleantalk\ApbctWP\Queue();
    //this is the first stage, select what type of SFW load need
    $load_type = isset($apbct->data['sfw_load_type']) ? TT::toString($apbct->data['sfw_load_type']) : 'all';
    $get_multifiles_params = array();
    if ( $load_type === 'all' ) {
        $queue->addStage('apbct_sfw_update__get_multifiles_all');
    } else {
        $get_multifiles_params['type'] = $load_type;
        $get_multifiles_params['do_return_urls'] = false;
        $queue->addStage('apbct_sfw_update__get_multifiles_of_type', $get_multifiles_params);
    }

    $cron = new Cron();
    $watch_dog_period = $apbct->sfw_update_sentinel->getWatchDogCronPeriod();
    $cron->addTask('sfw_update_sentinel_watchdog', 'apbct_sfw_update_sentinel__run_watchdog', $watch_dog_period, time() + $watch_dog_period);
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
 * Decide need to force direct update
 *
 * @return bool
 * @psalm-suppress NullArgument
 */
function apbct_sfw_update__switch_to_direct()
{
    global $apbct;

    $apbct->fw_stats['reason_direct_update_log'] = null;

    if (defined('APBCT_SFW_FORCE_DIRECT_UPDATE')) {
        $apbct->fw_stats['reason_direct_update_log'] = 'const APBCT_SFW_FORCE_DIRECT_UPDATE exists';
        return true;
    }

    $prepare_dir__result = SFWUpdateHelper::prepareUpdDir();
    if (!empty($prepare_dir__result['error'])) {
        $apbct->fw_stats['reason_direct_update_log'] = 'variable prepare_dir__result has error';
        return true;
    }

    $test_rc_result = Helper::httpRequestRcToHostTest(
        'sfw_update__worker',
        array(
            'spbc_remote_call_token' => md5($apbct->api_key),
            'spbc_remote_call_action' => 'sfw_update__worker',
            'plugin_name' => 'apbct'
        )
    );
    if (!empty($test_rc_result['error'])) {
        $apbct->fw_stats['reason_direct_update_log'] = 'test remote call has error';
        return true;
    }

    if (isset($apbct->fw_stats['firewall_updating_last_start'], $apbct->stats['sfw']['update_period']) &&
    ((int)$apbct->fw_stats['firewall_updating_last_start'] + (int)$apbct->stats['sfw']['update_period'] + 3600) < time()) {
        $apbct->fw_stats['reason_direct_update_log'] = 'general update is freezing';
        return true;
    }

    return false;
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

    if ( ! $apbct->data['key_is_ok'] ) {
        return array('error' => 'Worker: KEY_IS_NOT_VALID');
    }

    if ( ! $apbct->settings['sfw__enabled'] ) {
        return false;
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

    if ( $result === null ) {
        // The stage is in progress, will try to wait up to 5 seconds to its complete
        for ( $i = 0; $i < 5; $i++ ) {
            sleep(1);
            $queue->refreshQueue();
            if ( ! $queue->isQueueInProgress() ) {
                // The stage executed, break waiting and continue sfw_update__worker process
                break;
            }
            if ( $i >= 4 ) {
                // The stage still not executed, exit from sfw_update__worker
                return true;
            }
        }
    }

    if ( isset($result['error'], $result['status']) && $result['status'] === 'FINISHED' ) {
        SFWUpdateHelper::fallback();

        $direct_upd_res = SFWUpdateHelper::directUpdate();

        if ( !empty($direct_upd_res['error']) ) {
            $apbct->errorAdd('queue', $result['error'], 'sfw_update');
            $apbct->errorAdd('direct', $direct_upd_res['error'], 'sfw_update');
            $apbct->saveErrors();

            return $direct_upd_res['error'];
        }

        //stop seeking updates on success direct update
        $apbct->sfw_update_sentinel->clearSentinelData();

        return true;
    }

    if ( $queue->isQueueFinished() ) {
        $queue->queue['finished'] = time();
        $queue->saveQueue($queue->queue);
        foreach ( $queue->queue['stages'] as $stage ) {
            if ( isset($stage['error'], $stage['status']) && $stage['status'] !== 'FINISHED' ) {
                //there could be an array of errors of files processed
                if ( is_array($stage['error']) ) {
                    $error = implode(" ", array_values($stage['error']));
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
    if ( stripos(TT::toString(Request::get('stage')), 'Repeat') !== false ) {
        return true;
    }

    return Helper::httpRequestRcToHost(
        'sfw_update__worker',
        array('firewall_updating_id' => $apbct->fw_stats['firewall_updating_id']),
        array('async')
    );
}

/**
 * QUEUE STAGES *
 */

/**
 * Queue stage. Get both types of multifiles (common/personal) url for next downloading.
 * @return array|array[]|string[]
 */
function apbct_sfw_update__get_multifiles_all()
{
    global $apbct;

    if ( ! $apbct->data['key_is_ok'] ) {
        return array('error' => 'Get multifiles: KEY_IS_NOT_VALID');
    }

    $get_multifiles_common_urls = apbct_sfw_update__get_multifiles_of_type(array('type' => 'common', 'do_return_urls' => true));
    if ( !empty($get_multifiles_common_urls['error']) ) {
        $output_error = $get_multifiles_common_urls['error'];
    }

    $get_multifiles_personal_urls = apbct_sfw_update__get_multifiles_of_type(array('type' => 'personal', 'do_return_urls' => true));
    if ( !empty($get_multifiles_personal_urls['error']) ) {
        $output_error = $get_multifiles_personal_urls['error'];
    }

    $file_urls = array_merge($get_multifiles_common_urls, $get_multifiles_personal_urls);

    if ( empty($file_urls) ) {
        $output_error = 'SFW_UPDATE_FILES_URLS_IS_EMPTY';
    }

    if ( empty($output_error) ) {
        $apbct->fw_stats['firewall_update_percent'] = round(100 / count($file_urls), 2);
        $apbct->save('fw_stats');

        return array(
            'next_stage' => array(
                'name'    => 'apbct_sfw_update__download_files',
                'args'    => $file_urls,
                'is_last' => '0'
            )
        );
    } else {
        return array('error' => $output_error);
    }
}

/**
 * Queue stage. Get multifiles url for next downloading. Can return urls directly if flag is set instead of next stage call.
 * @param array $params 'type' -> type of SFW load, 'do_return_urls' -> return urls if true, array call for next stage otherwise(default false)
 * @return array|array[]|string[]
 */
function apbct_sfw_update__get_multifiles_of_type(array $params)
{
    global $apbct;
    //check key
    if ( ! $apbct->data['key_is_ok'] ) {
        return array('error' => 'Get multifiles: KEY_IS_NOT_VALID');
    }

    //chek type
    if ( !isset($params['type']) || !in_array($params['type'], array('common', 'personal')) ) {
        return array('error' => 'Get multifiles: bad params');
    } else {
        $type = $params['type'];
    }

    //check needs return urls instead of next stage
    $do_return_urls = TT::getArrayValueAsBool($params, 'do_return_urls');

    $output_error = array();
    // getting urls
    try {
        $direction_files = SFWUpdateHelper::getDirectionUrlsOfType($type);
        $direction_url_id = TT::getArrayValueAsString($direction_files, 'url_id');
        if ( $type === 'personal' ) {
            $apbct->fw_stats['personal_lists_url_id'] = $direction_url_id;
        } else {
            $apbct->fw_stats['common_lists_url_id'] = $direction_url_id;
        }
    } catch (\Exception $e) {
        $output_error[] = $e->getMessage();
    }

    if ( empty($output_error) ) {
        if ( !empty($direction_files['files_urls']) ) {
            $urls = array();
            foreach ( $direction_files['files_urls'] as $value ) {
                $urls[] = $value[0];
            }

            $apbct->fw_stats['firewall_update_percent'] = round(100 / count($urls), 2);
            $apbct->save('fw_stats');

            // return urls directly on do load all multifiles, otherwise proceed to next queue stage
            if ( !$do_return_urls) {
                return array(
                    'next_stage' => array(
                        'name'    => 'apbct_sfw_update__download_files',
                        'args'    => $urls,
                        'is_last' => '0'
                    )
                );
            } else {
                return $urls;
            }
        } else {
            return array('error' => 'SFW_UPDATE_FILES_URLS_IS_EMPTY');
        }
    } else {
        return array('error' => $output_error);
    }
}

/**
 * Queue stage. Do load multifiles with networks on their urls.
 * @param $urls
 * @return array|array[]|bool|string|string[]
 */
function apbct_sfw_update__download_files($urls, $direct_update = false)
{
    global $apbct;

    sleep(3);

    if ( ! is_writable($apbct->fw_stats['updating_folder']) ) {
        return array('error' => 'SFW update folder is not writable.');
    }

    //Reset keys
    $urls          = array_values(array_unique($urls));

    $results = array();
    $batch_size = 10;
    $total_urls = count($urls);
    $batches = ceil($total_urls / $batch_size);

    for ($i = 0; $i < $batches; $i++) {
        $batch_urls = array_slice($urls, $i * $batch_size, $batch_size);
        if (!empty($batch_urls)) {
            $http_results = Helper::httpMultiRequest($batch_urls, $apbct->fw_stats['updating_folder']);
            if (is_array($http_results)) {
                $results = array_merge($results, $http_results);
            }
            // to handle case if we request only one url, then Helper::httpMultiRequest returns string 'success' instead of array
            if (count($batch_urls) === 1 && $http_results === 'success') {
                $results = array_merge($results, $batch_urls);
            }
        }
    }

    $results       = TT::toArray($results);
    $count_urls    = count($urls);
    $count_results = count($results);

    if ( empty($results['error']) && ($count_urls === $count_results) ) {
        if ( $direct_update ) {
            return true;
        }
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

/**
 * Queue stage. Create SFW origin tables to make sure they are exists.
 * @return array[]|bool|string[]
 */
function apbct_sfw_update__create_tables($direct_update = false, $return_new_tables_names = false)
{
    global $apbct, $wpdb;
    // Preparing database infrastructure

    // Creating SFW tables to make sure that they are exists
    $db_tables_creator = new DbTablesCreator();

    //common table
    $common_table_name = $wpdb->base_prefix . Schema::getSchemaTablePrefix() . 'sfw';
    $db_tables_creator->createTable($common_table_name);
    $apbct->data['sfw_common_table_name'] = $common_table_name;
    //personal table
    $table_name_personal = $apbct->db_prefix . Schema::getSchemaTablePrefix() . 'sfw_personal';
    $db_tables_creator->createTable($table_name_personal);
    $apbct->data['sfw_personal_table_name'] = $table_name_personal;
    //ua table
    $personal_ua_bl_table_name = $apbct->db_prefix . Schema::getSchemaTablePrefix() . 'ua_bl';
    $db_tables_creator->createTable($personal_ua_bl_table_name);
    $apbct->data['sfw_personal_ua_bl_table_name'] = $personal_ua_bl_table_name;

    $apbct->saveData();

    if ( $return_new_tables_names ) {
        return array(
            'sfw_common_table_name' => $common_table_name,
            'sfw_personal_table_name' => $table_name_personal,
            'sfw_personal_ua_bl_table_name' => $personal_ua_bl_table_name,
            );
    }

    return $direct_update ? true : array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__create_temp_tables',
        )
    );
}

/**
 * Queue stage. Create SFW temporary tables. They will replace origin tables after update.
 * @return array[]|bool
 */
function apbct_sfw_update__create_temp_tables($direct_update = false)
{
    global $apbct;

    // Create common table
    $result = SFW::createTempTables(DB::getInstance(), $apbct->data['sfw_common_table_name']);
    if ( ! empty($result['error']) ) {
        return $result;
    }
    // Create personal table
    $result = SFW::createTempTables(DB::getInstance(), $apbct->data['sfw_personal_table_name']);
    if ( ! empty($result['error']) ) {
        return $result;
    }

    $result__clear_db = AntiCrawler::clearDataTable(
        \Cleantalk\ApbctWP\DB::getInstance(),
        APBCT_TBL_AC_UA_BL
    );

    if ( ! empty($result__clear_db['error']) ) {
        return $result__clear_db['error'];
    }

    return $direct_update ? true : array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__process_files',
        )
    );
}

/**
 * Queue stage. Process all of downloaded multifiles that collected to the update folder.
 * @return array[]|int|string[]|null
 */
function apbct_sfw_update__process_files()
{
    global $apbct;

    // get list of files in the upd folder
    $files = glob($apbct->fw_stats['updating_folder'] . '/*csv.gz');
    $files = array_filter($files, static function ($element) {
        return strpos($element, 'list') !== false;
    });

    if ( count($files) ) {
        reset($files);
        $concrete_file = current($files);

        //get direction on how the file should be processed (common/personal)
        if (
            // we should have a personal list id (hash) to make sure the file belongs to private lists
            !empty($apbct->fw_stats['personal_lists_url_id'])
            && strpos($concrete_file, $apbct->fw_stats['personal_lists_url_id']) !== false
        ) {
            $direction = 'personal';
        } elseif (
            // we should have a common list id (hash) to make sure the file belongs to common lists
            !empty($apbct->fw_stats['common_lists_url_id'])
            && strpos($concrete_file, $apbct->fw_stats['common_lists_url_id']) !== false ) {
            $direction = 'common';
        } else {
            // no id found in fw_stats or file namse does not contain any of them
            return array('error' => 'SFW_DIRECTION_FAILED');
        }

        // do proceed file with networks itself
        if ( strpos($concrete_file, 'bl_list') !== false ) {
            //$result = apbct_sfw_update__process_file($concrete_file, $direction);
            $result = SFWUpdateHelper::processFile($concrete_file, $direction);
        }

        // do proceed ua file
        if ( strpos($concrete_file, 'ua_list') !== false ) {
            $result = SFWUpdateHelper::processUA($concrete_file);
        }

        // do proceed checking file
        if ( strpos($concrete_file, 'ck_list') !== false ) {
            $result = SFWUpdateHelper::processCK($concrete_file, $direction);
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

/**
 * Queue stage. Process hardcoded exclusion to the SFW temp table.
 * @return array[]|string[]|bool
 */
function apbct_sfw_update__process_exclusions($direct_update = false)
{
    global $apbct;

    $result = SFW::updateWriteToDbExclusions(
        DB::getInstance(),
        APBCT_TBL_FIREWALL_DATA_PERSONAL . '_temp'
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
        $apbct->fw_stats['expected_networks_count_personal'] += $result;
        $apbct->save('fw_stats');
    }

    return $direct_update ? true : array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__end_of_update__renaming_tables',
            'accepted_tries' => 1
        )
    );
}

/**
 * Queue stage. Delete origin tables and rename temporary tables.
 * @return array|array[]|string[]|bool
 */
function apbct_sfw_update__end_of_update__renaming_tables($direct_update = false)
{
    global $apbct;

    $check = SFWUpdateHelper::checkTablesIntegrityBeforeRenaming($apbct->data['sfw_load_type']);

    if ( !empty($check['error']) ) {
        return array('error' => $check['error']);
    }

    $apbct->fw_stats['update_mode'] = 1;
    $apbct->save('fw_stats');
    usleep(10000);

    // REMOVE AND RENAME
    try {
        SFWUpdateHelper::removeAndRenameSfwTables($apbct->data['sfw_load_type']);
    } catch (\Exception $e) {
        $apbct->fw_stats['update_mode'] = 0;
        $apbct->save('fw_stats');
        return array('error' => $e->getMessage());
    }

    $apbct->fw_stats['update_mode'] = 0;
    $apbct->save('fw_stats');

    return $direct_update ? true : array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__end_of_update__checking_data',
            'accepted_tries' => 1
        )
    );
}

/**
 * Queue stage. Check data after all the SFW update actions.
 * @return array|array[]|string[]|bool
 */
function apbct_sfw_update__end_of_update__checking_data($direct_update = false)
{
    global $apbct, $wpdb;

    try {
        SFWUpdateHelper::checkTablesIntegrityAfterRenaming($apbct->data['sfw_load_type']);
    } catch (\Exception $e) {
        return array('error' => $e->getMessage());
    }

    $apbct->stats['sfw']['entries'] = $wpdb->get_var('SELECT COUNT(*) FROM ' . $apbct->data['sfw_common_table_name']);
    $apbct->stats['sfw']['entries_personal'] = $wpdb->get_var('SELECT COUNT(*) FROM ' . $apbct->data['sfw_personal_table_name']);
    $apbct->save('stats');

    /**
     * Checking the integrity of the sfw database update
     */
    if ( in_array($apbct->data['sfw_load_type'], array('all','common'))
        && isset($apbct->stats['sfw']['entries'])
        && ($apbct->stats['sfw']['entries'] != $apbct->fw_stats['expected_networks_count'] ) ) {
        return array(
            'error' =>
                'The discrepancy between the amount of data received for the update and in the final table: '
                . $apbct->data['sfw_common_table_name']
                . '. RECEIVED: ' . $apbct->fw_stats['expected_networks_count']
                . '. ADDED: ' . $apbct->stats['sfw']['entries']
        );
    }

    if ( in_array($apbct->data['sfw_load_type'], array('all','personal'))
        && isset($apbct->stats['sfw']['entries_personal'])
        && ( $apbct->stats['sfw']['entries_personal'] != $apbct->fw_stats['expected_networks_count_personal'] ) ) {
        return array(
            'error' =>
                'The discrepancy between the amount of data received for the update and in the final table: '
                . $apbct->data['sfw_personal_table_name']
                . '. RECEIVED: ' . $apbct->fw_stats['expected_networks_count_personal']
                . '. ADDED: ' . $apbct->stats['sfw']['entries_personal']
        );
    }

    return $direct_update ? true : array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__end_of_update__updating_stats',
            'accepted_tries' => 1
        )
    );
}

/**
 * Queue stage. Update stats.
 * @param $direct_update
 * @return array[]
 */
function apbct_sfw_update__end_of_update__updating_stats($direct_update = false)
{
    global $apbct;

    $is_first_updating = ! $apbct->stats['sfw']['last_update_time'];
    $apbct->stats['sfw']['last_update_time'] = time();
    $apbct->stats['sfw']['last_update_way']  = $direct_update ? 'Direct update' : 'Queue update';
    $apbct->save('stats');

    return array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__end_of_update',
            'accepted_tries' => 1,
            'args' => $is_first_updating
        )
    );
}

/**
 * Final queue stage. Reset all misc data and set new cron.
 * @param $is_first_updating
 * @return true
 */
function apbct_sfw_update__end_of_update($is_first_updating = false)
{
    global $apbct;

    // Delete update errors
    $apbct->errorDelete('sfw_update', true);

    // Running sfw update once again in 12 min if entries is < 4000
    if ( $is_first_updating &&
        $apbct->stats['sfw']['entries'] < 4000
    ) {
        wp_schedule_single_event(time() + 720, 'apbct_sfw_update__init');
    }

    $cron = new Cron();
    $cron->updateTask('sfw_update', 'apbct_sfw_update__init', $apbct->stats['sfw']['update_period']);
    $cron->removeTask('sfw_update_checker');

    SFWUpdateHelper::removeUpdFolder($apbct->fw_stats['updating_folder']);

    // Reset all FW stats
    $apbct->sfw_update_sentinel->clearSentinelData();
    $apbct->fw_stats['firewall_update_percent'] = 0;
    $apbct->fw_stats['firewall_updating_id']    = null;
    $apbct->fw_stats['expected_networks_count'] = false;
    $apbct->fw_stats['expected_ua_count'] = false;
    $apbct->save('fw_stats');

    return true;
}

/**
 * Cron task handler.
 * @return array|bool|int|string|string[]
 */
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


/**
 * * * * * * SFW COMMON ACTIONS * * * * * *
 */

function apbct_sfw__clear()
{
    global $apbct, $wpdb;

    $wpdb->query('DELETE FROM ' . APBCT_TBL_FIREWALL_DATA . ';');

    $apbct->stats['sfw']['entries'] = 0;
    $apbct->save('stats');
}

/**
 * Send SFW logs to the cloud.
 * @param $api_key
 * @return array|bool|int[]|string[]
 */
function ct_sfw_send_logs($api_key = '')
{
    global $apbct;

    $api_key = ! empty($apbct->api_key) ? $apbct->api_key : $api_key;

    if (
        time() - $apbct->stats['sfw']['sending_logs__timestamp'] < 180 ||
        empty($api_key) ||
        $apbct->settings['sfw__enabled'] != 1 ||
        apbct__is_hosting_license()
    ) {
        return true;
    }

    $apbct->stats['sfw']['sending_logs__timestamp'] = time();
    $apbct->save('stats');

    $result = SFW::sendLog(
        DB::getInstance(),
        APBCT_TBL_FIREWALL_LOG,
        $api_key
    );

    if ( empty($result['error']) ) {
        $apbct->stats['sfw']['last_send_time']   = time();
        $apbct->stats['sfw']['last_send_amount'] = TT::getArrayValueAsInt($result, 'rows');
        $apbct->errorDelete('sfw_send_logs', true);
        $apbct->save('stats');
    }

    return $result;
}

/**
 * Handle SFW private_records remote call.
 * @param $action
 * @param null|string $test_data
 * @return string JSON string of results
 * @throws Exception
 */
function apbct_sfw_private_records_handler($action, $test_data = null)
{

    $error = 'sfw_private_records_handler: ';

    if ( !empty($action) && (in_array($action, array('add', 'delete'))) ) {
        $metadata = !empty($test_data) ? TT::toString($test_data) : TT::toString(Post::get('metadata'));

        if ( !empty($metadata) ) {
            $metadata = json_decode(stripslashes($metadata), true);
            if ( $metadata === 'NULL' || $metadata === null ) {
                throw new InvalidArgumentException($error . 'metadata JSON decoding failed');
            }
        } else {
            throw new InvalidArgumentException($error . 'metadata is empty');
        }

        foreach ( $metadata as $_key => &$row ) {
            $row = explode(',', $row);
            //do this to get info more obvious
            $metadata_assoc_array = array(
                'network' => TT::getArrayValueAsInt($row, 0),
                'mask' => TT::getArrayValueAsInt($row, 1),
                'status' => isset($row[2]) ? TT::toInt($row[2]) : null
            );
            //validate
            $validation_error = '';
            if ( $metadata_assoc_array['network'] === 0
                || $metadata_assoc_array['network'] > 4294967295
            ) {
                $validation_error = 'metadata validate failed on "network" value';
            }
            if ( $metadata_assoc_array['mask'] === 0
                || $metadata_assoc_array['mask'] > 4294967295
            ) {
                $validation_error = 'metadata validate failed on "mask" value';
            }
            //only for adding
            if ( $action === 'add' ) {
                if ( $metadata_assoc_array['status'] !== 1 && $metadata_assoc_array['status'] !== 0 ) {
                    $validation_error = 'metadata validate failed on "status" value';
                }
            }

            if ( !empty($validation_error) ) {
                throw new InvalidArgumentException($error . $validation_error);
            }
            $row = $metadata_assoc_array;
        }
        unset($row);

        //method selection
        if ( $action === 'add' ) {
            $handler_output = SFW::privateRecordsAdd(
                DB::getInstance(),
                SFW::getSFWTablesNames()['sfw_personal_table_name'],
                $metadata
            );
        } elseif ( $action === 'delete' ) {
            $handler_output = SFW::privateRecordsDelete(
                DB::getInstance(),
                SFW::getSFWTablesNames()['sfw_personal_table_name'],
                $metadata
            );
        } else {
            $error .= 'unknown action name: ' . $action;
            throw new InvalidArgumentException($error);
        }
    } else {
        throw new InvalidArgumentException($error . 'empty action name');
    }

    return json_encode(array('OK' => $handler_output));
}

/**
 * Cron task handler. Clear anti-flood table.
 * @return void
 */
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

        // Clear table APBCT_TBL_AC_LOG once a day
        $anticrawler = new AntiCrawler(
            APBCT_TBL_FIREWALL_LOG,
            APBCT_TBL_AC_LOG
        );
        $anticrawler->setDb(DB::getInstance());
        $anticrawler->clearTable();
        unset($anticrawler);
    }
}

/**
 * * * * * * REMOTE CALL ACTIONS * * * * * *
 */

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
        $plugin = Get::get('plugin') ? Get::get('plugin') : '';
    }

    if ( !empty($plugin) ) {
        $plugin = TT::toString($plugin);
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

                    $download_link = is_object($result) ? $result->download_link : false;

                    if ($download_link) {
                        $installer->install($download_link);
                    }

                    if ( $download_link && $installer->apbct_result === 'OK' ) {
                        die('OK');
                    } else {
                        die('FAIL ' . json_encode(array('error' => $installer->apbct_result)));
                    }
                } else {
                    die(
                        'FAIL ' . json_encode(array(
                            'error'   => 'FAIL_TO_GET_LATEST_VERSION',
                            'details' => $result instanceof WP_Error ? $result->get_error_message() : '',
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
        $plugin = Get::get('plugin') ? TT::toString(Get::get('plugin')) : null;
    }

    if ( $plugin ) {
        if ( preg_match('@[a-zA-Z-\d]+[\\\/][a-zA-Z-\d]+\.php@', $plugin) ) {
            require_once(ABSPATH . '/wp-admin/includes/plugin.php');

            $result = activate_plugins($plugin);

            $result_array = array('success' => true);
            $error_msg = '';

            if (!$result || is_wp_error($result)) {
                if ($result instanceof WP_Error) {
                    $error_msg = ' ' . $result->get_error_message();
                }
                $result_array = array(
                    'error'   => 'FAIL_TO_ACTIVATE',
                    'details' => $error_msg
                );
            }
            return $result_array;
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
        $plugin = Get::get('plugin') ? TT::toString(Get::get('plugin')) : null;
    }

    if ( $plugin ) {
        // Switching complete deactivation for security
        if ( $plugin === 'security-malware-firewall/security-malware-firewall.php' && ! empty(Get::get('misc__complete_deactivation')) ) {
            $spbc_settings                                = TT::toArray(get_option('spbc_settings'));
            $spbc_settings['misc__complete_deactivation'] = TT::toInt(Get::get('misc__complete_deactivation'));
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
        $plugin = Get::get('plugin') ? TT::toString(Get::get('plugin')) : null;
    }

    if ( $plugin ) {
        // Switching complete deactivation for security
        if ( $plugin === 'security-malware-firewall/security-malware-firewall.php' && ! empty(Get::get('misc__complete_deactivation')) ) {
            $spbc_settings                                = TT::toArray(get_option('spbc_settings'));
            $spbc_settings['misc__complete_deactivation'] = TT::toInt(Get::get('misc__complete_deactivation'));
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
            $die_string = 'OK';
            $error_msg = '';

            if (!$result || is_wp_error($result)) {
                if ($result instanceof WP_Error) {
                    $error_msg = ' ' . $result->get_error_message();
                }
                $die_string = 'FAIL ' . json_encode(array(
                        'error'   => 'PLUGIN_STILL_EXISTS',
                        'details' => $error_msg
                    ));
            }
            die($die_string);
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

    foreach ( $apbct->default_settings as $setting => $def_value ) {
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

            if ( $key && preg_match('/^[a-z\d]{3,30}$/', $key) ) {
                $result = API::methodNoticePaidTill(
                    $key,
                    preg_replace('/http[s]?:\/\//', '', get_option('home'), 1), // Site URL
                    'security'
                );

                if ( empty($result['error']) ) {
                    if ( TT::getArrayValueAsInt($result, 'valid') === 1 ) {
                        // Set account params
                        $data                     = get_option('spbc_data', array());
                        $data['user_token']       = TT::getArrayValueAsInt($result, 'user_token');
                        $data['notice_show']      = TT::getArrayValueAsInt($result, 'show_notice');
                        $data['notice_renew']     = TT::getArrayValueAsInt($result, 'renew');
                        $data['notice_trial']     = TT::getArrayValueAsInt($result, 'trial');
                        $data['service_id']       = TT::getArrayValueAsInt($result, 'service_id');
                        $data['user_id']          = TT::getArrayValueAsInt($result, 'user_id');
                        $data['moderate']         = TT::getArrayValueAsInt($result, 'moderate');
                        $data['license_trial']    = TT::getArrayValueAsInt($result, 'license_trial');
                        $data['account_name_ob']  = TT::getArrayValueAsString($result, 'account_name_ob');
                        $data['key_is_ok']        = true;
                        update_option('spbc_data', $data);

                        // Set Access key
                        $settings             = TT::toArray(get_option('spbc_settings', array()));
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

    echo \Cleantalk\ApbctWP\Escape::escHtml($result . ' ' . json_encode($response));

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
                $country_data       = TT::toArray(API::methodIpInfo($ip));
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
        ! empty($apbct->headers_sent)              // Headers sent
    ) {
        return false;
    }

    if ( $apbct->settings['misc__store_urls'] && empty($apbct->flags__url_stored) && ! headers_sent() ) {
        // URLs HISTORY
        // Get current url
        $current_url = TT::toString(Server::get('HTTP_HOST'))
            . TT::toString(Server::get('REQUEST_URI'));
        $current_url = $current_url ? substr($current_url, 0, 128) : 'UNKNOWN';

        // Get already stored URLs
        $urls_json = TT::toString(RequestParameters::getCommonStorage('apbct_urls'));
        $urls = !empty($urls_json) ? json_decode($urls_json, true) : array();
        $urls = ! is_array($urls) ? [] : $urls;

        $urls[$current_url][] = time();

        // Saving only latest 5 visit for each of 5 last urls
        $urls_count_to_keep = 5;
        $visits_to_keep = 5;

        //Rotating.
        $urls[$current_url] = count($urls[$current_url]) > $visits_to_keep
            ? array_slice(
                $urls[$current_url],
                1,
                $visits_to_keep
            )
            : $urls[$current_url];
        $urls               = count($urls) > $urls_count_to_keep
            ? array_slice($urls, 1, $urls_count_to_keep)
            : $urls;

        // Saving
        RequestParameters::setCommonStorage('apbct_urls', json_encode($urls, JSON_UNESCAPED_SLASHES));

        // SITE-REFERER
        // Get current site-referer
        $new_site_referer = Server::getString('HTTP_REFERER');

        if (empty($new_site_referer)) {
            //do not overwrite existing param - this case is when the new url is entered via address bar
            if (empty(RequestParameters::get('apbct_site_referer', true))) {
                //we should keep existing param anyway to make sure the site referer is not lost
                RequestParameters::set('apbct_site_referer', '0', true);
            }
        } else {
            $is_valid_new_url  = parse_url($new_site_referer, PHP_URL_HOST) !== null;
            $is_not_like_host = $is_valid_new_url && parse_url($new_site_referer, PHP_URL_HOST) !== Server::getString('HTTP_HOST');
            if ($is_not_like_host) {
                RequestParameters::set('apbct_site_referer', $new_site_referer, true);
            }
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
        ! empty($apbct->flags__cookies_setuped) || // Cookies already set
        ! empty($apbct->headers_sent) ||             // Headers sent
        Post::get('fusion_login_box') // Avada Fusion registration form exclusion
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
    if ( empty($_POST) ) {
        $apbct_timestamp = time();
        RequestParameters::set('apbct_timestamp', (string)$apbct_timestamp, true);
        $cookie_test_value['cookies_names'][] = 'apbct_timestamp';
        $cookie_test_value['check_value']     .= $apbct_timestamp;
    }

    // Landing time
    $site_landing_timestamp = RequestParameters::get('apbct_site_landing_ts', true);

    if ( ! $site_landing_timestamp ) {
        $site_landing_timestamp = time();
        RequestParameters::set('apbct_site_landing_ts', TT::toString($site_landing_timestamp), true);
    }

    if ($apbct->data['cookies_type'] === 'native') {
        $http_referrer = TT::toString(Server::get('HTTP_REFERER'));
        // Previous referer
        if ( $http_referrer ) {
            Cookie::set('apbct_prev_referer', $http_referrer, 0, '/', $domain, null, true, 'Lax', true);
            $cookie_test_value['cookies_names'][] = 'apbct_prev_referer';
            $cookie_test_value['check_value']     .= $http_referrer;
        }
    }

    // Cookies test
    $cookie_test_value['check_value'] = md5($cookie_test_value['check_value']);
    if ( $apbct->data['cookies_type'] !== 'alternative' ) {
        Cookie::set('apbct_cookies_test', urlencode(json_encode($cookie_test_value)), 0, '/', $domain, null, true);
    }

    $apbct->flags__cookies_setuped = true;

    return $apbct->flags__cookies_setuped;
}

/**
 * Store page hits. Only for native cookies mode.
 * @return void
 */
function apbct_store__page_hits()
{
    global $apbct;
    if (
        ! empty($apbct->headers_sent)              // Headers sent
    ) {
        return;
    }
    if ( $apbct->data['cookies_type'] === 'native' && empty($apbct->page_hits_set) ) {
        // Page hits
        // Get
        $page_hits = TT::toInt(RequestParameters::get('apbct_page_hits', true));

        // Set / Increase
        // todo if cookies disabled there is no way to keep this data without DB:( always will be 1
        $page_hits = $page_hits ? $page_hits + 1 : 1;

        RequestParameters::set('apbct_page_hits', TT::toString($page_hits), true);

        $apbct->page_hits_set = true;
    }
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

    if ( $apbct->data['cookies_type'] !== 'native' || Cookie::$force_alt_cookies_global) {
        return 1;
    }

    if ( Cookie::get('apbct_cookies_test') ) {
        $apbct_cookies_test = TT::toString(Cookie::get('apbct_cookies_test'));
        $cookie_test = json_decode(urldecode($apbct_cookies_test), true);

        if ( ! is_array($cookie_test) ) {
            return 0;
        }

        $check_string = $apbct->api_key;
        // generate value
        $cookie_names = TT::getArrayValueAsArray($cookie_test, 'cookies_names');
        foreach ( $cookie_names as $cookie_name ) {
            $check_string .= Cookie::get($cookie_name);
        }
        // check generated value with current cookie
        $check_value = TT::getArrayValueAsString($cookie_test, 'check_value');
        if ( $check_value === md5($check_string) ) {
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
    $apbct_timestamp = (int) RequestParameters::get('apbct_timestamp', true);

    return apbct_cookies_test() === 1 && $apbct_timestamp !== 0 ? time() - $apbct_timestamp : null;
}

/**
 * Inner function - Account status check. Scheduled in 1800 seconds for default!
 * @param $api_key
 * @param $process_errors
 * @return bool
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
        $apbct->data['notice_show']         = TT::getArrayValueAsInt($result, 'show_notice', 0);
        $apbct->data['notice_renew']        = TT::getArrayValueAsInt($result, 'renew', 0);
        $apbct->data['notice_trial']        = TT::getArrayValueAsInt($result, 'trial', 0);
        $apbct->data['notice_review']       = TT::getArrayValueAsInt($result, 'show_review', 0);

        if ($apbct->data['notice_show']) {
            $notice_banners = API::getNoticeBanners($api_key);

            if (isset($notice_banners['operation_status'], $notice_banners['banners']) && $notice_banners['operation_status'] === 'SUCCESS') {
                if (isset($notice_banners['banners']['TRIAL']['level'])) {
                    $apbct->data['notice_trial_level'] = strtolower($notice_banners['banners']['TRIAL']['level']);
                }
                if (isset($notice_banners['banners']['RENEW']['level'])) {
                    $apbct->data['notice_renew_level'] = strtolower($notice_banners['banners']['RENEW']['level']);
                }
                if (isset($notice_banners['banners']['REVIEW']['level'])) {
                    $apbct->data['notice_review_level'] = strtolower($notice_banners['banners']['REVIEW']['level']);
                }
            }
        }

        // Other
        $apbct->data['service_id']          = TT::getArrayValueAsInt($result, 'service_id', 0);
        $apbct->data['user_id']             = TT::getArrayValueAsInt($result, 'user_id', 0);
        $apbct->data['valid']               = TT::getArrayValueAsInt($result, 'valid', 0);
        $apbct->data['moderate']            = TT::getArrayValueAsInt($result, 'moderate', 0);
        $apbct->data['ip_license']          = TT::getArrayValueAsInt($result, 'ip_license', 0);
        $apbct->data['spam_count']          = TT::getArrayValueAsInt($result, 'spam_count', 0);
        $apbct->data['user_token']          = TT::getArrayValueAsString($result, 'user_token', '');
        $apbct->data['license_trial']       = TT::getArrayValueAsInt($result, 'license_trial', 0);
        $apbct->data['account_name_ob']     = TT::getArrayValueAsString($result, 'account_name_ob', '');
        $apbct->data['moderate_ip']         = isset($result['moderate_ip'], $result['ip_license']) ?
            TT::getArrayValueAsInt($result, 'moderate_ip', 0)
            : 0;

        //todo:temporary solution for description, until we found the way to transfer this from cloud
        if (defined('APBCT_WHITELABEL_PLUGIN_DESCRIPTION')) {
            /** @psalm-suppress PossiblyInvalidArrayAssignment */
            $result['wl_antispam_description'] = APBCT_WHITELABEL_PLUGIN_DESCRIPTION;
        }

        //todo:temporary solution for FAQ
        if (defined('APBCT_WHITELABEL_FAQ_LINK')) {
            /** @psalm-suppress PossiblyInvalidArrayAssignment */
            $result['wl_faq_url'] = APBCT_WHITELABEL_FAQ_LINK;
        }

        if ( isset($result['wl_status']) && $result['wl_status'] === 'ON' ) {
            $apbct->data['wl_mode_enabled'] = true;
            $apbct->data['wl_brandname']     = isset($result['wl_brandname'])
                ? Sanitize::cleanTextField($result['wl_brandname'])
                : $apbct->default_data['wl_brandname'];
            $apbct->data['wl_url']           = isset($result['wl_url'])
                ? Sanitize::cleanUrl($result['wl_url'])
                : $apbct->default_data['wl_url'];
            $apbct->data['wl_support_url']   = isset($result['wl_support_url'])
                ? Sanitize::cleanUrl($result['wl_support_url'])
                : $apbct->default_data['wl_support_url'];
            $apbct->data['wl_support_faq']   = isset($result['wl_faq_url'])
                ? Sanitize::cleanUrl($result['wl_faq_url'])
                //important, if missed get this from already set wl_support_url for now
                : $apbct->data['wl_support_url'];
            $apbct->data['wl_support_email'] = isset($result['wl_support_email'])
                ? Sanitize::cleanEmail($result['wl_support_email'])
                : $apbct->default_data['wl_support_email'];
            $plugin_data_wl = get_file_data(__FILE__, array('Description' => 'Description'));
            $plugin_data_wl = is_array($plugin_data_wl) && isset($plugin_data_wl['Description'])
                ? $plugin_data_wl['Description']
                : 'No description provided';
            $apbct->data['wl_antispam_description']     = isset($result['wl_antispam_description'])
                ? Sanitize::cleanTextField($result['wl_antispam_description'])
                : $plugin_data_wl;
        } else {
            $apbct->data['wl_mode_enabled'] = false;
            $apbct->data['wl_brandname']     = $apbct->default_data['wl_brandname'];
            $apbct->data['wl_url']           = $apbct->default_data['wl_url'];
            $apbct->data['wl_support_faq']   = $apbct->default_data['wl_support_url'];
            $apbct->data['wl_support_url']   = $apbct->default_data['wl_support_url'];
            $apbct->data['wl_support_email'] = $apbct->default_data['wl_support_email'];
        }

        $cron = new Cron();
        $cron->updateTask('check_account_status', 'ct_account_status_check', 86400);

        $apbct->errorDelete('account_check', true);
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

    $apbct->saveData();

    return $result;
}

/**
 * Send connection reports cron wrapper.
 * If setting misc__send_connection_reports is disabled there will no reports sen on cron.
 */
function ct_cron_send_connection_report_email()
{
    global $apbct;
    if (isset($apbct->settings['misc__send_connection_reports']) && $apbct->settings['misc__send_connection_reports'] == 1) {
        $apbct->getConnectionReports()->sendUnsentReports(true);
    }
}

/**
 * Send js errors reports cron wrapper.
 * If setting misc__send_connection_reports is disabled there will no reports sen on cron.
 */
function ct_cron_send_js_error_report_email()
{
    global $apbct;
    if (isset($apbct->settings['misc__send_connection_reports']) && $apbct->settings['misc__send_connection_reports'] == 1) {
        $apbct->getJsErrorsReport()->sendEmail(true);
    }
}

/**
 * Cron job handler
 * Clear old alt-cookies/no-cookies from the database
 *
 * @return void
 */
function apbct_cron_clear_old_session_data()
{
    global $wpdb, $apbct;

    $query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like(APBCT_TBL_SESSIONS));
    $session_table_exists = $wpdb->get_var($query);

    if (empty($session_table_exists)) {
        return;
    }

    $res = \Cleantalk\ApbctWP\Variables\AltSessions::cleanFromOld();

    $session_clear_log = get_option('cleantalk_sessions_clear_log', []);
    $session_clear_log[] = array(
        'time' => time(),
        'result' => $res,
    );
    $session_clear_log = array_slice((array)$session_clear_log, -4, 4, true);
    update_option('cleantalk_sessions_clear_log', $session_clear_log);

    $ct_cron = new Cron();
    if (\Cleantalk\ApbctWP\Variables\AltSessions::checkHasUndeletedOldSessions()) {
        $ct_cron->updateTask('clear_old_session_data', 'apbct_cron_clear_old_session_data', 60, time() + 60);
    } else {
        $ct_cron->updateTask('clear_old_session_data', 'apbct_cron_clear_old_session_data', 86400);
    }
}

/**
 * @param $blog_id
 * @param $_drop
 *
 * @return void
 * @psalm-suppress UnusedParam
 */
function apbct_sfw__delete_tables($blog_id)
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

    if ( !is_object($user) || empty($user->ID) ) {
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

    $requests_counters = array_keys($apbct->stats['requests']);
    if ( !empty($requests_counters) ) {
        // Delete old stats
        $min_request_count_key = min($requests_counters);
        if ( $min_request_count_key < time() - (86400 * 7) ) {
            unset($apbct->stats['requests'][$min_request_count_key]);
        }

        // Create new if newest older than 1 day
        $max_request_count_key = max($requests_counters);
        if ( $max_request_count_key < time() - (86400 * 1) ) {
            $apbct->stats['requests'][time()] = array('amount' => 0, 'average_time' => 0);
        }
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

function apbct_sfw_update_sentinel__run_watchdog()
{
    global $apbct;
    $apbct->sfw_update_sentinel->runWatchDog();
}
