<?php

use Cleantalk\ApbctWP\Activator;
use Cleantalk\ApbctWP\AdminNotices;
use Cleantalk\ApbctWP\Antispam\EmailEncoder;
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
use Cleantalk\Common\TT;
use Cleantalk\Common\DNS;
use Cleantalk\Common\Firewall;
use Cleantalk\Common\Schema;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Request;
use Cleantalk\ApbctWP\Variables\Server;

global $apbct, $wpdb, $pagenow;

$cleantalk_executed = false;

/**
 * Define common const.
 */
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
define('APBCT_DEBUG', 'cleantalk_debug');            // Option name with a debug data. Empty by default.
define('APBCT_JS_ERRORS', 'cleantalk_js_errors');            // Option name with js errors. Empty by default.


/**
 * Define service const.
 */
define('APBCT_REMOTE_CALL_SLEEP', 5); // Minimum time between remote call
define('APBCT_WPMS', (is_multisite() ? true : false)); // WordPress Multisite - if WMPS is enabled
define('APBCT_LANG_REL_PATH', 'cleantalk-spam-protect/i18n');

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
const APBCT_MODERATE_URL = 'https://moderate.cleantalk.org'; // Api URL

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
$apbct = new State('cleantalk', array('settings', 'data', 'debug', 'errors', 'remote_calls', 'stats', 'fw_stats'));
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
add_action('wp_ajax_apbct_get_pixel_url', 'apbct_get_pixel_url__ajax');

// Checking email before POST
add_action('wp_ajax_nopriv_apbct_email_check_before_post', 'apbct_email_check_before_post');

// Checking email exist POST
add_action('wp_ajax_nopriv_apbct_email_check_exist_post', 'apbct_email_check_exist_post');

// Force ajax set important parameters (apbct_timestamp etc)
add_action('wp_ajax_nopriv_apbct_set_important_parameters', 'apbct_cookie');
add_action('wp_ajax_apbct_set_important_parameters', 'apbct_cookie');

// Email Encoder ajax handlers
EmailEncoder::getInstance()->registerAjaxRoute();

// Database prefix
global $wpdb, $wp_version;
$apbct->db_prefix = ! APBCT_WPMS || $apbct->allow_custom_key || $apbct->white_label ? $wpdb->prefix : $wpdb->base_prefix;
$apbct->db_prefix = ! $apbct->white_label && defined('CLEANTALK_ACCESS_KEY') ? $wpdb->base_prefix : $wpdb->prefix;

/** @todo HARDCODE FIX */
if ( $apbct->plugin_version === '1.0.0' ) {
    $apbct->plugin_version = '5.100';
}

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
) {
    if ( $apbct->data['cookies_type'] !== 'alternative' ) {
        if ( !$apbct->settings['forms__search_test'] && !Get::get('s') ) { //skip cookie set for search form redirect page
            add_action('template_redirect', 'apbct_cookie', 2);
        }
        add_action('template_redirect', 'apbct_store__urls', 2);
    }
    if (
        empty($_POST) &&
        ( (isset($_GET['q']) && $_GET['q'] !== '') || empty($_GET) ) &&
        $apbct->data['key_is_ok']
    ) {
            apbct_cookie();
            apbct_store__urls();
    }
}

require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-validate-skip-functions.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-validate.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-integrations.php');

require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-functions-early-check.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-hook-integrations.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-functions-custom-integrations.php');

// Public actions
if ( ! is_admin() && ! apbct_is_ajax() && ! apbct_is_customize_preview() ) {
    // Default search
    add_filter('get_search_query', 'apbct_forms__search__testSpam');
    add_action('wp_head', 'apbct_search_add_noindex', 1);

    if (apbct_is_plugin_active('fluentformpro/fluentformpro.php') && apbct_is_in_uri('ff_landing=')) {
        add_action('wp_head', function () {
            echo '<script data-pagespeed-no-defer="" src="'
                . APBCT_URL_PATH
                . '/js/apbct-public-bundle.min.js'
                . '?ver=' . APBCT_VERSION . '" id="ct_public_functions-js"></script>';
            echo '<script src="https://moderate.cleantalk.org/ct-bot-detector-wrapper.js?ver='
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
        wp_suspend_cache_addition(true);
        apbct_sfw__check();
        wp_suspend_cache_addition(false);
    }
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
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-wc-spam-orders.php');

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

        if ( class_exists('BuddyPress') ) {
            add_filter(
                'bp_activity_is_spam_before_save',
                'apbct_integration__buddyPres__activityWall',
                999,
                2
            ); /* ActivityWall */
            add_action('bp_locate_template', 'apbct_integration__buddyPres__getTemplateName', 10, 6);
        }
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

require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-functions-rc.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-functions.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-functions-cron.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-functions-sfw.php');

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

// Redirect admin to plugin settings.
if ( ! defined('WP_ALLOW_MULTISITE') || (defined('WP_ALLOW_MULTISITE') && WP_ALLOW_MULTISITE == false) ) {
    add_action('admin_init', 'apbct_plugin_redirect');
}

$js_errors_arr = apbct_check_post_for_no_cookie_data();
if ($js_errors_arr && isset($js_errors_arr['data'])) {
    apbct_write_js_errors($js_errors_arr['data']);
}
