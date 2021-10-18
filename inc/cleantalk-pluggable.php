<?php

use Cleantalk\Variables\Get;
use Cleantalk\Variables\Post;
use Cleantalk\Variables\Server;

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

    return $user ? $user : $current_user;
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

    $scheme     = $cookie_elements['scheme'];
    $username   = $cookie_elements['username'];
    $hmac       = $cookie_elements['hmac'];
    $token      = $cookie_elements['token'];
    $expiration = $cookie_elements['expiration'];

    // Allow a grace period for POST and Ajax requests
    $expired = apbct_is_ajax() || apbct_is_post()
        ? $expiration + HOUR_IN_SECONDS
        : $cookie_elements['expiration'];

    // Quick check to see if an honest cookie has expired
    if ( $expired >= time() ) {
        $user = apbct_wp_get_user_by('login', $username);
        if ( $user ) {
            $pass_frag = substr($user->user_pass, 8, 4);
            $key       = apbct_wp_hash($username . '|' . $pass_frag . '|' . $expiration . '|' . $token, $scheme);
            // If ext/hash is not present, compat.php's hash_hmac() does not support sha256.
            $algo = function_exists('hash') ? 'sha256' : 'sha1';
            $hash = hash_hmac($algo, $username . '|' . $expiration . '|' . $token, $key);
            if ( hash_equals($hash, $hmac) ) {
                $sessions = get_user_meta($user->ID, 'session_tokens', true);
                $sessions = is_array($sessions) ? current($sessions) : $sessions;
                if ( is_array($sessions) ) {
                    if ( is_int($sessions['expiration']) && $sessions['expiration'] > time() ) {
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

    if ( is_ssl() && isset($_SERVER['SERVER_NAME']) ) {
        // If the current host is the same as the REST URL host, force the REST URL scheme to HTTPS.
        if ( parse_url(get_home_url($blog_id), PHP_URL_HOST) === $_SERVER['SERVER_NAME'] ) {
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
            apbct_get_server_variable('HTTP_X_REQUESTED_WITH') &&
            strtolower(apbct_get_server_variable('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest'
        ) || // by Request type
        ! empty($_POST['quform_ajax']) || // special. QForms
        ! empty($_POST['iphorm_ajax']); // special. IPHorm
}

/**
 * Checks if the request is REST
 *
 * @return boolean
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

/*
 * GETTING SERVER VARIABLES BY VARIOUS WAYS
 */
function apbct_get_server_variable($server_variable_name)
{
    $var_name = strtoupper($server_variable_name);

    if ( function_exists('filter_input') ) {
        $value = filter_input(INPUT_SERVER, $var_name);
    }

    if ( empty($value) ) {
        $value = isset($_SERVER[$var_name]) ? $_SERVER[$var_name] : '';
    }

    // Convert to upper case for REQUEST_METHOD
    if ( in_array($server_variable_name, array('REQUEST_METHOD')) ) {
        $value = strtoupper($value);
    }

    // Convert HTML chars for HTTP_USER_AGENT, HTTP_USER_AGENT, SERVER_NAME
    if ( in_array($server_variable_name, array('HTTP_USER_AGENT', 'HTTP_USER_AGENT', 'SERVER_NAME')) ) {
        $value = htmlspecialchars($value);
    }

    return $value;
}

function apbct_is_post()
{
    return apbct_get_server_variable('REQUEST_METHOD') === 'POST';
}

function apbct_is_get()
{
    return apbct_get_server_variable('REQUEST_METHOD') === 'GET';
}

function apbct_is_in_referer($str)
{
    return stripos(apbct_get_server_variable('HTTP_REFERER'), $str) !== false;
}

function apbct_is_in_uri($str)
{
    return stripos(apbct_get_server_variable('REQUEST_URI'), $str) !== false;
}

/*
 * Checking if current request is a cron job
 * Support for wordpress < 4.8.0
 *
 * @return bool
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
    $uri = parse_url(Server::get('REQUEST_URI'));

    return $uri && isset($uri['query']) && strpos($uri['query'], 'customize_changeset_uuid') !== false;
}


/**
 * Checking if the request must be skipped.
 *
 * @param $ajax bool The current request is the ajax request?
 *
 * @return bool|string   false or request name for logging
 */
function apbct_is_skip_request($ajax = false)
{
    /* !!! Have to use more than one factor to detect the request - is_plugin active() && $_POST['action'] !!! */
    //@ToDo Implement direct integration checking - if have the direct integration will be returned false

    if ( $ajax ) {
        /*****************************************/
        /*    Here is ajax requests skipping     */
        /*****************************************/

        // Paid Memberships Pro - Login Form
        if (
            apbct_is_plugin_active('paid-memberships-pro/paid-memberships-pro.php') &&
            Post::get('rm_slug') === 'rm_login_form' &&
            Post::get('rm_form_sub_id')
        ) {
            return 'paid_memberships_pro__login_form';
        }

        // Thrive Ultimatum
        if (
            apbct_is_plugin_active('thrive-ultimatum/thrive-ultimatum.php') &&
            Post::get('action') === 'tve_dash_front_ajax'
        ) {
            return 'thrive-ultimatum__links_from_email';
        }

        // wpDiscuz - Online Users Addon for wpDiscuz
        if (
            apbct_is_plugin_active('wpdiscuz-online-users/wpdiscuz-ou.php') &&
            Post::get('action') === 'wouPushNotification'
        ) {
            return 'wpdiscuz_online_users__push_notification';
        }

        // Bookly Plugin admin actions skip
        if ( apbct_is_plugin_active('bookly-responsive-appointment-booking-tool/main.php') &&
             isset($_POST['action']) &&
             strpos($_POST['action'], 'bookly') !== false &&
             is_admin() ) {
            return 'bookly_pro_update_staff_advanced';
        }
        // Youzier login form skip
        if ( apbct_is_plugin_active('youzer/youzer.php') &&
             isset($_POST['action']) &&
             $_POST['action'] === 'yz_ajax_login' ) {
            return 'youzier_login_form';
        }
        // Youzify login form skip
        if ( apbct_is_plugin_active('youzify/youzify.php') &&
             isset($_POST['action']) &&
             $_POST['action'] === 'youzify_ajax_login' ) {
            return 'youzify_login_form';
        }
        // InJob theme lost password skip
        if ( apbct_is_plugin_active('iwjob/iwjob.php') &&
             isset($_POST['action']) &&
             $_POST['action'] === 'iwj_lostpass' ) {
            return 'injob_theme_plugin';
        }
        // Divi builder skip
        if ( apbct_is_theme_active('Divi') &&
             isset($_POST['action']) &&
             ($_POST['action'] === 'save_epanel' || $_POST['action'] === 'et_fb_ajax_save') ) {
            return 'divi_builder_skip';
        }
        // Email Before Download plugin https://wordpress.org/plugins/email-before-download/ action skip
        if ( apbct_is_plugin_active('email-before-download/email-before-download.php') &&
             isset($_POST['action']) &&
             $_POST['action'] === 'ebd_inline_links' ) {
            return 'ebd_inline_links';
        }
        // WP Discuz skip service requests. The plugin have the direct integration
        if ( apbct_is_plugin_active('wpdiscuz/class.WpdiscuzCore.php') &&
             isset($_POST['action']) &&
             strpos($_POST['action'], 'wpd') !== false ) {
            return 'ebd_inline_links';
        }
        // Exception for plugin https://ru.wordpress.org/plugins/easy-login-woocommerce/ login form
        if (
            apbct_is_plugin_active('easy-login-woocommerce/xoo-el-main.php') &&
            Post::get('_xoo_el_form') === 'login'
        ) {
            return 'xoo_login';
        }
        // Emails & Newsletters with Jackmail: skip all admin-side actions
        if (
            apbct_is_plugin_active('jackmail-newsletters/jackmail-newsletters.php') &&
            is_admin() &&
            strpos(Server::get('HTTP_REFERER'), 'jackmail_') !== false
        ) {
            return 'jackmail_admin_actions';
        }
        // Newspaper theme login form
        if ( apbct_is_theme_active('Newspaper') &&
             isset($_POST['action']) &&
             ($_POST['action'] == 'td_mod_login' || $_POST['action'] == 'td_mod_remember_pass') ) {
            return 'Newspaper_theme_login_form';
        }
        // Save abandoned cart checking skip
        if ( apbct_is_plugin_active('woo-save-abandoned-carts/cartbounty-abandoned-carts.php') &&
             Post::get('action') === 'cartbounty_save' ) {
            return 'cartbounty_save';
        }
        // SUMODISCOUNT discout request skip
        if ( apbct_is_plugin_active('sumodiscounts/sumodiscounts.php') &&
             Post::get('action') === 'fp_apply_discount_for_first_purchase' ) {
            return 'fp_apply_discount_for_first_purchase';
        }
        // WP eMember login form skip
        if ( apbct_is_plugin_active('wp-eMember/wp_eMember.php') &&
             Post::get('action') === 'emember_ajax_login' ) {
            return 'emember_ajax_login';
        }
        // Avada theme saving settings
        if ( apbct_is_theme_active('Avada') &&
             Post::get('action') === 'fusion_options_ajax_save' ) {
            return 'Avada_theme_saving_settings';
        }
        // Formidable skip - this is the durect integration
        if ( apbct_is_plugin_active('formidable/formidable.php') &&
             Post::get('action') === 'frm_entries_update' ) {
            return 'formidable_skip';
        }
        // Artbees Jupiter theme saving settings
        if ( Post::get('action') === 'mk_theme_save' && strpos(get_template(), 'jupiter') !== false ) {
            return 'artbees_jupiter_6_skip';
        }
        // fix conflict with wiloke theme and unknown plugin, that removes standard authorization cookies
        if ( Post::get('action') === 'wiloke_themeoptions_ajax_save' && apbct_is_theme_active('wilcity') ) {
            return 'wiloke_themeoptions_ajax_save_skip';
        }
        // Essentials addons for elementor - light and pro
        if (
            (apbct_is_plugin_active('essential-addons-for-elementor-lite/essential_adons_elementor.php') ||
             apbct_is_plugin_active('essential-addons-elementor/essential_adons_elementor.php')) &&
            (Post::get('eael-login-submit') !== '' && Post::get('eael-user-login') !== '') ) {
            return 'eael_login_skipped';
        }
        // WPForms check restricted email skipped
        if (
            (apbct_is_plugin_active('wpforms/wpforms.php')) &&
            (Post::get('action') === 'wpforms_restricted_email' && Post::get('token') !== '')
        ) {
            return 'wpforms_check_restricted_email';
        }
        // FluentForm multistep skip
        if (
            (apbct_is_plugin_active('fluentformpro/fluentformpro.php') ||  apbct_is_plugin_active('fluentform/fluentform.php')) &&
            Post::get('action') === 'active_step'
        ) {
            return 'fluentform_skip';
        }

        // W2DC - https://codecanyon.net/item/web-20-directory-plugin-for-wordpress/6463373
        if ( apbct_is_plugin_active('w2dc/w2dc.php') &&
             isset($_POST['action']) &&
             $_POST['action'] === 'vp_w2dc_ajax_vpt_option_save' &&
             is_admin() ) {
            return 'w2dc_skipped';
        }
        if ( (apbct_is_plugin_active('elementor/elementor.php') || apbct_is_plugin_active('elementor-pro/elementor-pro.php')) &&
             isset($_POST['actions_save_builder_action']) &&
             $_POST['actions_save_builder_action'] === 'save_builder' &&
             is_admin() ) {
            return 'elementor_skip';
        }
        // Enfold theme saving settings
        if ( apbct_is_theme_active('Enfold') &&
             Post::get('action') === 'avia_ajax_save_options_page' ) {
            return 'Enfold_theme_saving_settings';
        }
        //SiteOrigin pagebuilder skip save
        if (
            apbct_is_plugin_active('siteorigin-panels/siteorigin-panels.php') &&
            Post::get('action') === 'save-widget'
        ) {
            return 'SiteOrigin pagebuilder';
        }
        //Skip classfields email check
        if (
            (apbct_is_theme_active('classified-child') || apbct_is_theme_active('classified'))
            && Post::get('action') === 'tmpl_ajax_check_user_email'
        ) {
            return 'Classified checkemail';
        }
    } else {
        /*****************************************/
        /*  Here is non-ajax requests skipping   */
        /*****************************************/
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
             isset($_POST['_um_password_reset']) && $_POST['_um_password_reset'] == 1 ) {
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
             (Post::get('happyforms_message_nonce') !== '') ) {
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
        //Skip wforms because of direct integration
        if (
            apbct_is_plugin_active('wpforms/wpforms.php') &&
            (Post::get('wpforms') || Post::get('actions') === 'wpforms_submit')
        ) {
            return 'wp_forms';
        }
        // Formidable skip - this is the durect integration
        if ( apbct_is_plugin_active('formidable/formidable.php') &&
             Post::get('frm_action') === 'update' ) {
            return 'formidable_skip';
        }
        // WC payment APIs
        if ( apbct_is_plugin_active('woocommerce/woocommerce.php') &&
             apbct_is_in_uri('wc-ajax=iwd_opc_update_order_review') ) {
            return 'cartflows_save_cart';
        }
    }

    return false;
}
