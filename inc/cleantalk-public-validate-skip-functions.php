<?php

use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\ApbctWP\Variables\Post;

// Prevent direct call
if ( ! defined('ABSPATH') ) {
    die('Not allowed!');
}

/**
 * Compare the path exactly. If the configured URL contains query arguments
 * (for example, with plain permalinks), require those arguments separately.
 *
 * @param string $url
 * @return bool
 */
function apbct_matches_request_url($url)
{
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if ( ! is_string($uri) || strpos($uri, '/') !== 0 || strpos($uri, '//') === 0 ) {
        return false;
    }

    $expected = wp_parse_url($url);
    $path = wp_parse_url($uri, PHP_URL_PATH);
    if ( ! is_string($path) || ! is_array($expected) || strpos($path, '//') !== false || strpos($path, '\\') !== false ) {
        return false;
    }

    $expected_path = isset($expected['path']) ? $expected['path'] : '/';
    if ( untrailingslashit($path) !== untrailingslashit($expected_path) ) {
        return false;
    }

    if ( ! empty($expected['query']) ) {
        parse_str($expected['query'], $query);
        foreach ( $query as $field => $value ) {
            if ( ! isset($_GET[$field]) || wp_unslash($_GET[$field]) !== $value ) {
                return false;
            }
        }
    }

    return true;
}

/** @return bool */
function apbct_is_cf7_feedback_request()
{
    global $apbct;

    $form_id = Post::getString('_wpcf7');

    return did_action('init') && $apbct->settings['forms__contact_forms_test'] == 1 && $form_id !== '' && ctype_digit($form_id) && (int) $form_id > 0
        && apbct_is_plugin_active('contact-form-7/wp-contact-form-7.php')
        && apbct_matches_request_url(rest_url('contact-form-7/v1/contact-forms/' . (int) $form_id . '/feedback'));
}

/**
 * Match the nonce read by the WooCommerce form handler.
 *
 * @param string $field
 * @param string $action
 * @param string $fallback
 * @return bool
 */
function apbct_verify_woocommerce_nonce($field, $action, $fallback = '_wpnonce')
{
    $nonce = isset($_REQUEST[$field]) ? $_REQUEST[$field] : ($_REQUEST[$fallback] ?? '');

    return is_string($nonce) && $nonce !== '' && wp_verify_nonce(wp_unslash($nonce), $action) !== false;
}

/**
 * Shared WooCommerce exceptions used by both generic form checks.
 *
 * @return bool
 */
function apbct_is_woocommerce_service_request()
{
    if ( ! apbct_is_plugin_active('woocommerce/woocommerce.php') ) {
        return false;
    }

    $action = Get::getString('wc-ajax');
    if ( $action === '' ) {
        return (isset($_POST['woocommerce_checkout_place_order']) || isset($_POST['woocommerce_checkout_update_totals']))
            && function_exists('wc_get_checkout_url') && wc_get_page_id('checkout') > 0
            && apbct_matches_request_url(wc_get_checkout_url())
            && apbct_verify_woocommerce_nonce('woocommerce-process-checkout-nonce', 'woocommerce-process_checkout');
    }

    $hook = 'wc_ajax_' . $action;
    if ( ! doing_action($hook) && (
        is_admin() || ! class_exists('WC_AJAX', false) || ! has_action($hook)
        || has_action('template_redirect', array('WC_AJAX', 'do_wc_ajax')) === false
        || ! apbct_matches_request_url(WC_AJAX::get_endpoint($action))
    ) ) {
        return false;
    }

    if ( $action === 'checkout' ) {
        return apbct_verify_woocommerce_nonce('woocommerce-process-checkout-nonce', 'woocommerce-process_checkout');
    }

    $nonces = array(
        'apply_coupon' => 'apply-coupon',
        'remove_coupon' => 'remove-coupon',
        'update_shipping_method' => 'update-shipping-method',
        'update_order_review' => 'update-order-review',
    );
    if ( isset($nonces[$action]) ) {
        return apbct_verify_woocommerce_nonce('security', $nonces[$action], 'security');
    }

    // These WooCommerce services do not expect a form nonce.
    if ( in_array($action, array(
        'add_to_cart', 'remove_from_cart', 'get_refreshed_fragments', 'get_cart_totals',
        'get_variation', 'get_customer_location', 'wc_ppec_start_checkout',
        'sa_wc_buy_now_get_ajax_buy_now_button',
    ), true) ) {
        return true;
    }

    if ( ! apbct_is_plugin_active('woocommerce-gateway-stripe/woocommerce-gateway-stripe.php') ) {
        return false;
    }

    switch ( $action ) {
        case 'wc_stripe_normalize_address':
            return apbct_verify_woocommerce_nonce('security', 'wc-stripe-express-checkout-normalize-address', 'security');
        case 'wc_stripe_create_setup_intent':
            $nonce = Post::getString('nonce');
            return apbct_is_user_logged_in() && $nonce !== '' && wp_verify_nonce($nonce, 'wc_stripe_create_si') !== false;
        case 'wc_stripe_init_setup_intent':
            return apbct_verify_woocommerce_nonce('_ajax_nonce', 'wc_stripe_create_setup_intent_nonce');
    }

    return false;
}

