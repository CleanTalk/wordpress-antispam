<?php

use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\RemoteCalls;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Request;
use Cleantalk\ApbctWP\Variables\Server;

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

    if (!is_array($cookie_elements) || empty($cookie_elements)) {
        return false;
    }

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
            strtolower(Server::get('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest'
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
    return stripos(Server::get('HTTP_REFERER'), $str) !== false;
}

function apbct_is_in_uri($str)
{
    return stripos(Server::get('REQUEST_URI'), $str) !== false;
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
    $uri = parse_url(Server::get('REQUEST_URI'));

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
 *
 * @return bool|string   false or request name for logging
 */
function apbct_is_skip_request($ajax = false)
{
    /* !!! Have to use more than one factor to detect the request - is_plugin active() && $_POST['action'] !!! */
    //@ToDo Implement direct integration checking - if have the direct integration will be returned false

    global $apbct;

    if ( RemoteCalls::check() ) {
        return 'CleanTalk RemoteCall request.';
    }

    if (
        Post::get('action') === 'apbct_alt_session__save__AJAX' &&
        wp_verify_nonce(Post::get('_ajax_nonce'), 'ct_secret_stuff')
    ) {
        return 'CleanTalk AltCookies request.';
    }

    if ( is_admin() && ! $ajax ) {
        return 'Admin side request.';
    }

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
            strpos(Post::get('action'), 'bookly') !== false &&
            is_admin() ) {
            return 'bookly_pro_update_staff_advanced';
        }
        // Youzier login form skip
        if ( apbct_is_plugin_active('youzer/youzer.php') &&
            Post::get('action') === 'yz_ajax_login' ) {
            return 'youzier_login_form';
        }
        // Youzify login form skip
        if ( apbct_is_plugin_active('youzify/youzify.php') &&
            Post::get('action') === 'youzify_ajax_login' ) {
            return 'youzify_login_form';
        }
        // InJob theme lost password skip
        if ( apbct_is_plugin_active('iwjob/iwjob.php') &&
            Post::get('action') === 'iwj_lostpass' ) {
            return 'injob_theme_plugin';
        }
        // Divi builder skip
        if ( apbct_is_theme_active('Divi') &&
            (Post::get('action') === 'save_epanel' || Post::get('action') === 'et_fb_ajax_save') ) {
            return 'divi_builder_skip';
        }
        // Email Before Download plugin https://wordpress.org/plugins/email-before-download/ action skip
        if ( apbct_is_plugin_active('email-before-download/email-before-download.php') &&
            Post::get('action') === 'ebd_inline_links' ) {
            return 'ebd_inline_links';
        }
        // WP Discuz skip service requests. The plugin have the direct integration
        if ( apbct_is_plugin_active('wpdiscuz/class.WpdiscuzCore.php') &&
            strpos(Post::get('action'), 'wpd') !== false ) {
            return 'WpdiscuzCore';
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
            (Post::get('action') === 'td_mod_login' || Post::get('action') === 'td_mod_remember_pass') ) {
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
            apbct_is_plugin_active('wpforms/wpforms.php') &&
            Post::get('action') === 'wpforms_restricted_email'
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
             Post::get('action') === 'vp_w2dc_ajax_vpt_option_save' &&
             is_admin() ) {
            return 'w2dc_skipped';
        }
        if ( (apbct_is_plugin_active('elementor/elementor.php') || apbct_is_plugin_active('elementor-pro/elementor-pro.php')) &&
             Post::get('actions_save_builder_action') === 'save_builder' &&
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
        if (
            (apbct_is_plugin_active('uncanny-toolkit-pro/uncanny-toolkit-pro.php') || apbct_is_plugin_active('uncanny-learndash-toolkit'))
            && Post::get('action') === 'ult-forgot-password'
        ) {
            return 'Uncanny Toolkit';
        }
        if (
            apbct_is_plugin_active('popup-builder/popup-builder.php') &&
            Post::get('action') === 'sgpb_send_to_open_counter'
        ) {
            return 'Popup builder service actions';
        }
        if (
            apbct_is_plugin_active('security-malware-firewall/security-malware-firewall.php') &&
            Post::get('action') === 'spbc_get_authorized_users'
        ) {
            return 'SPBCT service actions';
        }
        // APBCT service actions
        if (
            apbct_is_plugin_active('cleantalk-spam-protect/cleantalk.php') &&
            ( Post::get('action') === 'apbct_get_pixel_url' && wp_verify_nonce(Post::get('_ajax_nonce'), 'ct_secret_stuff') )
        ) {
            return 'APBCT service actions';
        }
        // Entry Views plugin service requests
        if (
            apbct_is_plugin_active('entry-views/entry-views.php') &&
            Post::get('action') === 'entry_views' &&
            Post::get('post_id') !== ''
        ) {
            return 'Entry Views service actions';
        }
        // Woo Gift Wrapper plugin service requests
        if (
            apbct_is_plugin_active('woocommerce-gift-wrapper/woocommerce-gift-wrapper.php') &&
            Post::get('action') === 'wcgwp_remove_from_cart'
        ) {
            return 'Woo Gift Wrapper service actions';
        }
        // iThemes Security plugin service requests
        if (
            apbct_is_plugin_active('better-wp-security/better-wp-security.php') &&
            Post::get('action') === 'itsec-login-interstitial-ajax'
        ) {
            return 'iThemes Security service actions';
        }
        // Microsoft Azure Storage plugin service requests
        if (
            apbct_is_plugin_active('windows-azure-storage/windows-azure-storage.php') &&
            Post::get('action') === 'get-azure-progress'
        ) {
            return 'Microsoft Azure Storage service actions';
        }
        // AdRotate plugin service requests
        if (
            apbct_is_plugin_active('adrotate/adrotate.php') &&
            Post::get('action') === 'adrotate_impression' &&
            Post::get('track') !== ''
        ) {
            return 'AdRotate service actions';
        }
        // WP Booking System Premium
        if (
            (apbct_is_plugin_active('wp-booking-system-premium/index.php') &&
            Post::get('action') === 'wpbs_calculate_pricing') ||
            Post::get('action') === 'wpbs_validate_date_selection'
        ) {
            return 'WP Booking System Premium';
        }
        // GiveWP - having the direct integration
        if (
            (apbct_is_plugin_active('give/give.php') &&
            Post::get('action') === 'give_process_donation')
        ) {
            return 'GiveWP';
        }

        // MultiStep Checkout for WooCommerce
        if (
            apbct_is_plugin_active('woo-multistep-checkout/woo-multistep-checkout.php') &&
            Post::get('action') === 'thwmsc_step_validation'
        ) {
            return 'MultiStep Checkout for WooCommerce - step validation';
        }

        // Skip Login Form for Wishlist Member
        if (
            apbct_is_plugin_active('wishlist-member/wpm.php') &&
            Post::get('action') === 'wishlistmember_ajax_login'
        ) {
            return 'Wishlist Member - skip login';
        }

        // Skip some Smart Quiz Builder requests
        if (
            apbct_is_plugin_active('smartquizbuilder/smartquizbuilder.php') &&
            (
                Post::get('action') === 'sqb_lead_save' ||
                Post::get('action') === 'SQBSendNotificationAjax'
            )
        ) {
            return 'Smart Quiz Builder - skip some requests';
        }

        // Abandoned Cart Recovery for WooCommerce requests
        if (
            apbct_is_plugin_active('woo-abandoned-cart-recovery/woo-abandoned-cart-recovery.php') &&
            Post::hasString('action', 'wacv_') &&
            (
                wp_verify_nonce(Post::get('nonce'), 'wacv_nonce') ||
                wp_verify_nonce(Get::get('nonce'), 'wacv_nonce') ||
                wp_verify_nonce(Post::get('security'), 'wacv_nonce')
            )
        ) {
            return 'Abandoned Cart Recovery for WooCommerce: skipped ' . Post::get('action');
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
            Post::get('action') === 'rm_user_exists'
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
            strpos($current_theme_uri, 'porto') !== false &&
            Post::get('action') === 'porto_account_login_popup_login'
        ) {
            return 'Proto theme login popup form';
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
            (int) Post::get('_um_password_reset') === 1 ) {
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
            preg_match('%Automattic\/VaultPress\/\d.\d$%', Server::get('HTTP_USER_AGENT'))
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
            (int) Post::get('wsffid') > 0 )
        ) {
            return 'WSForms skip';
        }
        // Checkout For WC - service requests skip
        if (
            apbct_is_plugin_active('checkout-for-woocommerce/checkout-for-woocommerce.php') &&
            ( ( apbct_is_in_uri('wc-ajax=update_checkout') && wp_verify_nonce(Post::get('security'), 'update-order-review') ) ||
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
            apbct_is_in_uri('wp-json/cleantalk-antispam/v1/check_email_before_post')
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
            wp_verify_nonce(Post::get('ihc_user_add_edit_nonce'), 'ihc_user_add_edit_nonce')
        ) {
            return 'Indeed Ultimate Membership Pro - have the direct integration';
        }

        // Plugin Name: OptimizeCheckouts - skip fields checks
        if (
            apbct_is_plugin_active('op-cart/op-checkouts.php') &&
            ( apbct_is_in_uri('wp-json/opc/v1/cart/recalculate') ||
            apbct_is_in_uri('wp-json/opc/v1/cart/update-payment-method') )
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
            strpos(Post::get('action'), 'wpdCheckNotificationType') !== false ) {
            return 'no_ajax_wpdCheckNotificationType';
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

    // Event Manager - there is the direct integration
    if (
        apbct_is_plugin_active('events-manager/events-manager.php') &&
        Post::get('action') === 'booking_add' &&
        wp_verify_nonce(Post::get('_wpnonce'), 'booking_add')
    ) {
        return 'Event Manager skip';
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
    if (defined('APBCT_SET_AJAX_ROUTE_TYPE')
        && in_array(APBCT_SET_AJAX_ROUTE_TYPE, array('rest','admin_ajax'))
    ) {
        return APBCT_SET_AJAX_ROUTE_TYPE;
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
        $res = json_decode(Helper::httpRequest(get_option('home'), $rc_params), true);
        if ( is_array($res) && isset($res['success']) ) {
            return 'rest';
        }
    } else {
        $res_rest = Helper::httpRequestGetResponseCode(esc_url(apbct_get_rest_url()));
        $res_body = Helper::httpRequestGetContent(esc_url(apbct_get_rest_url()));

        if ( $res_rest == 200 && Helper::isJson($res_body) ) {
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
    return strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false;
}

function apbct__check_admin_ajax_request($query_arg = 'security')
{
    check_ajax_referer('ct_secret_nonce', $query_arg);

    if ( ! current_user_can('manage_options') ) {
        wp_die('-1', 403);
    }
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
