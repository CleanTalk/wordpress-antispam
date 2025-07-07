<?php

use Cleantalk\ApbctWP\AJAXService;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\RemoteCalls;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Request;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;

/**
 * Getting current user by cookie
 *
 * @return WP_User|null
 */
function apbct_wp_get_current_user()
{
    global $apbct, $current_user;

    $user = null;

    if ( ! (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) ) {
        if ( ! empty($apbct->user) ) {
            $user_id = is_object($current_user) && isset($current_user->ID) && ! ($current_user instanceof WP_User)
                ? $current_user->ID
                : null;
        } else {
            $user_id = defined('LOGGED_IN_COOKIE') && ! empty($_COOKIE[LOGGED_IN_COOKIE])
                ? apbct_wp_validate_auth_cookie($_COOKIE[LOGGED_IN_COOKIE], 'logged_in')
                : null;
        }

        if ( $user_id ) {
            $user = new WP_User($user_id);
        }
    }

    if (!is_null($current_user) && $current_user instanceof WP_User) {
        $current_user_defined = $current_user->ID === 0 ? null : $current_user;
    } else {
        $current_user_defined = null;
    }

    return $user ? $user : $current_user_defined;
}

function apbct_wp_set_current_user($user = null)
{
    global $apbct;

    if ( $user instanceof WP_User ) {
        $apbct->user = $user;

        return true;
    }

    return false;
}

/**
 * Validates authentication cookie.
 *
 * The checks include making sure that the authentication cookie is set and
 * pulling in the contents (if $cookie is not used).
 *
 * Makes sure the cookie is not expired. Verifies the hash in cookie is what is
 * should be and compares the two.
 *
 * @param string $cookie Optional. If used, will validate contents instead of cookie's
 * @param string $scheme Optional. The cookie scheme to use: auth, secure_auth, or logged_in
 *
 * @return false|int False if invalid cookie, User ID if valid.
 * @global int $login_grace_period
 *
 */