/**
 * @param string $endpoint Empty for the My account page.
 * @param string $field
 * @param string $action
 * @return bool
 */
function apbct_is_woocommerce_account_request($endpoint, $field, $action)
{
    if ( ! apbct_is_plugin_active('woocommerce/woocommerce.php') || ! function_exists('wc_get_account_endpoint_url')
        || wc_get_page_id('myaccount') <= 0 ) {
        return false;
    }

    $url = $endpoint === '' ? wc_get_page_permalink('myaccount') : wc_get_account_endpoint_url($endpoint);

    return apbct_matches_request_url($url) && apbct_verify_woocommerce_nonce($field, $action);
}

/** @return bool */
function apbct_is_invoice4u_callback()
{
    if ( ! did_action('init') ) {
        return false;
    }

    return apbct_matches_request_url(rest_url('invoice4u/v1/callback'))
        || (apbct_is_plugin_active('woocommerce/woocommerce.php') && function_exists('WC')
            && has_action('woocommerce_api_wc_invoice4u')
            && apbct_matches_request_url(WC()->api_request_url('WC_Invoice4U')));
}

/**
 * Function for skip in ct_contact_form_validate_postdata()
 * @return bool
 */
function skip_for_ct_contact_form_validate_postdata()
{
    global $apbct, $pagenow;

    if ( @sizeof($_POST) === 0 ||
         (isset($_POST['signup_username'], $_POST['signup_email'], $_POST['signup_password'])) ||
         (isset($pagenow) && $pagenow === 'wp-login.php') || // WordPress log in form
         (isset($pagenow, $_GET['action']) && $pagenow === 'wp-login.php' && $_GET['action'] === 'lostpassword') ||
         apbct_is_woocommerce_service_request() ||
         apbct_is_cf7_feedback_request() ||
         (defined('WP_ADMIN') && WP_ADMIN) ||
         apbct_matches_request_url(site_url('wp-comments-post.php')) ||
         apbct_matches_request_url(home_url('/login/')) ||
         isset($_POST['ct_checkjs_register_form']) ||
         (isset($_POST['signup_username'], $_POST['signup_password_confirm']) && isset($_POST['signup_submit'])) ||
         $apbct->settings['forms__general_contact_forms_test'] == 0 ||
         (isset($_POST['log'], $_POST['pwd'], $_POST['wp-submit'])) ||
         (apbct_is_plugin_active('woocommerce/woocommerce.php') && apbct_matches_request_url(home_url('/wc-api/'))) ||
         (isset($_POST['wc_reset_password']) && apbct_is_woocommerce_account_request('lost-password', 'woocommerce-lost-password-nonce', 'lost_password')) ||
         apbct_is_woocommerce_add_payment_method_form() || // WooCommerce Add payment method, checked as an order
         (isset($_POST['login'], $_POST['password']) && apbct_is_woocommerce_account_request('', 'woocommerce-login-nonce', 'woocommerce-login')) ||
         (isset($_POST['provider'], $_POST['authcode']) && $_POST['provider'] === 'Two_Factor_Totp') || //TwoFactor authorization
         apbct_is_in_uri('/wpstatistics/v1/hit') || //WPStatistics
         (isset($_POST['ihcaction']) && $_POST['ihcaction'] === 'login') || //Skip login form
         (isset($_POST['action']) && $_POST['action'] === 'infinite_scroll') || //Scroll
         (isset($_POST['lrm_action']) && $_POST['lrm_action'] === 'login') || //Skip login form
         (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) ||
         apbct_is_in_uri('connector=bridge&task=put_sql') ||
         Server::inUri('cleantalk-antispam/v1/alt_sessions') || // Skip test for alt sessions
         (apbct_is_in_uri('bvMethod=') && apbct_is_in_uri('bvVersion=') && isset($_POST['apipage']) && $_POST['apipage'] === 'blogvault') ||
         (isset($_POST['wpstg-username'], $_POST['wpstg-pass'], $_POST['wpstg-submit']) && $_POST['wpstg-submit'] === 'Log In') //Accept Stripe Payments
    ) {
        return true;
    }

    return false;
}