function apbct_wp_validate_auth_cookie($cookie = '', $scheme = '')
{
    $cookie_elements = apbct_wp_parse_auth_cookie($cookie, $scheme);

    if (!is_array($cookie_elements) || empty($cookie_elements)) {
        return false;
    }

    $scheme     = isset($cookie_elements['scheme']) ? $cookie_elements['scheme'] : '';
    $username   = isset($cookie_elements['username']) ? $cookie_elements['username'] : '';
    $hmac       = isset($cookie_elements['hmac']) ? $cookie_elements['hmac'] : '';
    $token      = isset($cookie_elements['token']) ? $cookie_elements['token'] : '';
    $expiration = isset($cookie_elements['expiration']) ? $cookie_elements['expiration'] : '';

    // Allow a grace period for POST and Ajax requests
    $expired = apbct_is_ajax() || apbct_is_post()
        ? $expiration + HOUR_IN_SECONDS
        : (isset($cookie_elements['expiration']) ? $cookie_elements['expiration'] : '');

    // Quick check to see if an honest cookie has expired
    if ( $expired >= time() ) {
        $user = apbct_wp_get_user_by('login', $username);
        if ( $user && is_object($user) ) {
            $pass_frag = substr($user->user_pass, 8, 4);
            $key       = apbct_wp_hash($username . '|' . $pass_frag . '|' . $expiration . '|' . $token, $scheme);
            // If ext/hash is not present, compat.php's hash_hmac() does not support sha256.
            $algo = function_exists('hash') ? 'sha256' : 'sha1';
            $hash = hash_hmac($algo, $username . '|' . $expiration . '|' . $token, $key);
            if ( hash_equals($hash, $hmac) && is_object($user) ) {
                $sessions = get_user_meta($user->ID, 'session_tokens', true);
                $sessions = is_array($sessions) ? current($sessions) : $sessions;
                if ( is_array($sessions) ) {
                    if ( isset($sessions['expiration']) && is_int($sessions['expiration']) && $sessions['expiration'] > time() ) {
                        return $user->ID;
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    } else {
        return false;
    }
}

/**
 * Checks if the user is a super admin
 *
 * @return boolean
 */
function apbct_is_super_admin($user_id = false)
{
    if (! $user_id) {
        $user = apbct_wp_get_current_user();
    } else {
        $user = get_userdata($user_id);
    }

    if (! $user || ! $user->exists()) {
        return false;
    }

    if (is_multisite()) {
        $super_admins = get_super_admins();
        if (is_array($super_admins) && in_array($user->user_login, $super_admins, true)) {
            return true;
        }
    } else {
        if ($user->has_cap('delete_users')) {
            return true;
        }
    }

    return false;
}

/**
 * Gets REST url
 *
 * @param $blog_id
 * @param string $path Optional.
 * @param string $scheme Optional.
 *
 * @return string
 */
function apbct_get_rest_url($blog_id = null, $path = '/', $scheme = 'rest')
{
    global $wp_rewrite;

    /**
     * If exists get_rest_url() - return it
     */
    if ( ! is_null($wp_rewrite) && function_exists('get_rest_url') ) {
        return get_rest_url();
    }

    if ( empty($path) ) {
        $path = '/';
    }

    $path = '/' . ltrim($path, '/');

    if ( is_multisite() && (get_blog_option($blog_id, 'permalink_structure') || get_option('permalink_structure')) ) {
        if ( !is_null($wp_rewrite) && $wp_rewrite->using_index_permalinks() ) {
            $url = get_home_url($blog_id, $wp_rewrite->index . '/' . rest_get_url_prefix(), $scheme);
        } else {
            $url = get_home_url($blog_id, rest_get_url_prefix(), $scheme);
        }

        $url .= $path;
    } else {
        $url = trailingslashit(get_home_url($blog_id, '', $scheme));
        // nginx only allows HTTP/1.0 methods when redirecting from / to /index.php.
        // To work around this, we manually add index.php to the URL, avoiding the redirect.
        if ( 'index.php' !== substr($url, 9) ) {
            $url .= 'index.php';
        }

        $url = add_query_arg('rest_route', $path, $url);
    }

    //this code part is partially copied from wp-includes/rest-api.php
    if ( is_ssl() && !empty(Server::get('SERVER_NAME')) ) {
        // If the current host is the same as the REST URL host, force the REST URL scheme to HTTPS.
        if ( parse_url(get_home_url($blog_id), PHP_URL_HOST) === Server::get('SERVER_NAME')) {
            $url = set_url_scheme($url, 'https');
        }
    }

    if ( is_admin() && force_ssl_admin() ) {
        /*
         * In this situation the home URL may be http:, and `is_ssl()` may be false,
         * but the admin is served over https: (one way or another), so REST API usage
         * will be blocked by browsers unless it is also served over HTTPS.
         */
        $url = set_url_scheme($url, 'https');
    }

    /**
     * Filters the REST URL.
     *
     * Use this filter to adjust the url returned by the get_rest_url() function.
     *
     * @param string $url REST URL.
     * @param string $path REST route.
     * @param int|null $blog_id Blog ID.
     * @param string $scheme Sanitization scheme.
     *
     * @psalm-suppress TooManyArguments
     * @since 4.4.0
     *
     */
    return apply_filters('rest_url', $url, $path, $blog_id, $scheme);
}

/**
 * Gets REST url only path
 *
 * @return string
 */
function apbct_get_rest_url_only_path()
{
    $url = apbct_get_rest_url(null, '/');
    $url = parse_url($url);
    return isset($url['path']) ? $url['path'] : '/';
}

/**
 * Gets user by filed
 *
 * @param $field
 * @param $value
 *
 * @return bool|WP_User
 */
function apbct_wp_get_user_by($field, $value)
{
    $userdata = WP_User::get_data_by($field, $value);

    if ( ! $userdata ) {
        return false;
    }

    $user = new WP_User();
    $user->init($userdata);

    return $user;
}

/**
 * Get hash of given string.
 *
 * @param string $data Plain text to hash
 * @param string $scheme Authentication scheme (auth, secure_auth, logged_in, nonce)
 *
 * @return string Hash of $data
 */
function apbct_wp_hash($data, $scheme = 'auth')
{
    $values = array(
        'key'  => '',
        'salt' => '',
    );

    foreach ( array('key', 'salt') as $type ) {
        $const = strtoupper("{$scheme}_{$type}");
        if ( defined($const) && constant($const) ) {
            $values[$type] = constant($const);
        } elseif ( ! $values[$type] ) {
            $values[$type] = get_site_option("{$scheme}_{$type}");
            if ( ! $values[$type] ) {
                $values[$type] = '';
            }
        }
    }

    $salt = $values['key'] . $values['salt'];

    return hash_hmac('md5', $data, $salt);
}

/**
 * Parse a cookie into its components
 *
 * @param string $cookie
 * @param string $scheme Optional. The cookie scheme to use: auth, secure_auth, or logged_in
 *
 * @return array|false Authentication cookie components
 *
 */
function apbct_wp_parse_auth_cookie($cookie = '', $scheme = '')
{
    $cookie_elements = explode('|', $cookie);
    if ( count($cookie_elements) !== 4 ) {
        return false;
    }

    list($username, $expiration, $token, $hmac) = $cookie_elements;

    return compact('username', 'expiration', 'token', 'hmac', 'scheme');
}

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
 * Checks if the theme is active
 *
 * @param string $theme_name template name
 *
 * @return bool
 */
function apbct_is_theme_active($theme_name)
{
    return get_option('template') == $theme_name ? true : false;
}

/**
 * Checks if the plugin is active for network
 *
 * @param string $plugin relative path from plugin folder like cleantalk-spam-protect/cleantalk.php
 *
 * @return bool
 */
function apbct_is_plugin_active_for_network($plugin)
{
    if ( ! APBCT_WPMS ) {
        return false;
    }

    $plugins = get_site_option('active_sitewide_plugins');

    return isset($plugins[$plugin])
        ? true
        : false;
}

/**
 * Checks if the request is AJAX
 *
 * @return boolean
 */
function apbct_is_ajax()
{
    return
        (defined('DOING_AJAX') && DOING_AJAX) || // by standart WP functions
        (
            Server::get('HTTP_X_REQUESTED_WITH') &&
            strtolower(TT::toString(Server::get('HTTP_X_REQUESTED_WITH'))) === 'xmlhttprequest'
        ) || // by Request type
        ! empty(Post::get('quform_ajax')) || // special. QForms
        ! empty(Post::get('iphorm_ajax')) || // special. IPHorm
        ! empty(Post::get('mf-email')); // special. Metform
}

/**
 * Checks if the request is REST
 *
 * @return boolean
 * @psalm-suppress RedundantCondition
 */
function apbct_is_rest()
{
    return defined('REST_REQUEST') && REST_REQUEST;
}

/**
 * Checks if the request is the command line access
 *
 * @return boolean
 */
function apbct_is_cli()
{
    return PHP_SAPI === "cli";
}

/**
 * Checks if the user is logged in
 *
 * @return bool
 */
function apbct_is_user_logged_in()
{
    $siteurl    = get_site_option('siteurl');
    $cookiehash = $siteurl ? md5($siteurl) : '';

    return count($_COOKIE) && isset($_COOKIE['wordpress_logged_in_' . $cookiehash]);
}

function apbct_is_post()
{
    return Server::get('REQUEST_METHOD') === 'POST';
}

function apbct_is_get()
{
    return Server::get('REQUEST_METHOD') === 'GET';
}

function apbct_is_in_referer($str)
{
    return stripos(TT::toString(Server::get('HTTP_REFERER')), $str) !== false;
}

function apbct_is_in_uri($str)
{
    return stripos(TT::toString(Server::get('REQUEST_URI')), $str) !== false;
}

/**
 * Checking if current request is a cron job
 * Support for WordPress < 4.8.0
 *
 * @return bool
 * @psalm-suppress RedundantCondition
 */
function apbct_wp_doing_cron()
{
    if ( function_exists('wp_doing_cron') ) {
        return wp_doing_cron();
    } else {
        return (defined('DOING_CRON') && DOING_CRON);
    }
}

/**
 * Checks if a comment contains disallowed characters or words.
 *
 * @param $author
 * @param $email
 * @param $url
 * @param $comment
 * @param $user_ip
 * @param $user_agent
 *
 * @return bool
 */
function apbct_wp_blacklist_check($author, $email, $url, $comment, $user_ip, $user_agent)
{
    global $wp_version;

    if ( version_compare($wp_version, '5.5.0', '>=') ) {
        return wp_check_comment_disallowed_list($author, $email, $url, $comment, $user_ip, $user_agent);
    } else {
        return wp_blacklist_check($author, $email, $url, $comment, $user_ip, $user_agent);
    }
}

/**
 * Check if the site is being previewed in the Customizer.
 * We can not use is_customize_preview() - the function must be called from init hook.
 *
 * @return bool
 */
function apbct_is_customize_preview()
{
    // Maybe not enough to check the Customizer preview
    $uri = parse_url(TT::toString(Server::get('REQUEST_URI')));

    return $uri && isset($uri['query']) && strpos($uri['query'], 'customize_changeset_uuid') !== false;
}

/**
 * Check if the request is a direct trackback (like url_to_a_post/trackback/)
 *
 * @return bool
 */
function apbct_is_direct_trackback()
{
    return
        Server::hasString('REQUEST_URI', '/trackback') &&
        isset($_POST) &&
        ! empty(Post::get('url')) &&
        ! empty(Post::get('title'));
}

/**
 * Determines whether the query is for a trackback endpoint call.
 * @see is_trackback()
 *
 * @return bool
 */
function apbct_is_trackback()
{
    global $wp_query;

    if ( ! isset($wp_query) ) {
        return false;
    }

    return $wp_query->is_trackback();
}

/**
 * Checking if the request must be skipped.
 *
 * @param $ajax bool The current request is the ajax request?
 * @param $ajax_message_obj array The message object for the ajax request, default is []
 *
 * @return bool|string   false or request name for logging
 */
function apbct_is_skip_request($ajax = false, $ajax_message_obj = array())
{
    /* !!! Have to use more than one factor to detect the request - is_plugin active() && $_POST['action'] !!! */
    //@ToDo Implement direct integration checking - if have the direct integration will be returned false

    global $apbct;

    if ( RemoteCalls::check() ) {
        return 'CleanTalk RemoteCall request.';
    }

    if (
        TT::toString(Post::get('action')) === 'apbct_alt_session__save__AJAX' &&
        wp_verify_nonce(TT::toString(Post::get('_ajax_nonce')), AJAXService::$public_nonce_id)
    ) {
        return 'CleanTalk AltCookies request.';
    }

    if ( is_admin() && ! $ajax ) {
        return 'Admin side request.';
    }

    // Events Manager - there is the direct integration
    if (
        apbct_is_plugin_active('events-manager/events-manager.php') &&
        (Post::getString('action') === 'booking_add' || Post::getString('action') === 'em_booking_add') &&
        wp_verify_nonce(Post::getString('_wpnonce'), 'booking_add')
    ) {
        return 'Event Manager skip';
    }

    if ( $ajax ) {
        /*****************************************/
        /*    Here is ajax requests skipping     */
        /*****************************************/

        // $_REQUEST['action'] to skip. Go out because of not spam data
        $skip_for_request_actions = array(
            'apbct_js_keys__get',
            // Our service code
            'gmaps_display_info_window',
            // Geo My WP pop-up windows.
            'gmw_ps_display_info_window',
            // Geo My WP pop-up windows.
            'the_champ_user_auth',
            // Super Socializer
            'simbatfa-init-otp',
            //Two-Factor Auth
            'wppb_msf_check_required_fields',
            //ProfileBuilder skip step checking
            'boss_we_login',
            //Login form
            'sidebar_login_process',
            // Login CF7
            'cp_update_style_settings',
            // Convert Pro. Saving settings
            'updraft_savesettings',
            // UpdraftPlus
            'wpdUpdateAutomatically',
            //Comments update
            'upload-attachment',
            // Skip ulpload attachments
            'iwj_update_profile',
            //Skip profile page checker
            'st_partner_create_service',
            //Skip add hotel via admin
            'vp_ajax_vpt_option_save',
            // https://themeforest.net/item/motor-vehicles-parts-equipments-accessories-wordpress-woocommerce-theme/16829946
            'mailster_send_test',
            //Mailster send test admin
            'admin:saveThemeOptions',
            //Ait-theme admin checking
            'save_tourmaster_option',
            //Tourmaster admin save
            'validate_register_email',
            //Elementor Pro
            'phone-orders-for-woocommerce',
            //Phone orders for woocommerce backend
            'ihc_check_reg_field_ajax',
            //Ajax check required fields
            'OSTC_lostPassword',
            //Lost password ajax form
            'check_retina_image_availability',
            //There are too many ajax requests from mobile
            'uap_check_reg_field_ajax',
            // Ultimate Affiliate Pro. Form validation.
            'edit-comment',
            // Edit comments by admin ??? that shouldn't happen
            'formcraft3_save_form_progress',
            // FormCraft – Contact Form Builder for WordPress. Save progress.
            'wpdmpp_save_settings',
            // PayPal save settings.
            'iwj_login',
            // Fix for unknown plugin for user #133315
            'custom_user_login',
            // Fix for unknown plugin for user #466875
            'wordfence_ls_authenticate',
            //Fix for wordfence auth
            'frm_strp_amount',
            //Admin stripe form
            'wouCheckOnlineUsers',
            //Skip updraft admin checking users
            'et_fb_get_shortcode_from_fb_object',
            //Skip generate shortcode
            'pp_lf_process_login',
            //Skip login form
            'check_email',
            //Ajax email checking
            'dflg_do_sign_in_user',
            // Unknown plugin
            'cartflows_save_cart_abandonment_data',
            // WooCommerce cartflow
            'rcp_process_register_form',
            // WordPress Membership Plugin – Restrict Content
            'apus_ajax_login',
            // ???? plugin authorization
            'bookly_save_customer',
            //bookly
            'postmark_test',
            //Avocet
            'postmark_save',
            //Avocet
            'ck_get_subscriber',
            //ConvertKit checking the subscriber
            'metorik_send_cart',
            //Metorik skip
            'ppom_ajax_validation',
            // PPOM add to cart validation
            'wpforms_form_abandonment',
            // WPForms. Quiting without submitting
            'post_woo_ml_email_cookie',
            //Woocommerce system
            'ig_es_draft_broadcast',
            //Icegram broadcast ajax
            'simplefilelistpro_edit_job',
            //Simple File List editing current job
            'wfu_ajax_action_ask_server',
            //WFU skip ask server
            'wfu_ajax_action',
            //WFU skip ask server
            'wcap_save_guest_data',
            //WooCommerce skip
            'ajaxlogin',
            //Skip ajax login redirect
            'heartbeat',
            //Gravity multipage
            'erforms_field_change_command',
            //ERForms internal request
            'wl_out_of_stock_notify',
            // Sumo Waitlist
            'rac_preadd_guest',
            //Rac internal request
            'apbct_email_check_before_post',
            //Interal request
            'edd_process_checkout',
            // Easy Digital Downloads ajax skip
            //Unknown plugin Ticket #25047
            'alhbrmeu',
            // Ninja Forms
            'nf_preview_update',
            'nf_save_form',
            // WPUserMeta registration plugin exclusion
            'pf_ajax_request',
            //profilegrid addon
            'pm_check_user_exist',
            //Cartbounty plugin (saves every action on the page to keep abandoned carts)
            'cartbounty_pro_save', 'cartbounty_save',
            'wpmtst_form2', //has direct integration StrongTestimonials
            'latepoint_route_call', //LatePoint service calls
            'uael_login_form_submit', //Ultimate Addons for Elementor login
            'my_custom_login_validate', //Ultimate Addons for Elementor login validate
            'wpforms_restricted_email', //WPForm validate
            'fluentcrm_unsubscribe_ajax', //FluentCRM unsubscribe
            'forminator_submit_form_custom', //Forminator has direct integration
            'forminator_submit_form_custom-forms', //Forminator has direct integration
            'wcf_woocommerce_login', //WooCommerce CartFlows login
            'nasa_process_login', //Nasa login
            'leaky_paywall_validate_registration', //Leaky Paywall validation request
            'cleantalk_force_ajax_check', //Force ajax check has direct integration
            'cscf-submitform', // CSCF has direct integration
        );

        // Skip test if
        if ( ( ! $apbct->settings['forms__general_contact_forms_test'] && ! $apbct->settings['forms__check_external'] ) ) {
            return 'Form testing is disabled in the plugin settings';
        }

        if ( ! apbct_is_user_enable($apbct->user) ) {
            return 'User is admin, editor, author';
        }

        if ( ! $apbct->settings['data__protect_logged_in'] && ($apbct->user instanceof WP_User) && $apbct->user->ID !== 0 ) {
            return 'User is logged in and protection is disabled for logged in users';
        }

        if ( apbct_exclusions_check__url() ) {
            return 'URL exclusions';
        }

        /**
         * Apply filtration list of actions to skip for the POST/GET request
         */

        if ( Post::getString('action') && in_array(Post::getString('action'), $skip_for_request_actions) ) {
            return 'POST action skipped - ' . Post::getString('action');
        }

        if ( Get::getString('action') && in_array(Get::getString('action'), $skip_for_request_actions) ) {
            return 'GET action skipped - ' . Get::getString('action');
        }

        /**
         * End of the filtration list of actions to skip for the POST/GET request
         */

        if ( Post::get('quform_submit') ) {
            return 'QForms multi-paged form skip';
        }

        if ( (string)current_filter() !== 'et_pre_insert_answer' &&
             ( (isset($ajax_message_obj['author']) && (int)$ajax_message_obj['author'] === 0) ||
               (isset($ajax_message_obj['post_author']) && (int)$ajax_message_obj['post_author'] === 0) ) ) {
            return 'QAEngine Theme fix';
        }

        if ( Post::get('action') === 'erf_login_user' && in_array('easy-registration-forms/erforms.php', apply_filters('active_plugins', get_option('active_plugins'))) ) {
            return'Easy Registration Forms login form skip';
        }

        if ( Post::get('action') === 'mailpoet' && Post::get('endpoint') === 'ImportExport' && Post::get('method') === 'processImport' ) {
            return 'Mailpoet import';
        }

        if ( Post::get('action') === 'arm_shortcode_form_ajax_action' && Post::get('arm_action') === 'please-login' ) {
            return 'ARM forms skip login';
        }

        if (apbct_is_plugin_active('ws-form/ws-form.php') && Post::getString('action') === 'the_ajax_hook') {
            return 'WS Form submit service request';
        }

        // Paid Memberships Pro - Login Form
        if (
            apbct_is_plugin_active('paid-memberships-pro/paid-memberships-pro.php') &&
            TT::toString(Post::get('rm_slug')) === 'rm_login_form' &&
            TT::toString(Post::get('rm_form_sub_id'))
        ) {
            return 'paid_memberships_pro__login_form';
        }

        if (
            Post::get('action') === 'acf/validate_save_post' &&
            (
             apbct_is_plugin_active('advanced-custom-fields-pro/acf.php') ||
             apbct_is_plugin_active('advanced-custom-fields/acf.php')
            ) &&
            apbct_is_user_logged_in()
        ) {
            return 'ACF admin - skip post saving [acf/validate_save_post]';
        }

        // Thrive Ultimatum
        if (
            apbct_is_plugin_active('thrive-ultimatum/thrive-ultimatum.php') &&
            TT::toString(Post::get('action')) === 'tve_dash_front_ajax'
        ) {
            return 'thrive-ultimatum__links_from_email';
        }

        // wpDiscuz - Online Users Addon for wpDiscuz
        if (
            apbct_is_plugin_active('wpdiscuz-online-users/wpdiscuz-ou.php') &&
            TT::toString(Post::get('action')) === 'wouPushNotification'
        ) {
            return 'wpdiscuz_online_users__push_notification';
        }

        // Bookly Plugin admin actions skip
        if ( apbct_is_plugin_active('bookly-responsive-appointment-booking-tool/main.php') &&
            strpos(TT::toString(Post::get('action')), 'bookly') !== false &&
            is_admin() ) {
            return 'bookly_pro_update_staff_advanced';
        }
        // Youzier login form skip
        if ( apbct_is_plugin_active('youzer/youzer.php') &&
            TT::toString(Post::get('action')) === 'yz_ajax_login' ) {
            return 'youzier_login_form';
        }
        // Youzify login form skip
        if ( apbct_is_plugin_active('youzify/youzify.php') &&
            TT::toString(Post::get('action')) === 'youzify_ajax_login' ) {
            return 'youzify_login_form';
        }
        // InJob theme lost password skip
        if ( apbct_is_plugin_active('iwjob/iwjob.php') &&
            TT::toString(Post::get('action')) === 'iwj_lostpass' ) {
            return 'injob_theme_plugin';
        }
        // Divi builder skip
        if ( apbct_is_theme_active('Divi') &&
            (TT::toString(Post::get('action')) === 'save_epanel' || TT::toString(Post::get('action')) === 'et_fb_ajax_save') ) {
            return 'divi_builder_skip';
        }
        // Email Before Download plugin https://wordpress.org/plugins/email-before-download/ action skip
        if ( apbct_is_plugin_active('email-before-download/email-before-download.php') &&
            TT::toString(Post::get('action')) === 'ebd_inline_links' ) {
            return 'ebd_inline_links';
        }
        // WP Discuz skip service requests. The plugin have the direct integration
        if ( apbct_is_plugin_active('wpdiscuz/class.WpdiscuzCore.php') &&
            strpos(TT::toString(Post::get('action')), 'wpd') !== false ) {
            return 'WpdiscuzCore';
        }
        // Exception for plugin https://ru.wordpress.org/plugins/easy-login-woocommerce/ login form
        if (
            apbct_is_plugin_active('easy-login-woocommerce/xoo-el-main.php') &&
            TT::toString(Post::get('_xoo_el_form')) === 'login'
        ) {
            return 'xoo_login';
        }
        // Emails & Newsletters with Jackmail: skip all admin-side actions
        if (
            apbct_is_plugin_active('jackmail-newsletters/jackmail-newsletters.php') &&
            is_admin() &&
            strpos(TT::toString(Server::get('HTTP_REFERER')), 'jackmail_') !== false
        ) {
            return 'jackmail_admin_actions';
        }
        // Newspaper theme login form
        if ( apbct_is_theme_active('Newspaper') &&
            (TT::toString(Post::get('action')) === 'td_mod_login' || TT::toString(Post::get('action')) === 'td_mod_remember_pass') ) {
            return 'Newspaper_theme_login_form';
        }
        // Save abandoned cart checking skip
        if ( apbct_is_plugin_active('woo-save-abandoned-carts/cartbounty-abandoned-carts.php') &&
             TT::toString(Post::get('action')) === 'cartbounty_save' ) {
            return 'cartbounty_save';
        }
        // SUMODISCOUNT discout request skip
        if ( apbct_is_plugin_active('sumodiscounts/sumodiscounts.php') &&
             TT::toString(Post::get('action')) === 'fp_apply_discount_for_first_purchase' ) {
            return 'fp_apply_discount_for_first_purchase';
        }
        // WP eMember login form skip
        if ( apbct_is_plugin_active('wp-eMember/wp_eMember.php') &&
             TT::toString(Post::get('action')) === 'emember_ajax_login' ) {
            return 'emember_ajax_login';
        }
        // Avada theme saving settings
        if ( apbct_is_theme_active('Avada') &&
             TT::toString(Post::get('action')) === 'fusion_options_ajax_save' ) {
            return 'Avada_theme_saving_settings';
        }
        // Formidable skip - this is the direct integration
        if ( apbct_is_plugin_active('formidable/formidable.php') &&
            (TT::toString(Post::get('frm_action')) === 'update' ||
            (TT::toString(Post::get('frm_action')) === 'create' &&
            $apbct->settings['forms__contact_forms_test'] == 1 &&
            TT::toString(Post::get('form_id')) !== '' &&
            TT::toString(Post::get('form_key')) !== ''))
        ) {
            return 'formidable_skip';
        }
        // Artbees Jupiter theme saving settings
        if ( TT::toString(Post::get('action')) === 'mk_theme_save' && strpos(get_template(), 'jupiter') !== false ) {
            return 'artbees_jupiter_6_skip';
        }
        // fix conflict with wiloke theme and unknown plugin, that removes standard authorization cookies
        if ( TT::toString(Post::get('action')) === 'wiloke_themeoptions_ajax_save' && apbct_is_theme_active('wilcity') ) {
            return 'wiloke_themeoptions_ajax_save_skip';
        }
        // Essentials addons for elementor - light and pro
        if (
            (apbct_is_plugin_active('essential-addons-for-elementor-lite/essential_adons_elementor.php') ||
             apbct_is_plugin_active('essential-addons-elementor/essential_adons_elementor.php')) &&
            (TT::toString(Post::get('eael-login-submit')) !== '' && TT::toString(Post::get('eael-user-login')) !== '') ) {
            return 'eael_login_skipped';
        }
        // WPForms check restricted email skipped
        if (
            apbct_is_plugin_active('wpforms/wpforms.php') &&
            TT::toString(Post::get('action')) === 'wpforms_restricted_email'
        ) {
            return 'wpforms_check_restricted_email';
        }
        // FluentForm multistep skip
        if (
            (
                apbct_is_plugin_active('fluentformpro/fluentformpro.php')
                ||  apbct_is_plugin_active('fluentform/fluentform.php'))
            &&
            (
                Post::getString('action') === 'active_step'
                || Post::getString('action') === 'fluentform_step_form_save_data'
            )
        ) {
            return 'fluentform_skip';
        }

        // W2DC - https://codecanyon.net/item/web-20-directory-plugin-for-wordpress/6463373
        if ( apbct_is_plugin_active('w2dc/w2dc.php') &&
             TT::toString(Post::get('action')) === 'vp_w2dc_ajax_vpt_option_save' &&
             is_admin() ) {
            return 'w2dc_skipped';
        }
        if ( (apbct_is_plugin_active('elementor/elementor.php') || apbct_is_plugin_active('elementor-pro/elementor-pro.php')) &&
             TT::toString(Post::get('actions_save_builder_action')) === 'save_builder' &&
             is_admin() ) {
            return 'elementor_skip';
        }
        // Enfold theme saving settings
        if ( apbct_is_theme_active('Enfold') &&
             TT::toString(Post::get('action')) === 'avia_ajax_save_options_page' ) {
            return 'Enfold_theme_saving_settings';
        }
        //SiteOrigin pagebuilder skip save
        if (
            apbct_is_plugin_active('siteorigin-panels/siteorigin-panels.php') &&
            TT::toString(Post::get('action')) === 'save-widget'
        ) {
            return 'SiteOrigin pagebuilder';
        }
        //Skip classfields email check
        if (
            (apbct_is_theme_active('classified-child') || apbct_is_theme_active('classified'))
            && TT::toString(Post::get('action')) === 'tmpl_ajax_check_user_email'
        ) {
            return 'Classified checkemail';
        }
        if (
            (apbct_is_plugin_active('uncanny-toolkit-pro/uncanny-toolkit-pro.php') || apbct_is_plugin_active('uncanny-learndash-toolkit'))
            && TT::toString(Post::get('action')) === 'ult-forgot-password'
        ) {
            return 'Uncanny Toolkit';
        }
        if (
            apbct_is_plugin_active('popup-builder/popup-builder.php') &&
            TT::toString(Post::get('action')) === 'sgpb_send_to_open_counter'
        ) {
            return 'Popup builder service actions';
        }
        if (
            apbct_is_plugin_active('security-malware-firewall/security-malware-firewall.php') &&
            TT::toString(Post::get('action')) === 'spbc_get_authorized_users'
        ) {
            return 'SPBCT service actions';
        }
        // APBCT service actions
        if (
            apbct_is_plugin_active('cleantalk-spam-protect/cleantalk.php') &&
            ( TT::toString(Post::get('action')) === 'apbct_get_pixel_url' && wp_verify_nonce(TT::toString(Post::get('_ajax_nonce')), AJAXService::$public_nonce_id) )
        ) {
            return 'APBCT service actions';
        }
        // Entry Views plugin service requests
        if (
            apbct_is_plugin_active('entry-views/entry-views.php') &&
            TT::toString(Post::get('action')) === 'entry_views' &&
            TT::toString(Post::get('post_id')) !== ''
        ) {
            return 'Entry Views service actions';
        }
        // Woo Gift Wrapper plugin service requests
        if (
            apbct_is_plugin_active('woocommerce-gift-wrapper/woocommerce-gift-wrapper.php') &&
            TT::toString(Post::get('action')) === 'wcgwp_remove_from_cart'
        ) {
            return 'Woo Gift Wrapper service actions';
        }
        // iThemes Security plugin service requests
        if (
            apbct_is_plugin_active('better-wp-security/better-wp-security.php') &&
            TT::toString(Post::get('action')) === 'itsec-login-interstitial-ajax'
        ) {
            return 'iThemes Security service actions';
        }
        // Microsoft Azure Storage plugin service requests
        if (
            apbct_is_plugin_active('windows-azure-storage/windows-azure-storage.php') &&
            TT::toString(Post::get('action')) === 'get-azure-progress'
        ) {
            return 'Microsoft Azure Storage service actions';
        }
        // AdRotate plugin service requests
        if (
            apbct_is_plugin_active('adrotate/adrotate.php') &&
            TT::toString(Post::get('action')) === 'adrotate_impression' &&
            TT::toString(Post::get('track')) !== ''
        ) {
            return 'AdRotate service actions';
        }
        // WP Booking System Premium
        if (
            (apbct_is_plugin_active('wp-booking-system-premium/index.php') &&
            TT::toString(Post::get('action')) === 'wpbs_calculate_pricing') ||
            TT::toString(Post::get('action')) === 'wpbs_validate_date_selection'
        ) {
            return 'WP Booking System Premium';
        }
        // GiveWP - having the direct integration
        if (
            (apbct_is_plugin_active('give/give.php') &&
            TT::toString(Post::get('action')) === 'give_process_donation')
        ) {
            return 'GiveWP';
        }

        // MultiStep Checkout for WooCommerce
        if (
            apbct_is_plugin_active('woo-multistep-checkout/woo-multistep-checkout.php') &&
            TT::toString(Post::get('action')) === 'thwmsc_step_validation'
        ) {
            return 'MultiStep Checkout for WooCommerce - step validation';
        }

        // Skip Login Form for Wishlist Member
        if (
            apbct_is_plugin_active('wishlist-member/wpm.php') &&
            TT::toString(Post::get('action')) === 'wishlistmember_ajax_login'
        ) {
            return 'Wishlist Member - skip login';
        }

        // Skip some Smart Quiz Builder requests
        if (
            apbct_is_plugin_active('smartquizbuilder/smartquizbuilder.php') &&
            (
                Post::getString('action') === 'sqb_lead_save' ||
                Post::getString('action') === 'SQBSendNotificationAjax' ||
                Post::getString('action') === 'SQBSubmitQuizAjax'
            )
        ) {
            return 'Smart Quiz Builder - skip some requests';
        }

        // Abandoned Cart Recovery for WooCommerce requests
        if (
            apbct_is_plugin_active('woo-abandoned-cart-recovery/woo-abandoned-cart-recovery.php') &&
            Post::hasString('action', 'wacv_') &&
            (
                wp_verify_nonce(TT::toString(Post::get('nonce')), 'wacv_nonce') ||
                wp_verify_nonce(TT::toString(Get::get('nonce')), 'wacv_nonce') ||
                wp_verify_nonce(TT::toString(Post::get('security')), 'wacv_nonce')
            )
        ) {
            return 'Abandoned Cart Recovery for WooCommerce: skipped ' . TT::toString(Post::get('action'));
        }

        //Skip smart_forms because of direct integration
        if (
            apbct_is_plugin_active('smart-forms/smartforms.php') &&
            Post::get('action') === 'rednao_smart_forms_save_form_values'
        ) {
            return 'Smart Forms skip';
        }

        //Skip Universal form builder because of direct integration
        if (
            apbct_is_plugin_active('ultimate-form-builder-lite/ultimate-form-builder-lite.php') &&
            Post::get('action') === 'ufbl_front_form_action'
        ) {
            return 'Universal form builder skip';
        }

        //Skip ActiveCampaign for WooCommerce service request
        if (
            apbct_is_plugin_active('activecampaign-for-woocommerce/activecampaign-for-woocommerce.php') &&
            Post::get('action') === 'activecampaign_for_woocommerce_cart_sync_guest'
        ) {
            return 'ActiveCampaign for WooCommerce skip';
        }

        //Skip WooCommerce add to cart trigger
        if (
            apbct_is_plugin_active('woocommerce/woocommerce.php') &&
            Post::get('action') === 'wdm_trigger_add_to_enq_cart'
        ) {
            return 'WooCommerce add to cart trigger skip';
        }

        //Skip WooCommerce addon - Wati - action for customers who came from Whatsapp
        if (
            apbct_is_plugin_active('woocommerce/woocommerce.php') &&
            Post::get('action') === 'wati_cartflows_save_cart_abandonment_data'
        ) {
            return 'WooCommerce addon Wati add to cart trigger skip';
        }

        //Skip RegistrationMagic service request
        if (
            apbct_is_plugin_active('custom-registration-form-builder-with-submission-manager/registration_magic.php') &&
            Post::get('action') === 'rm_user_exists' ||
            Post::get('action') === 'check_username_validity' ||
            Post::get('action') === 'check_email_exists'
        ) {
            return 'RegistrationMagic service request';
        }

        //Wp Booking System request - having the direct integration
        if (
            apbct_is_plugin_active('wp-booking-system/wp-booking-system.php') &&
            Post::get('action') === 'wpbs_submit_form'
        ) {
            return 'Wp Booking System request';
        }

        // Contact Form by Supsystic - having the direct integration
        if (
            apbct_is_plugin_active('contact-form-by-supsystic/cfs.php') &&
            Post::get('action') === 'contact'
        ) {
            return 'Contact Form by Supsystic request';
        }

        // Quiz And Survey Master
        if (
            apbct_is_plugin_active('qsm-save-resume/qsm-save-resume.php') &&
            Post::get('action') === 'qsm_save_resume_auto_save_data'
        ) {
            return 'Quiz And Survey Master - QSM - Save & Resume Addon';
        }

        // Plugin Name: CartFlows; ajax action wcf_check_email_exists
        if (
            apbct_is_plugin_active('cartflows/cartflows.php') &&
            Post::get('action') === 'wcf_check_email_exists'
        ) {
            return 'Plugin Name: CartFlows; ajax action wcf_check_email_exists';
        }

        // Plugin Name: Profile Builder; ajax action wppb_conditional_logic
        if (
            apbct_is_plugin_active('profile-builder/index.php') &&
            Post::get('action') === 'wppb_conditional_logic' &&
            Post::get('formType') === 'register'
        ) {
            return 'Plugin Name: Profile Builder; ajax action wppb_conditional_logic';
        }

        // Plugin Name: ModernEventsCalendar have the direct integration.
        if (
            apbct_is_plugin_active('modern-events-calendar/mec.php') &&
            Post::get('action') === 'mec_book_form' &&
            Request::get('book')
        ) {
            return 'ModernEventsCalendar skip (direct integration)';
        }

        // Plugin Name: DIGITS: WordPress Mobile Number Signup and Login; ajax login action digits_forms_ajax
        if (
            apbct_is_plugin_active('digits/digit.php') &&
            Post::get('action') === 'digits_forms_ajax' &&
            (Post::get('type') === 'login' || (Post::get('type') === 'register' && Post::get('digits_otp_field') === '1') )
        ) {
            return 'Plugin Name: DIGITS: WordPress Mobile Number Signup and Login; ajax login action digits_forms_ajax';
        }

        // Plugin Name: Ultimate Addons for Beaver Builder: Exclude login form request
        if (
            apbct_is_plugin_active('bb-ultimate-addon/bb-ultimate-addon.php') &&
            Post::get('action') === 'uabb-lf-form-submit' &&
            check_ajax_referer('uabb-lf-nonce', 'nonce')
        ) {
            return 'Plugin Name: Ultimate Addons for Beaver Builder: Exclude login form request';
        }

        // Plugin Name: Digimember: Exclude login form request
        if (
            apbct_is_plugin_active('digimember/digimember.php') &&
            Post::get('action') === 'ncore_ajax_action' &&
            Post::get('ncore_plugin') === 'digimember'
        ) {
            return 'Plugin Name: Digimember: Exclude login form request';
        }

        // Exclude Authorize.net payment form request
        if (
            Post::get('action') === 'rm_authnet_ipn' &&
            Post::get('x_invoice_num') !== '' &&
            Post::get('x_amount') !== ''
        ) {
            return 'Exclude Authorize.net payment form request';
        }

        // Exclude ProfilePress login form request
        if (
            apbct_is_plugin_active('wp-user-avatar/wp-user-avatar.php') &&
            Post::get('action') === 'pp_ajax_login'
        ) {
            return 'Exclude ProfilePress login form request';
        }

        // Exclude UserPro login form request
        if (
            apbct_is_plugin_active('userpro/index.php') &&
            (Post::get('action') === 'userpro_fbconnect' || Post::get('action') === 'userpro_side_validate')
        ) {
            return 'Exclude UserPro login form request';
        }

        // Flux Checkout for WooCommerce service requests
        if (
            (
                apbct_is_plugin_active('flux-checkout-premium/flux-checkout.php') ||
                apbct_is_plugin_active('flux-checkout/flux-checkout.php')
            )
            &&
            (
                Post::get('action') === 'flux_check_email_exists' ||
                Post::get('action') === 'flux_check_for_inline_error' ||
                Post::get('action') === 'flux_check_for_inline_errors'
            )
        ) {
            return 'Flux Checkout for WooCommerce service requests';
        }

        // TranslatePress - Multilingual, action trp_get_translations_regular
        if (
            apbct_is_plugin_active('translatepress-multilingual/index.php') &&
            Post::get('action') === 'trp_get_translations_regular'
        ) {
            return 'TranslatePress - Multilingual, action trp_get_translations_regular';
        }

        // Cleantalk Register Widget request was excluded because there is the direct integration
        if (
            apbct_is_plugin_active('cleantalk-register-widget/CleantalkRegisterWidget.php') &&
            Post::get('action') === 'cleantalk_register_widget__get_api_key' &&
            check_ajax_referer('cleantalk_register_widget')
        ) {
            return 'Cleantalk Register Widget request';
        }

        // ElementorUltimateAddonsRegister
        if (
            apbct_is_plugin_active('ultimate-elementor/ultimate-elementor.php') &&
            Post::get('action') === 'uael_register_user'
        ) {
            return 'Elementor UltimateAddons Register form';
        }

        // VBOUT Woocommerce Plugin
        if (
            apbct_is_plugin_active('vbout-woocommerce-plugin/vbout.php') &&
            Post::get('action') === 'updatevboutabandon'
        ) {
            return 'VBOUT Woocommerce Plugin request';
        }

        // WooCommerce Waitlist Plugin
        if (
            apbct_is_plugin_active('woocommerce-waitlist/woocommerce-waitlist.php') &&
            Post::get('action') === 'wcwl_process_user_waitlist_request'
        ) {
            return 'WooCommerce Waitlist request';
        }

        if (
            (
                apbct_is_plugin_active('user-registration/user-registration.php')
                ||
                apbct_is_plugin_active('user-registration-pro/user-registration.php')
            ) &&
            Post::get('action') === 'user_registration_user_form_submit'
        ) {
            return 'user-registration/user-registration-pro';
        }

        // Convertkit service action
        if (
            apbct_is_plugin_active('convertkit/wp-convertkit.php') &&
            Post::get('action') === 'convertkit_store_subscriber_email_as_id_in_cookie'
        ) {
            return 'Convertkit service action';
        }

        if (
            apbct_is_plugin_active('facetwp/index.php') &&
            Post::get('action') === 'facetwp_refresh'
        ) {
            return 'FacetWP facetwp_refresh service action';
        }

        // BackInStockNotifier skip - have the direct integration
        if (
            apbct_is_plugin_active('back-in-stock-notifier-for-woocommerce/cwginstocknotifier.php') &&
            Post::get('action') === 'cwginstock_product_subscribe'
        ) {
            return 'BackInStockNotifier service action';
        }

        //WP GeoDirectory service action
        if (
            apbct_is_plugin_active('geodirectory/geodirectory.php') &&
            Post::get('action') === 'geodir_auto_save_post' ||
            Post::get('action') === 'geodir_save_post'
        ) {
            return 'WP GeoDirectory service action';
        }

        if (
            (
                apbct_is_plugin_active('paid-member-subscriptions/index.php') ||
                apbct_is_plugin_active('paid-member-subscriptions-pro/index.php')
            ) &&
            Post::get('action') === 'pms_update_payment_intent_connect'
        ) {
            return 'Paid memebership service action';
        }

        if (
            (
                apbct_is_plugin_active('easy-digital-downloads/easy-digital-downloads.php') ||
                apbct_is_plugin_active('easy-digital-downloads-pro/easy-digital-downloads.php')
            ) &&
            Post::get('action') === 'edd_add_to_cart' ||
            Post::get('action') === 'edd_get_shipping_rate' ||
            Post::get('action') === 'edd_check_email' ||
            Post::get('action') === 'edd_recalculate_discounts_pro'
        ) {
            return 'Easy Digital Downloads service action';
        }

        if (
            (
                apbct_is_plugin_active('bookingpress-appointment-booking/bookingpress-appointment-booking.php') ||
                apbct_is_plugin_active('bookingpress-appointment-booking-pro/bookingpress-appointment-booking-pro.php')
            ) &&
            Post::get('action') === 'bookingpress_pre_booking_verify_details' ||
            Post::get('action') === 'bookingpress_book_appointment_booking'
        ) {
            return 'BookingPress service action';
        }

        if (
            (
                apbct_is_plugin_active('pixelyoursite/pixelyoursite.php') ||
                apbct_is_plugin_active('pixelyoursite-pro/pixelyoursite-pro.php')
            ) &&
            Post::get('action') === 'pys_api_event'
        ) {
            return 'Pixelyoursite service action';
        }

        //this is theme request, no way to get active child theme correctly
        $current_theme_uri = wp_get_theme()->get('ThemeURI');
        if (
            strpos(TT::toString($current_theme_uri), 'porto') !== false &&
            TT::toString(Post::get('action')) === 'porto_account_login_popup_login'
        ) {
            return 'Proto theme login popup form';
        }

        if (
            (
                apbct_is_plugin_active('piotnet-addons-for-elementor-pro/piotnet-addons-for-elementor-pro.php') ||
                apbct_is_plugin_active('piotnet-addons-for-elementor/piotnet-addons-for-elementor.php')
            ) &&
            Post::get('action') === 'pafe_ajax_form_builder_preview_submission' ) {
            return 'PAFE';
        }

        // Bloom - has the direct integration
        if (
            apbct_is_plugin_active('bloom/bloom.php') &&
            Post::get('action') === 'bloom_subscribe'
        ) {
            return 'Bloom';
        }

        // Ajax Search Lite - these requests will be caught by search form protection
        if (
            apbct_is_plugin_active('ajax-search-lite/ajax-search-lite.php') &&
            Post::get('action') === 'ajaxsearchlite_search'
        ) {
            return 'Ajax Search Lite';
        }

        // Monta Checkout service action
        if (
            apbct_is_plugin_active('montapacking-checkout-woocommerce-extension/montapacking-checkout.php') &&
            Post::get('action') === 'monta_shipping_options'
        ) {
            return 'Monta Checkout';
        }

        // skip kali forms
        if (
            apbct_is_plugin_active('kali-forms/kali-forms.php') &&
            (
                Post::get('action') === 'kaliforms_form_process'
            )
        ) {
            return 'kaliforms_form_process_skip';
        }

        // skip learndash-elementor
        if (
            apbct_is_plugin_active('learndash-elementor/learndash-elementor.php') &&
            (
                Post::get('course_id') !== '' && Post::get('lesson_id') !== ''
            )
        ) {
            return 'learndash-elementor';
        }

        // skip klaviyo coupon service request
        if (
            apbct_is_plugin_active('klaviyo-coupons/kl-coupons.php') &&
            Post::get('action') === 'klc_generate_coupon'
        ) {
            return 'klc_generate_coupon';
        }

        // skip Super WooCommerce Product Filter
        if (
            apbct_is_plugin_active('super-woocommerce-product-filter/super-woocommerce-product-filter.php') &&
            Post::get('action') === 'swpf_get_product_list'
        ) {
            return 'Super WooCommerce Product Filter';
        }

        // skip masteriyo_login LMS
        if (
            (
                apbct_is_plugin_active('learning-management-system/lms.php') ||
                apbct_is_plugin_active('learning-management-system-pro/lms.php')
            ) &&
            Post::get('action') === 'masteriyo_login'
        ) {
            return 'masteriyo_login LMS';
        }

        if (
            Post::get('action') === 'ct_check_internal' &&
            $apbct->settings['forms__check_internal'] &&
            class_exists('Cleantalk\Antispam\Integrations\CleantalkInternalForms')
        ) {
            return 'APBCT Internal Forms Class';
        }

        // skip tourmaster order
        if ( apbct_is_plugin_active('tourmaster/tourmaster.php') &&
            Post::get('action') === 'tourmaster_payment_template'
        ) {
            return 'tourmaster_payment_template';
        }
        // skip Broken Link Notifier service action
        if (
            apbct_is_plugin_active('broken-link-notifier/broken-link-notifier.php') &&
            Post::get('action') === 'blnotifier_blinks'
        ) {
            return 'Broken Link Notifier service action';
        }

        // skip WP Rocket service requests
        if (
            apbct_is_plugin_active('wp-rocket/wp-rocket.php') &&
            (
                Get::get('wpr_imagedimensions') ||
                Post::get('wpr_imagedimensions') ||
                Post::get('action') === 'rocket_beacon'
            )
        ) {
            return 'WP Rocket service requests';
        }
        // skip Check email before POST request
        if (
                Post::get('action') === 'apbct_email_check_exist_post'
        ) {
            return 'apbct_email_check_exist_post_skip';
        }
        // BuddyPress has the direct integration
        if ( apbct_is_plugin_active('buddypress/bp-loader.php') && Post::get('action') === 'messages_send_message' ) {
            return 'buddypress_messages_send_message';
        }

        // skip Force Protection check bot
        if (Post::get('action') === 'apbct_force_protection_check_bot') {
            return 'apbct_force_protection_check_bot_skip';
        }

        // TEvolution checking email existence need to be excluded
        if (
            apbct_is_plugin_active('Tevolution/templatic.php') &&
            Post::get('action') === 'tmpl_ajax_check_user_email'
        ) {
            return 'tevolution email exitence';
        }

        // skip listeo ajax registeration
        if (
            apbct_is_plugin_active('listeo-core/listeo-core.php') &&
            Post::get('action') === 'listeoajaxregister'
        ) {
            return 'listeo ajax register';
        }

        // skip BravePopUp Pro - have direct integration
        if (
            apbct_is_plugin_active('bravepopup-pro/index.php') &&
            Post::get('action') === 'bravepop_form_submission' &&
            check_ajax_referer('brave-ajax-form-nonce', 'security')
        ) {
            return 'BravePopUp Pro';
        }
        // Exclusion of hooks from the Avada theme for the forms of the fusion form builder
        if (
            (apbct_is_theme_active('Avada') || apbct_is_theme_active('Avada Child')) &&
            Post::get('action') === 'fusion_form_submit_form_to_database_email' ||
            Post::get('action') === 'fusion_form_submit_form_to_email' ||
            Post::get('action') === 'fusion_form_submit_ajax'
        ) {
            return 'fusion_form/avada_theme skip';
        }

        // skip Newsletter - has direct integration
        if (
            apbct_is_plugin_active('newsletter/plugin.php') &&
            Request::getString('action') === 'tnp'
        ) {
            return 'Newsletter';
        }

        // skip ChatyContactForm - has direct integration
        if (
            apbct_is_plugin_active('chaty/cht-icons.php') &&
            Request::getString('action') === 'chaty_front_form_save_data'
        ) {
            return 'ChatyContactForm';
        }

        // skip Login/Signup Popup - has direct integration
        if (
            apbct_is_plugin_active('easy-login-woocommerce/xoo-el-main.php') &&
            Request::getString('action') === 'xoo_el_form_action'
        ) {
            return 'Login/Signup Popup';
        }

        // skip QuickCal - has direct integration
        if (
            apbct_is_plugin_active('quickcal/quickcal.php') &&
            Request::getString('action') === 'booked_add_appt'
        ) {
            return 'QuickCal';
        }
    } else {
        /*****************************************/
        /*  Here is non-ajax requests skipping   */
        /*****************************************/
        //Skip RegistrationMagic main request - has own integration
        if (
            apbct_is_plugin_active('custom-registration-form-builder-with-submission-manager/registration_magic.php') &&
            isset($_POST['rm_cond_hidden_fields'])
        ) {
            return 'RegistrationMagic main request';
        }
        // WC payment APIs
        if ( apbct_is_plugin_active('woocommerce/woocommerce.php') &&
             apbct_is_in_uri('wc-api=2checkout_ipn_convert_plus') ) {
            return 'wc-payment-api';
        }
        // BuddyPress edit profile checking skip
        if ( apbct_is_plugin_active('buddypress/bp-loader.php') &&
             array_key_exists('profile-group-edit-submit', $_POST) ) {
            return 'buddypress_profile_edit';
        }
        // UltimateMember password reset skip
        if ( apbct_is_plugin_active('ultimate-member/ultimate-member.php') &&
            TT::toInt(Post::get('_um_password_reset')) === 1 ) {
            return 'ultimatemember_password_reset';
        }
        // UltimateMember password reset skip
        if ( apbct_is_plugin_active('gravityformspaypal/paypal.php') &&
             (apbct_is_in_uri('page=gf_paypal_ipn') || apbct_is_in_uri('callback=gravityformspaypal')) ) {
            return 'gravityformspaypal_processing_skipped';
        }
        // MyListing theme service requests skip
        if ( (apbct_is_theme_active('My Listing Child') || apbct_is_theme_active('My Listing')) &&
             Get::get('mylisting-ajax') === '1' ) {
            return 'mylisting_theme_service_requests_skip';
        }
        // HappyForms skip every requests. HappyForms have the direct integration
        if ( (apbct_is_plugin_active('happyforms-upgrade/happyforms-upgrade.php') ||
              apbct_is_plugin_active('happyforms/happyforms.php')) &&
             (Post::get('happyforms_message_nonce') !== '') ||
             (Post::get('action') === 'happyforms_message' && Post::get('happyforms_form_id') !== '')
        ) {
            return 'happyform_skipped';
        }
        // Essentials addons for elementor - light and pro
        if (
            (apbct_is_plugin_active('essential-addons-for-elementor-lite/essential_adons_elementor.php') ||
             apbct_is_plugin_active('essential-addons-elementor/essential_adons_elementor.php')) &&
            (Post::get('eael-login-submit') !== '' && Post::get('eael-user-login') !== '') ) {
            return 'eael_login_skipped';
        }
        // Autonami Marketing Automations service request
        if ( apbct_is_rest() && Post::get('automation_id') !== '' && Post::get('unique_key') !== '' ) {
            return 'autonami-rest';
        }
        // Formidable skip - this is the direct integration
        if ( apbct_is_plugin_active('formidable/formidable.php') &&
             (Post::get('frm_action') === 'update' ||
             (Post::get('frm_action') === 'create' &&
             $apbct->settings['forms__contact_forms_test'] == 1 &&
             Post::get('form_id') !== '' &&
             Post::get('form_key') !== ''))
        ) {
            return 'formidable_skip';
        }
        // WC payment APIs
        if ( apbct_is_plugin_active('woocommerce/woocommerce.php') &&
             apbct_is_in_uri('wc-ajax=iwd_opc_update_order_review') ) {
            return 'cartflows_save_cart';
        }
        // Vault Press (JetPack) plugin service requests
        if (
            Post::get('do_backups') !== '' &&
            Get::get('vaultpress') === 'true' &&
            Get::get('action') !== '' &&
            preg_match('%Automattic\/VaultPress\/\d.\d$%', TT::toString(Server::get('HTTP_USER_AGENT')))
        ) {
            return 'Vault Press service actions';
        }
        // GridBuilder plugin service requests
        if (
            apbct_is_plugin_active('wp-grid-builder/wp-grid-builder.php') &&
            Post::get('wpgb') !== '' &&
            Get::get('wpgb-ajax') !== ''
        ) {
            return 'GridBuilder service actions';
        }
        // WSForms - this is the direct integration and service requests skip
        if (
            apbct_is_plugin_active('ws-form-pro/ws-form.php') &&
            ( ( Post::get('wsf_form_id') !== '' && Post::get('wsf_post_id') !== '' ) ||
            TT::toInt(Post::get('wsffid')) > 0 )
        ) {
            return 'WSForms skip';
        }
        // Checkout For WC - service requests skip
        if (
            apbct_is_plugin_active('checkout-for-woocommerce/checkout-for-woocommerce.php') &&
            ( ( apbct_is_in_uri('wc-ajax=update_checkout') && wp_verify_nonce(TT::toString(Post::get('security')), 'update-order-review') ) ||
            apbct_is_in_uri('wc-ajax=account_exists') ||
            apbct_is_in_uri('wc-ajax=complete_order') )
        ) {
            return 'Checkout For WC skip';
        }
        //Restrict Content Pro - Login Form
        if (
            apbct_is_plugin_active('restrict-content-pro/restrict-content-pro.php') &&
            Post::equal('rcp_action', 'login') &&
            Post::get('rcp_user_login') ||
            Post::get('rcp_user_pass')
        ) {
            return 'Restrict Content Pro Login Form skip';
        }
        // APBCT service actions
        if (
            apbct_is_plugin_active('cleantalk-spam-protect/cleantalk.php') &&
            apbct_is_in_uri('cleantalk-antispam/v1/check_email_before_post') ||
            apbct_is_in_uri('cleantalk-antispam/v1/check_email_exist_post')
        ) {
            return 'APBCT service actions';
        }

        // JQueryMigrate plugin
        if (
            apbct_is_plugin_active('enable-jquery-migrate-helper/enable-jquery-migrate-helper.php') &&
            Post::get('action') === 'jquery-migrate-log-notice'
        ) {
            return 'JQueryMigrate plugin service actions';
        }

        /** Skip Optima Express login */
        if (
            apbct_is_plugin_active('optima-express/iHomefinder.php') &&
            Post::get('actionType') === 'login' &&
            !empty(Post::get('username'))
        ) {
            return 'Skip Optima Express login';
        }

        /** Skip Optima Express update */
        if (
            apbct_is_plugin_active('optima-express/iHomefinder.php') &&
            Post::get('actionType') === 'update' &&
            !empty(Post::get('firstName'))
        ) {
            return 'Skip Optima Express update';
        }

        //Skip AutomateWoo service request
        if (
            apbct_is_plugin_active('automatewoo/automatewoo.php') &&
            ( Get::get('aw-ajax') === 'capture_email' ||
            Get::get('aw-ajax') === 'capture_checkout_field' )
        ) {
            return 'AutomateWoo skip';
        }

        //Skip Billige-teste theme 1st step checkout request
        if (
            apbct_is_theme_active('bilige-teste') &&
            Post::get('bt_checkout_data') == true &&
            Post::get('email') &&
            Post::get('unkey')
        ) {
            return 'Billige-teste theme 1st step checkout request';
        }

        // Skip WS Forms Pro request - have the direct integration
        if (
            apbct_is_plugin_active('ws-form-pro/ws-form.php') &&
            Post::get('wsf_form_id') &&
            Post::get('wsf_post_mode') === 'submit'
        ) {
            return 'WS Forms Pro request';
        }

        // Skip Indeed Ultimate Membership Pro - have the direct integration
        if (
            apbct_is_plugin_active('indeed-membership-pro/indeed-membership-pro.php') &&
            wp_verify_nonce(TT::toString(Post::get('ihc_user_add_edit_nonce')), 'ihc_user_add_edit_nonce')
        ) {
            return 'Indeed Ultimate Membership Pro - have the direct integration';
        }

        // Plugin Name: OptimizeCheckouts - skip fields checks
        if (
            apbct_is_plugin_active('op-cart/op-checkouts.php') &&
            ( apbct_is_in_uri('opc/v1/cart/recalculate') ||
            apbct_is_in_uri('opc/v1/cart/update-payment-method') )
        ) {
            return 'Plugin Name: OptimizeCheckouts skip fields checks';
        }

        // Plugin Name: WooCommerce Product Enquiry Premium - have the direct integration
        if (
            apbct_is_plugin_active('product-enquiry-pro/woocommerce-product-enquiry-pro.php') &&
            Post::get('mcg_enq_submit') &&
            Post::get('product_id')

        ) {
            return 'Plugin Name: WooCommerce Product Enquiry Premium - have the direct integration';
        }

        // WP Discuz skip service requests. The plugin have the direct integration
        if ( apbct_is_plugin_active('wpdiscuz/class.WpdiscuzCore.php') &&
            strpos(TT::toString(Post::get('action')), 'wpdCheckNotificationType') !== false ) {
            return 'no_ajax_wpdCheckNotificationType';
        }

        // Plugin Name: Profile Builder
        if (
            apbct_is_plugin_active('profile-builder/index.php') &&
            Post::get('action') === 'edit_profile'
        ) {
            return 'Plugin Name: Profile Builder; ajax action wppb_conditional_logic';
        }

        // CoBlocks. The plugin have the direct integration
        if (
            apbct_is_plugin_active('coblocks/class-coblocks.php') &&
            TT::toString(Post::get('action')) === 'coblocks-form-submit'
        ) {
            return 'Plugin Name: CoBlocks - have the direct integration';
        }
    }

    // WP Fusion Abandoned Cart Addon
    if ( apbct_is_plugin_active('wp-fusion-abandoned-cart/wp-fusion-abandoned-cart.php') &&
        (Post::get('action') === 'wpf_abandoned_cart' || Post::get('action') === 'wpf_progressive_update_cart')
    ) {
        return 'WP Fusion Abandoned Cart Addon service action';
    }

    // Elementor pro forms has a direct integration
    if (apbct_is_plugin_active('elementor-pro/elementor-pro.php')) {
        if ( Post::get('action') === 'elementor_pro_forms_send_form') {
            if (
                Post::get('post_id') !== '' &&
                Post::get('form_id') !== '' &&
                Post::get('cfajax') === ''
            ) {
                return 'Elementor pro forms ajax';
            }
        } elseif (
            Post::get('queried_id') !== '' &&
            Post::get('post_id') !== '' &&
            Post::get('form_id') !== '' &&
            Post::get('cfajax') === ''
        ) {
            return 'Elementor pro forms non ajax';
        }
    }

    //Skip wforms because of direct integration
    if (
        (apbct_is_plugin_active('wpforms/wpforms.php') || apbct_is_plugin_active('wpforms-lite/wpforms.php')) &&
        (Post::get('wpforms') || Post::get('actions') === 'wpforms_submit')
    ) {
        return 'wp_forms';
    }

    //Plugin Name: Kali Forms
    if (
        apbct_is_plugin_active('product-enquiry-pro/kali-forms.php') ||
        apbct_is_plugin_active('product-enquiry-pro/kali-forms-pro.php')
    ) {
        if ( Post::get('action') === 'kaliforms_form_process' ) {
            return 'Plugin Name: Kali Forms - have the direct integration';
        }

        if ( Post::get('action') === 'kaliforms_preflight' ) {
            return 'Plugin Name: Kali Forms - service action skip';
        }
    }

    //nobletitle-calc
    if (
        apbct_is_plugin_active('nobletitlecalc/nobletitle-calc.php')
        && Post::get('Calculate')
        && Post::get('coverageType')
    ) {
        return 'nobletitle-calc';
    }

    // Otter Blocks have the direct integration
    if (
        apbct_is_plugin_active('otter-blocks/otter-blocks.php') &&
        Post::get('form_data')
    ) {
        return 'Otter Blocks';
    }

    return false;
}

/**
 * Checking if the request must be skipped but logged by exception flag.
 *
 * @return false|string
 */
function apbct_is_exception_arg_request()
{
    if (
        apbct_is_plugin_active('wc-dynamic-pricing-and-discounts/wc-dynamic-pricing-and-discounts.php') &&
        Post::get('action') === 'rp_wcdpd_promotion_countdown_timer_update'
    ) {
        return 'WooCommerce Dynamic Pricing & Discounts service actions';
    }
    return false;
}

/**
 * Checking availability of the handlers and return ajax type
 *
 * @return string|false
 */
function apbct_settings__get_ajax_type()
{
    global $apbct;

    //force ajax route type if constant is defined and compatible
    if ($apbct->service_constants->set_ajax_route_type->isDefined()
        && in_array($apbct->service_constants->set_ajax_route_type->getValue(), array('rest','admin_ajax'))
    ) {
        return $apbct->service_constants->set_ajax_route_type->getValue();
    }

    // Check rest availability
    // Getting WP REST nonce from the public side
    $frontend_body = Helper::httpRequest(get_option('home'));
    $localize = null;

    if ( is_string($frontend_body) ) {
        preg_match_all('@const ctPublicFunctions.*{(.*)}@', $frontend_body, $matches);
        if ( isset($matches[1][0]) ) {
            $localize = json_decode('{' . $matches[1][0] . '}', true);
        }
    }

    if ( is_array($localize) && isset($localize['_rest_nonce']) ) {
        $rc_params = array(
            'spbc_remote_call_token' => md5($apbct->api_key),
            'spbc_remote_call_action' => 'rest_check',
            'plugin_name' => 'apbct',
            '_rest_nonce' => $localize['_rest_nonce']
        );
        $res = json_decode(TT::toString(Helper::httpRequest(get_option('home'), $rc_params)), true);
        if ( is_array($res) && isset($res['success']) ) {
            return 'rest';
        }
    } else {
        $res_rest = Helper::httpRequestGetResponseCode(esc_url(apbct_get_rest_url()));
        $res_body = Helper::httpRequestGetContent(esc_url(apbct_get_rest_url()));

        if ( $res_rest == 200 && Helper::isJson(TT::toString($res_body)) ) {
            return 'rest';
        }
    }

    // Check WP ajax availability
    $res_ajax = Helper::httpRequestGetResponseCode(admin_url('admin-ajax.php'));
    if ( $res_ajax == 400 ) {
        return 'admin_ajax';
    }

    return false;
}

function apbct__get_cookie_prefix()
{
    if ( defined('CLEANTALK_COOKIE_PREFIX') ) {
        return preg_replace('/[^A-Za-z1-9_-]/', '', CLEANTALK_COOKIE_PREFIX);
    }
    return '';
}

function apbct__is_rest_api_request()
{
    if (isset($_SERVER['REQUEST_URI'])) {
        $rest_url_only_path = apbct_get_rest_url_only_path();
        return strpos(TT::toString($_SERVER['REQUEST_URI']), '/wp-json/') !== false ||
        ($rest_url_only_path !== 'index.php' &&
        strpos(TT::toString($_SERVER['REQUEST_URI']), $rest_url_only_path) !== false);
    }

    return false;
}

/**
 * @return bool
 */
function apbct__is_wp_rocket_preloader_request()
{
    return (
        isset($_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR'], $_SERVER['SERVER_ADDR']) &&
        strpos($_SERVER['HTTP_USER_AGENT'], 'WP Rocket/Preload') !== false &&
        $_SERVER['REMOTE_ADDR'] === $_SERVER['SERVER_ADDR']
    );
}

/**
 * Generates MD5 hash for email encoder pass key
 *
 * @return string
 */
function apbct_get_email_encoder_pass_key()
{
    global $apbct;

    return md5(Helper::ipGet() . $apbct->api_key . 'email_encoder');
}