/**
 * Real WooCommerce "Add payment method" submit.
 *
 * The dedicated check sends the account email as an order. Skipping it here keeps the
 * general checker from marking the request done. A single POST field is not enough:
 * the request must hit this endpoint and carry the form nonce.
 *
 * @return bool
 */
function apbct_is_woocommerce_add_payment_method_form()
{
    return isset($_POST['woocommerce_add_payment_method'], $_POST['payment_method'])
        && apbct_is_woocommerce_account_request('add-payment-method', 'woocommerce-add-payment-method-nonce', 'woocommerce-add-payment-method');
}

/**
 * Function for skip in ct_contact_form_validate(). Returns false if no exclusions found, or the key of the exclusion.
 * @return false|string
 */
function skip_for_ct_contact_form_validate()
{
    global $apbct, $pagenow, $ct_checkjs_frm;

    if ( empty($_POST) ) {
        return 1;
    }

    $exclusions = array(
        '2' => (isset($_POST['signup_username'], $_POST['signup_email'], $_POST['signup_password'])),
        '3' => (
            isset($pagenow) && $pagenow === 'wp-login.php'
            && Post::getString('wp-submit') !== 'Register'
        ),
        // WordPress log in form
        '4' =>
            (isset($pagenow, $_GET['action']) && $pagenow === 'wp-login.php' && $_GET['action'] === 'lostpassword'),
        '7' =>
            (defined('WP_ADMIN') && WP_ADMIN
             && ! (
                 apbct_matches_request_url(admin_url('admin-post.php'))
                 && Post::getString('action') === 'wms_contact'
             )
             && (empty($_POST['your-phone'])
                 && empty($_POST['your-email'])
                 && empty($_POST['your-message']))),
        //Bitrix24 Contact
        '8' => apbct_matches_request_url(site_url('wp-login.php')),
        '9' => apbct_matches_request_url(site_url('wp-comments-post.php')),
        '11' => apbct_matches_request_url(home_url('/reset-password/')),
        // Ticket #13668. Password reset.
        // task 9405 - prevent Forminator forms direct attacks
        '13' => apbct_matches_request_url(home_url('/login/')) && Post::get('action') !== 'forminator_submit_form_custom-forms',
        '14' => (apbct_is_in_uri('/my-account/edit-account/') && (int)$apbct->settings['data__protect_logged_in'] === 1),
        // WooCommerce edit account page
        '15' => (apbct_is_in_uri('/my-account/edit-address/') && (int)$apbct->settings['data__protect_logged_in'] === 1),
        // WooCommerce edit account page
        '16' => (isset($_POST['action']) && $_POST['action'] === 'save_account_details' && (int)$apbct->settings['data__protect_logged_in'] === 1),
        // WooCommerce edit account action
        '17' => apbct_matches_request_url(home_url('/peepsoajax/profilefieldsajax.validate_register')),
        '19' => isset($_POST['ct_checkjs_register_form']),
        '20' =>
            (isset($_POST['signup_username'], $_POST['signup_password_confirm'], $_POST['signup_submit'])),
        '21' =>
            ($apbct->settings['forms__general_contact_forms_test'] == 0 && $apbct->settings['forms__check_external'] == 0 && $apbct->settings['forms__check_internal'] == 0),
        '25' => apbct_is_plugin_active('woocommerce/woocommerce.php') && apbct_matches_request_url(home_url('/wc-api/')),
        '26' => (isset($_POST['log'], $_POST['pwd'], $_POST['wp-submit'])),
        '27' => (isset($_POST[$ct_checkjs_frm]) && $apbct->settings['forms__contact_forms_test'] == 1),
        // Formidable forms
        '28' => (isset($_POST['comment_post_ID']) && ! isset($_POST['comment-submit'])),
        // The comment form && ! DW Question & Answer
        '30' => (isset($_POST['log'], $_POST['pwd'])),
        //WooCommerce Sensei login form fix
        '31' => isset($_POST['wc_reset_password'])
            && apbct_is_woocommerce_account_request('lost-password', 'woocommerce-lost-password-nonce', 'lost_password'),
        // WooCommerce recovery password form
        '32' => isset($_POST['login'], $_POST['password'])
            && apbct_is_woocommerce_account_request('', 'woocommerce-login-nonce', 'woocommerce-login'),
        // WooCommerce login form
        '33' => (isset($_POST['wc-api']) && strtolower($_POST['wc-api']) === 'wc_gateway_systempay'),
        // Woo Systempay payment plugin
        '34' => apbct_is_in_uri('wc-api=WC_Gateway_Realex_Redirect'),
        // Woo Realex payment Gateway plugin
        '35' => apbct_is_in_uri('wc-api=WC_Gateway_Tpay_Basic'),
        // Tpay payment Gateway plugin
        '36' => apbct_is_cf7_feedback_request(),
        '37' => (isset($_POST['hash'], $_POST['device_unique_id'], $_POST['device_name'])),
        //Mobile Assistant Connector fix
        '40' => (isset($_POST['ccf_form']) && intval($_POST['ccf_form']) == 1),
        '41' => (isset($_POST['contact_tags']) && strpos($_POST['contact_tags'], 'MBR:') !== false),
        '42' => (apbct_is_in_uri('bizuno.php') && ! empty($_POST['bizPass'])),
        '44' => isset($_POST['slm_action'], $_POST['license_key'], $_POST['secret_key'], $_POST['registered_domain']),
        // ticket_id=9122
        '46' => (isset($_POST['action']) && $_POST['action'] === 'grunion-contact-form'),
        // JetPack
        '47' => (isset($_POST['action']) && $_POST['action'] === 'bbp-update-user'),
        //BBP update user info page
        '49' => (isset($_GET['mbr'], $_GET['amp;appname'], $_GET['amp;master'])),
        //  ticket_id=10773
        '50' =>
            (isset($_POST['call_function']) && $_POST['call_function'] === 'push_notification_settings'),
        // Skip mobile requests (push settings)
        '51' => apbct_matches_request_url(home_url('/membership-login/')),
        // Skip login form
        '53' => (Server::get('HTTP_USER_AGENT') === 'MailChimp'
                 && apbct_is_in_uri('mc4wp-sync-api/webhook-listener')),
        // Mailchimp webhook skip
        '54' => apbct_matches_request_url(home_url('/researcher-log-in/')),
        // Skip login form
        '55' => apbct_matches_request_url(home_url('/admin_aspcms/_system/AspCms_SiteSetting.asp?action=saves')),
        // Skip admin save callback
        '57' => (isset($_POST['btn_insert_post_type_hotel']) && $_POST['btn_insert_post_type_hotel'] === 'SUBMIT HOTEL'),
        // Skip adding hotel
        '58' => (isset($_POST['action']) && $_POST['action'] === 'updraft_savesettings'),
        // Updraft save settings
        '60' => (isset($_POST['wpum_form']) && $_POST['wpum_form'] === 'login'),
        //WPUM login skip
        '61' => (isset($_POST['password']) && ! apbct_custom_forms_trappings()),
        // Exception for login form. From Analysis uid=406596
        '62' => (isset($_POST['action']) && $_POST['action'] === 'wilcity_reset_password'),
        // Exception for reset password form. From Analysis uid=430898
        '63' => (isset($_POST['action']) && $_POST['action'] === 'wilcity_login'),
        // Exception for login form. From Analysis uid=430898
        '64' => apbct_matches_request_url(plugins_url('tin-canny-learndash-reporting/src/h5p-xapi/process-xapi-statement.php?v=asd')),
        //Skip Tin Canny plugin
        '65' => (isset($_POST['na'], $_POST['ts'], $_POST['nhr']) && ! apbct_is_in_uri('?na=s')),
        // The Newsletter Plugin double requests fix. Ticket #14772
        '66' => (isset($_POST['spl_action']) && $_POST['spl_action'] === 'register'),
        //Skip interal action with empty params
        '67' => (isset($_POST['action'])
                 && $_POST['action'] === 'bwfan_insert_abandoned_cart'
                 && apbct_is_in_uri('my-account/edit-address')),
        //Skip edit account
        '68' => apbct_matches_request_url(home_url('/login-1/')),
        //Skip login form
        '69' => apbct_matches_request_url(home_url('/recuperacao-de-senha-2/')),
        //Skip form reset password
        '70' => (apbct_is_in_uri('membermouse/api/request.php')
                 && isset($_POST['membership_level_id'], $_POST['apikey'], $_POST['apisecret'])),
        // Membermouse API
        '71' => (isset($_POST['AppKey'], $_POST['cbAP']) && $_POST['cbAP'] === 'Caspio'),
        // Caspio exclusion (ticket #16444)
        '73' => (isset($_POST['somfrp_action'], $_POST['submitted']) && $_POST['somfrp_action'] === 'somfrp_lost_pass'),
        // Frontend Reset Password exclusion
        '74' => (isset($_POST['action']) && $_POST['action'] === 'dokan_save_account_details'),
        '75' => Post::get('action') === 'frm_get_lookup_text_value',
        // Exception for Formidable multilevel form
        '76' => (isset($_POST['ihcaction']) && $_POST['ihcaction'] === 'reset_pass'),
        //Reset pass exclusion
        '77' => (isset($_POST['action'], $_POST['register_unspecified_nonce_field'])
                 && $_POST['action'] === 'register'),
        // Profile Builder have a direct integration
        '78' => (isset($_POST['_wpmem_register_nonce'])
                 && wp_verify_nonce($_POST['_wpmem_register_nonce'], 'wpmem_longform_nonce')),
        // WP Members have a direct integration
        // BuddyPress resolves the current member route.
        '79' => isset($_POST['submit']) && function_exists('bp_is_user') && bp_is_user()
            && (bp_is_current_component('settings') || bp_is_current_component('xprofile')),
        '85' => (isset($_POST['action']) && $_POST['action'] === 'bwfan_insert_abandoned_cart'),
        // Autonami Marketing Automations - WC Plugin - integration
        '86' => (isset($_POST['action']) && $_POST['action'] === 'check_email_exists'),
        // Handling an unknown action check_email_exists
        '87' => Server::inUri('cleantalk-antispam/v1/alt_sessions'),
        '88' => apbct_is_invoice4u_callback(),
        // has direct integration lib/Cleantalk/Antispam/Integrations/MemberPress.php
        '89' => apbct_is_plugin_active('memberpress/memberpress.php') && Post::get('mepr_process_signup_form'),
        '91' => apbct_is_plugin_active('envira-gallery/envira-gallery.php') && Server::inUri('envira-background/v1/resize-image'),
        // woocommerce cfw_validate_email_domain skip
        '92' => apbct_is_plugin_active('checkout-for-woocommerce/checkout-for-woocommerce.php') && Server::inUri('wc-ajax=cfw_validate_email_domain'),
        // FunnelKit Automations
        '93' => (apbct_is_in_uri('/autonami/v1/events') && apbct_is_plugin_active('wp-marketing-automations/wp-marketing-automations.php')),
        '94' => (apbct_is_in_uri('/woofunnels/v1/worker') && apbct_is_plugin_active('wp-marketing-automations/wp-marketing-automations.php')),
        // Mailchimp for woocommerce
        '95' => (apbct_is_in_uri('/mailchimp-for-woocommerce/v1/member-sync')
            && apbct_is_plugin_active('mailchimp-for-woocommerce/mailchimp-woocommerce.php')),
        '96' => (
            apbct_is_plugin_active('woo-stripe-payment/stripe-payments.php') &&
            strpos(Get::get('wc-ajax'), 'wc_stripe') !== false &&
            strpos(Get::get('path'), 'wc-stripe/v1/checkout/payment') !== false &&
            Post::get('stripe_cc_token_key')
        ),
        // OptimizePress
        '97' => (apbct_is_plugin_active('op-dashboard/op-dashboard.php') && apbct_is_in_uri('/trackFacebookCAPIEvents')),
        // WS Form has a direct integration
        '98' => (apbct_is_plugin_active('ws-form/ws-form.php') && apbct_is_in_uri('/ws-form/v1/submit')),
        '99' => (apbct_is_plugin_active('give/give.php') && Get::equal('givewp-route', 'validate')),
        // BuddyBoss REST API has a direct integration
        '101' => (
            apbct_is_plugin_active('buddyboss-platform/buddyboss-platform.php') &&
            apbct_is_in_uri('/wp-json/buddyboss/v1/signup')
        ),
        // aMember Pro signup — has a direct integration AmemberRegister
        '102' => (
            apbct_is_plugin_active('amember4/amember4.php') &&
            (
                isset($_POST['_save_']) ||
                (isset($_POST['pass']) && isset($_POST['_pass']))
            )
        ),
        // WooCommerce Add payment method. Direct check uses the account email and type "order".
        '103' => apbct_is_woocommerce_add_payment_method_form(),
    );

    foreach ( $exclusions as $exclusion_key => $state ) {
        if ( $state === true ) {
            return $exclusion_key;
        }
    }

    return false;
}
