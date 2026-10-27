<?php

use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\ApbctWP\Variables\Post;

// Prevent direct call
if ( ! defined('ABSPATH') ) {
    die('Not allowed!');
}

/**
 * Function for skip in ct_contact_form_validate_postdata()
 * @return bool
 */
function skip_for_ct_contact_form_validate_postdata()
{
    global $apbct;

    if ( @sizeof($_POST) === 0 ||
         (
             isset($_POST['signup_username'], $_POST['signup_email'], $_POST['signup_password']) &&
             apbct_is_plugin_active('buddypress/bp-loader.php') &&
             ! apbct_is_email_in_post(array(), array('signup_'))
         ) ||
         apbct_is_script_request('wp-login.php') || // WordPress log in form
         (apbct_is_script_request('wp-login.php') && Get::equal('action', 'lostpassword')) ||
         (
             apbct_is_in_path('/checkout/') &&
             apbct_is_plugin_active('woocommerce/woocommerce.php') &&
             ! apbct_is_email_in_post(array(), array('billing_', 'shipping_', 'account_'))
         ) ||
         /* WooCommerce Service Requests - skip them */
         (isset($_GET['wc-ajax']) && (
                 $_GET['wc-ajax'] === 'checkout' ||
                 $_GET['wc-ajax'] === 'get_refreshed_fragments' ||
                 $_GET['wc-ajax'] === 'apply_coupon' ||
                 $_GET['wc-ajax'] === 'remove_coupon' ||
                 $_GET['wc-ajax'] === 'update_shipping_method' ||
                 $_GET['wc-ajax'] === 'get_cart_totals' ||
                 $_GET['wc-ajax'] === 'update_order_review' ||
                 $_GET['wc-ajax'] === 'add_to_cart' ||
                 $_GET['wc-ajax'] === 'remove_from_cart' ||
                 $_GET['wc-ajax'] === 'get_variation' ||
                 $_GET['wc-ajax'] === 'get_customer_location'
             )) ||
         /* END: WooCommerce Service Requests  */
         is_admin() ||
         apbct_is_script_request('wp-comments-post.php') ||
         (apbct_is_in_referer('/wp-admin/') && current_user_can('edit_posts')) ||
         (apbct_is_in_path('/login/') && apbct_is_login_form()) ||
         (Get::equal('provider', 'facebook') && apbct_is_login_form()) ||
         (Get::equal('ptype', 'login') && isset($_POST['log'], $_POST['pwd']) && apbct_is_login_form()) ||
         (isset($_POST['ct_checkjs_register_form']) && ! apbct_is_email_in_post(array('user_email'), array('user_email-'))) ||
         (
             isset($_POST['signup_username'], $_POST['signup_password_confirm'], $_POST['signup_submit']) &&
             apbct_is_plugin_active('buddypress/bp-loader.php') &&
             ! apbct_is_email_in_post(array(), array('signup_'))
         ) ||
         $apbct->settings['forms__general_contact_forms_test'] == 0 ||
         (
             (isset($_POST['bbp_topic_content']) || isset($_POST['bbp_reply_content'])) &&
             class_exists('bbPress') &&
             ! apbct_is_email_in_post(array(), array('bbp_'))
         ) ||
         (
             isset($_POST['fscf_submitted']) &&
             $apbct->settings['forms__contact_forms_test'] == 1 &&
             apbct_is_fscf_form_processed()
         ) ||
         (isset($_POST['log'], $_POST['pwd'], $_POST['wp-submit']) && apbct_is_login_form()) ||
         (apbct_is_plugin_active('woocommerce/woocommerce.php') && strpos(apbct_get_request_path() . '/', '/wc-api/') !== false) ||
         Get::equal('wc-api', 'WC_Gateway_Tpay_Basic') || // Tpay payment Gateway plugin
         (
             isset($_POST['wc_reset_password'], $_POST['_wpnonce'], $_POST['_wp_http_referer']) &&
             ! apbct_is_email_in_post(array('user_login', 'reset_login'))
         ) || //WooCommerce recovery password form
         apbct_is_woocommerce_add_payment_method_form() || // WooCommerce Add payment method, checked as an order
         (
             isset($_POST['woocommerce-login-nonce'], $_POST['login'], $_POST['password'], $_POST['_wp_http_referer']) &&
             apbct_is_login_form()
         ) || //WooCommerce login form
         (isset($_POST['provider'], $_POST['authcode']) && $_POST['provider'] === 'Two_Factor_Totp' && apbct_is_login_form()) || //TwoFactor authorization
         (isset($_GET['wc-ajax']) && $_GET['wc-ajax'] === 'sa_wc_buy_now_get_ajax_buy_now_button') || //BuyNow add to cart
         apbct_is_rest_route('wpstatistics/v1/hit') || //WPStatistics
         (isset($_POST['ihcaction']) && $_POST['ihcaction'] === 'login' && apbct_is_login_form()) || //Skip login form
         (isset($_POST['action']) && $_POST['action'] === 'infinite_scroll' && ! apbct_is_email_in_post()) || //Scroll
         (
             isset($_POST['gform_submit']) &&
             defined('GF_MIN_WP_VERSION') &&
             ! apbct_is_email_in_post(array(), array('input_'))
         ) || //Skip gravity checking because of direct integration
         (isset($_POST['lrm_action']) && $_POST['lrm_action'] === 'login' && apbct_is_login_form()) || //Skip login form
         (defined('XMLRPC_REQUEST') && Get::equal('for', 'jetpack')) ||
         (Get::equal('connector', 'bridge') && Get::equal('task', 'put_sql') && ! apbct_is_email_in_post()) ||
         apbct_is_rest_route('cleantalk-antispam/v1/alt_sessions') || // Skip test for alt sessions
         (
             isset($_POST['_wpcf7'], $_POST['_wpcf7_version'], $_POST['_wpcf7_locale']) &&
             defined('WPCF7_VERSION') &&
             apbct_is_rest_route('contact-form-7/v1/contact-forms/')
         ) || // Contact Form 7 has a direct integration
         (
             apbct_is_in_uri('bvMethod=') &&
             apbct_is_in_uri('bvVersion=') &&
             isset($_POST['apipage']) &&
             $_POST['apipage'] === 'blogvault' &&
             ! apbct_is_email_in_post()
         ) ||
         (
             isset($_POST['wpstg-username'], $_POST['wpstg-pass'], $_POST['wpstg-submit']) &&
             $_POST['wpstg-submit'] === 'Log In' &&
             ! apbct_is_email_in_post(array('wpstg-username'))
         ) //Accept Stripe Payments
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
    if (
        ! apbct_is_plugin_active('woocommerce/woocommerce.php') ||
        ! apbct_is_in_uri('add-payment-method') ||
        ! isset($_POST['woocommerce_add_payment_method'], $_POST['payment_method'])
    ) {
        return false;
    }

    $nonce = '';
    if (
        isset($_REQUEST['woocommerce-add-payment-method-nonce']) &&
        is_string($_REQUEST['woocommerce-add-payment-method-nonce'])
    ) {
        $nonce = $_REQUEST['woocommerce-add-payment-method-nonce'];
    } elseif ( isset($_REQUEST['_wpnonce']) && is_string($_REQUEST['_wpnonce']) ) {
        $nonce = $_REQUEST['_wpnonce'];
    }

    return wp_verify_nonce($nonce, 'woocommerce-add-payment-method') !== false;
}

/**
 * Checking if the form looks like a login form: it has no emails besides the login field.
 *
 * @return bool
 */
function apbct_is_login_form()
{
    return ! apbct_is_email_in_post(array('log', 'login', 'username', 'user_login'));
}

/**
 * Checking if Fast Secure Contact Form has processed the form without errors.
 * The form is processed and checked on init, before the general check.
 *
 * @return bool
 * @psalm-suppress UndefinedClass
 */
function apbct_is_fscf_form_processed()
{
    return class_exists('FSCF_Process') &&
        FSCF_Process::$form_processed &&
        empty(FSCF_Process::$form_errors);
}

/**
 * Checking if Contact Form by BestWebSoft has processed the form without errors.
 * The form is processed and checked on init, before the general check.
 *
 * @return bool
 */
function apbct_is_bws_contact_form_processed()
{
    global $cntctfrm_result;

    return $cntctfrm_result === true;
}

/**
 * Checking if the request is the Tutor LMS student registration, it is checked by the registration_errors hook.
 *
 * @return bool
 */
function apbct_is_tutor_registration()
{
    global $apbct;

    return Post::getString('tutor_action') === 'tutor_register_student' &&
        $apbct->settings['forms__registrations_test'] == 1 &&
        get_option('users_can_register') &&
        apbct_is_plugin_active('tutor/tutor.php') &&
        wp_verify_nonce(Post::getString('_tutor_nonce'), 'tutor_nonce_action') &&
        apbct_is_only_fields_in_post(
            array(
                'first_name', 'last_name', 'user_login', 'email', 'password', 'password_confirmation', 'terms_conditions',
                '_tutor_nonce', '_wp_http_referer'
            ),
            array('tutor_')
        ) &&
        ! apbct_is_email_in_post(array('email'));
}

/**
 * Checking if the request is The Newsletter Plugin subscription, it is checked by the direct integration.
 *
 * @return bool
 */
function apbct_is_newsletter_subscription()
{
    global $apbct;

    if ( ! $apbct->settings['forms__contact_forms_test'] || ! apbct_is_plugin_active('newsletter/plugin.php') ) {
        return false;
    }

    $action = isset($_POST['na']) ? $_POST['na'] : (isset($_GET['na']) ? $_GET['na'] : '');

    return is_string($action) && in_array(sanitize_key($action), array('s', 'subscribe', 'sa', 'ajaxsub'), true);
}

/**
 * Function for skip in ct_contact_form_validate(). Returns false if no exclusions found, or the key of the exclusion.
 * @return false|string
 */
function skip_for_ct_contact_form_validate()
{
    global $apbct, $ct_checkjs_frm;

    if ( empty($_POST) ) {
        return 1;
    }

    $exclusions = array(
        '2' => (
            isset($_POST['signup_username'], $_POST['signup_email'], $_POST['signup_password']) &&
            apbct_is_plugin_active('buddypress/bp-loader.php') &&
            ! apbct_is_email_in_post(array(), array('signup_'))
        ),
        '3' => (
            apbct_is_script_request('wp-login.php')
            && Post::getString('wp-submit') !== 'Register'
        ),
        // WordPress log in form
        '4' =>
            (isset($_GET['action']) && apbct_is_script_request('wp-login.php') && $_GET['action'] === 'lostpassword'),
        '5' => apbct_is_in_path('lostpassword') && apbct_is_login_form(),
        '6' => apbct_is_in_path('lost-password') && apbct_is_login_form(),
        //Skip lost-password form check
        '7' =>
            (is_admin()
             && ! (
                 apbct_is_script_request('admin-post.php')
                 && Post::getString('action') === 'wms_contact'
             )
             && (empty($_POST['your-phone'])
                 && empty($_POST['your-email'])
                 && empty($_POST['your-message']))),
        //Bitrix24 Contact
        '8' => apbct_is_script_request('wp-login.php'),
        '9' => apbct_is_script_request('wp-comments-post.php'),
        '10' => Get::equal('provider', 'facebook') && apbct_is_login_form(),
        '11' => apbct_is_in_path('reset-password/') && apbct_is_login_form(),
        // Ticket #13668. Password reset.
        '12' => apbct_is_in_referer('/wp-admin/') && current_user_can('edit_posts'),
        // task 9405 - prevent Forminator forms direct attacks
        '13' => apbct_is_in_path('/login/') && Post::get('action') !== 'forminator_submit_form_custom-forms' && apbct_is_login_form(),
        '14' => (
            apbct_is_in_path('/my-account/edit-account/') && (int)$apbct->settings['data__protect_logged_in'] === 1 &&
            apbct_is_user_logged_in() &&
            ! apbct_is_email_in_post(array('account_email'))
        ),
        // WooCommerce edit account page
        '15' => (
            apbct_is_in_path('/my-account/edit-address/') && (int)$apbct->settings['data__protect_logged_in'] === 1 &&
            apbct_is_user_logged_in() &&
            ! apbct_is_email_in_post(array(), array('billing_', 'shipping_'))
        ),
        // WooCommerce edit account page
        '16' => (
            isset($_POST['action']) && $_POST['action'] === 'save_account_details' && (int)$apbct->settings['data__protect_logged_in'] === 1 &&
            apbct_is_user_logged_in() &&
            ! apbct_is_email_in_post(array('account_email'))
        ),
        // WooCommerce edit account action
        '17' => apbct_is_plugin_active('peepso-core/peepso.php') && apbct_is_path_end('/peepsoajax/profilefieldsajax.validate_register'),
        '18' => (Get::equal('ptype', 'login') && isset($_POST['log'], $_POST['pwd']) && apbct_is_login_form()),
        '19' => (
            isset($_POST['ct_checkjs_register_form']) &&
            ! apbct_is_email_in_post(array('user_email'), array('user_email-'))
        ),
        '20' => (
            isset($_POST['signup_username'], $_POST['signup_password_confirm'], $_POST['signup_submit']) &&
            apbct_is_plugin_active('buddypress/bp-loader.php') &&
            ! apbct_is_email_in_post(array(), array('signup_'))
        ),
        '21' =>
            ($apbct->settings['forms__general_contact_forms_test'] == 0 && $apbct->settings['forms__check_external'] == 0 && $apbct->settings['forms__check_internal'] == 0),
        '22' => (
            isset($_POST['bbp_topic_content']) &&
            class_exists('bbPress') &&
            ! apbct_is_email_in_post(array(), array('bbp_'))
        ),
        '23' => (
            isset($_POST['bbp_reply_content']) &&
            class_exists('bbPress') &&
            ! apbct_is_email_in_post(array(), array('bbp_'))
        ),
        '24' => (
            isset($_POST['fscf_submitted']) &&
            $apbct->settings['forms__contact_forms_test'] == 1 &&
            apbct_is_fscf_form_processed()
        ),
        '25' => apbct_is_plugin_active('woocommerce/woocommerce.php') && strpos(apbct_get_request_path() . '/', '/wc-api/') !== false,
        '26' => (isset($_POST['log'], $_POST['pwd'], $_POST['wp-submit']) && apbct_is_login_form()),
        '27' => (
            isset($_POST[$ct_checkjs_frm]) &&
            $apbct->settings['forms__contact_forms_test'] == 1 &&
            apbct_is_plugin_active('formidable/formidable.php') &&
            ! apbct_is_email_in_post(array('item_meta'))
        ),
        // Formidable forms
        '28' => (isset($_POST['comment_post_ID']) && ! isset($_POST['comment-submit']) && ! apbct_is_email_in_post()),
        // The comment form && ! DW Question & Answer
        '29' => (apbct_is_script_request('xmlrpc.php') && Get::equal('for', 'jetpack')),
        '30' => (isset($_POST['log'], $_POST['pwd']) && apbct_is_login_form()),
        //WooCommerce Sensei login form fix
        '31' => (
            isset($_POST['wc_reset_password'], $_POST['_wpnonce'], $_POST['_wp_http_referer']) &&
            ! apbct_is_email_in_post(array('user_login', 'reset_login'))
        ),
        // WooCommerce recovery password form
        '32' => (
            (isset($_POST['woocommerce-login-nonce']) || isset($_POST['_wpnonce'])) &&
            isset($_POST['login'], $_POST['password'], $_POST['_wp_http_referer']) &&
            apbct_is_login_form()
        ),
        // WooCommerce login form
        '33' => (isset($_POST['wc-api']) && strtolower($_POST['wc-api']) === 'wc_gateway_systempay'),
        // Woo Systempay payment plugin
        '34' => Get::equal('wc-api', 'WC_Gateway_Realex_Redirect'),
        // Woo Realex payment Gateway plugin
        '35' => Get::equal('wc-api', 'WC_Gateway_Tpay_Basic'),
        // Tpay payment Gateway plugin
        '36' => (
            isset($_POST['_wpcf7'], $_POST['_wpcf7_version'], $_POST['_wpcf7_locale']) &&
            defined('WPCF7_VERSION') &&
            apbct_is_rest_route('contact-form-7/v1/contact-forms/')
        ),
        //CF7 fix)
        '37' => (isset($_POST['hash'], $_POST['device_unique_id'], $_POST['device_name']) && ! apbct_is_email_in_post()),
        //Mobile Assistant Connector fix
        '38' => (
            isset($_POST['gform_submit']) &&
            defined('GF_MIN_WP_VERSION') &&
            ! apbct_is_email_in_post(array(), array('input_'))
        ),
        //Gravity form
        '39' => Get::equal('wc-ajax', 'get_refreshed_fragments'),
        '40' => (
            isset($_POST['ccf_form']) && intval($_POST['ccf_form']) == 1 &&
            defined('CCF_VERSION') &&
            apbct_is_rest_route('ccf/v1/submit')
        ),
        '41' => (isset($_POST['contact_tags']) && strpos($_POST['contact_tags'], 'MBR:') !== false && ! apbct_is_email_in_post()),
        '42' => (apbct_is_in_path('bizuno.php') && ! empty($_POST['bizPass']) && ! apbct_is_email_in_post(array('bizUser'))),
        '43' => apbct_is_in_path('my-dashboard/') && ! apbct_is_email_in_post(),
        // ticket_id=7885
        '44' => (
            isset($_POST['slm_action'], $_POST['license_key'], $_POST['secret_key'], $_POST['registered_domain']) &&
            ! apbct_is_email_in_post()
        ),
        // ticket_id=9122
        '45' => (
            isset($_POST['wpforms']['submit']) && $_POST['wpforms']['submit'] === 'wpforms-submit' &&
            (apbct_is_plugin_active('wpforms-lite/wpforms.php') || apbct_is_plugin_active('wpforms/wpforms.php')) &&
            ! apbct_is_email_in_post(array('wpforms'))
        ),
        // WPForms
        '46' => (
            isset($_POST['action']) && $_POST['action'] === 'grunion-contact-form' &&
            defined('JETPACK__VERSION') &&
            ! apbct_is_email_in_post(array(), array('g' . Post::getString('contact-form-id') . '-'))
        ),
        // JetPack
        '47' => (
            isset($_POST['action']) && $_POST['action'] === 'bbp-update-user' &&
            class_exists('bbPress') &&
            apbct_is_user_logged_in() &&
            ! apbct_is_email_in_post(array('email'))
        ),
        //BBP update user info page
        '48' => Get::equal('wc-api', 'WC_Gateway_Transferuj'),
        //WC Gateway
        '49' => (isset($_GET['mbr'], $_GET['amp;appname'], $_GET['amp;master'])),
        //  ticket_id=10773
        '50' =>
            (isset($_POST['call_function']) && $_POST['call_function'] === 'push_notification_settings'),
        // Skip mobile requests (push settings)
        '51' => apbct_is_in_path('membership-login') && apbct_is_login_form(),
        // Skip login form
        '52' => (isset($_GET['cookie-state-change']) && ! apbct_is_email_in_post()),
        //skip GDPR plugin
        '53' => (Server::get('HTTP_USER_AGENT') === 'MailChimp'
                 && apbct_is_plugin_active('mailchimp-sync/mailchimp-sync.php')
                 && apbct_is_path_end('/mc4wp-sync-api/webhook-listener')),
        // Mailchimp webhook skip
        '54' => apbct_is_in_path('researcher-log-in') && apbct_is_login_form(),
        // Skip login form
        '55' => apbct_is_in_path('admin_aspcms/_system/AspCms_SiteSetting.asp') && Get::equal('action', 'saves') && ! apbct_is_email_in_post(),
        // Skip admin save callback
        '56' => Get::equal('profile_tab', 'postjobs') && ! apbct_is_email_in_post(),
        // Skip post vacancies
        '57' => (
            isset($_POST['btn_insert_post_type_hotel']) && $_POST['btn_insert_post_type_hotel'] === 'SUBMIT HOTEL' &&
            apbct_is_user_logged_in() &&
            ! apbct_is_email_in_post()
        ),
        // Skip adding hotel
        '58' => (
            isset($_POST['action']) && $_POST['action'] === 'updraft_savesettings' &&
            apbct_is_user_logged_in() &&
            current_user_can('manage_options')
        ),
        // Updraft save settings
        '59' => (
            isset($_POST['quform_submit']) &&
            apbct_is_plugin_active('quform/quform.php') &&
            ! apbct_is_email_in_post(array(), array('quform_'))
        ),
        //QForms multi-paged form skip
        '60' => (isset($_POST['wpum_form']) && $_POST['wpum_form'] === 'login' && apbct_is_login_form()),
        //WPUM login skip
        '61' => (isset($_POST['password']) && ! apbct_custom_forms_trappings() && apbct_is_login_form()),
        // Exception for login form. From Analysis uid=406596
        '62' => (isset($_POST['action']) && $_POST['action'] === 'wilcity_reset_password' && apbct_is_login_form()),
        // Exception for reset password form. From Analysis uid=430898
        '63' => (isset($_POST['action']) && $_POST['action'] === 'wilcity_login' && apbct_is_login_form()),
        // Exception for login form. From Analysis uid=430898
        '64' => (
            apbct_is_script_request('process-xapi-statement.php') &&
            apbct_is_in_path('tin-canny-learndash-reporting/src/h5p-xapi/process-xapi-statement.php') &&
            Get::equal('v', 'asd')
        ),
        //Skip Tin Canny plugin
        '65' => apbct_is_newsletter_subscription(),
        // The Newsletter Plugin double requests fix. Ticket #14772
        '66' => (isset($_POST['spl_action']) && $_POST['spl_action'] === 'register' && ! apbct_is_email_in_post()),
        //Skip interal action with empty params
        '67' => (isset($_POST['action'])
                 && $_POST['action'] === 'bwfan_insert_abandoned_cart'
                 && apbct_is_in_path('my-account/edit-address')
                 && apbct_is_user_logged_in()
                 && ! apbct_is_email_in_post(array(), array('billing_', 'shipping_'))),
        //Skip edit account
        '68' => apbct_is_in_path('login-1') && apbct_is_login_form(),
        //Skip login form
        '69' => apbct_is_in_path('recuperacao-de-senha-2') && apbct_is_login_form(),
        //Skip form reset password
        '70' => (apbct_is_script_request('request.php')
                 && apbct_is_in_path('membermouse/api/request.php')
                 && isset($_POST['membership_level_id'], $_POST['apikey'], $_POST['apisecret'])),
        // Membermouse API
        '71' => (isset($_POST['AppKey'], $_POST['cbAP']) && $_POST['cbAP'] === 'Caspio' && ! apbct_is_email_in_post()),
        // Caspio exclusion (ticket #16444)
        '72' => isset($_POST['wpforms_id'], $_POST['wpforms_author']) && ! apbct_is_email_in_post(),
        //Skip wpforms
        '73' => (
            isset($_POST['somfrp_action'], $_POST['submitted']) && $_POST['somfrp_action'] === 'somfrp_lost_pass' &&
            ! apbct_is_email_in_post(array('somfrp_user_info'))
        ),
        // Frontend Reset Password exclusion
        '74' => (
            isset($_POST['action']) && $_POST['action'] === 'dokan_save_account_details' &&
            apbct_is_plugin_active('dokan-lite/dokan.php') &&
            apbct_is_user_logged_in() &&
            ! apbct_is_email_in_post(array('account_email'))
        ),
        '75' => Post::get('action') === 'frm_get_lookup_text_value' && ! apbct_is_email_in_post(),
        // Exception for Formidable multilevel form
        '76' => (
            isset($_POST['ihcaction']) && $_POST['ihcaction'] === 'reset_pass' &&
            ! apbct_is_email_in_post(array('email_or_userlogin'))
        ),
        //Reset pass exclusion
        '77' => (isset($_POST['action'], $_POST['register_unspecified_nonce_field'])
                 && $_POST['action'] === 'register'
                 && ! apbct_is_email_in_post()),
        // Profile Builder have a direct integration
        '79' => (
            apbct_is_in_path('/settings/') && isset($_POST['submit']) &&
            apbct_is_user_logged_in() && ! apbct_is_email_in_post(array('email'))
        ),
        // Buddypress integration
        '80' => (
            apbct_is_in_path('/settings/notifications/') && isset($_POST['submit']) &&
            apbct_is_user_logged_in() && ! apbct_is_email_in_post(array('email'))
        ),
        // Buddypress integration
        '81' => (
            apbct_is_in_path('/settings/profile/') && isset($_POST['submit']) &&
            apbct_is_user_logged_in() && ! apbct_is_email_in_post(array('email'))
        ),
        // Buddypress integration
        '82' => (
            apbct_is_in_path('/settings/data/') && isset($_POST['submit']) &&
            apbct_is_user_logged_in() && ! apbct_is_email_in_post(array('email'))
        ),
        // Buddypress integration
        '83' => (
            apbct_is_in_path('/settings/delete-account/') && isset($_POST['submit']) &&
            apbct_is_user_logged_in() && ! apbct_is_email_in_post(array('email'))
        ),
        // Buddypress integration
        '84' => (
            apbct_is_in_path('/profile/') && isset($_POST['submit']) &&
            apbct_is_user_logged_in() && ! apbct_is_email_in_post(array('email'))
        ),
        // Buddypress integration
        '85' => (
            isset($_POST['action']) && $_POST['action'] === 'bwfan_insert_abandoned_cart' &&
            apbct_is_plugin_active('wp-marketing-automations/wp-marketing-automations.php') &&
            wp_doing_ajax()
        ),
        // Autonami Marketing Automations - WC Plugin - integration
        '86' => (isset($_POST['action']) && $_POST['action'] === 'check_email_exists' && wp_doing_ajax()),
        // Handling an unknown action check_email_exists
        '87' => apbct_is_rest_route('cleantalk-antispam/v1/alt_sessions'),
        '88' => (
            Get::equal('wc-api', 'WC_Invoice4U') ||
            apbct_is_rest_route('invoice4u/v1/callback')
        ),
        // has direct integration lib/Cleantalk/Antispam/Integrations/MemberPress.php
        '89' => (
            apbct_is_plugin_active('memberpress/memberpress.php') &&
            Post::getString('mepr_process_signup_form') &&
            (int)$apbct->settings['forms__registrations_test'] === 1
        ),
        // WooCommerce recovery password form
        '90' => (isset($_POST['wc_reset_password'], $_POST['woocommerce-lost-password-nonce']) && apbct_is_login_form()),
        '91' => apbct_is_plugin_active('envira-gallery/envira-gallery.php') && apbct_is_rest_route('envira-background/v1/resize-image'),
        // woocommerce cfw_validate_email_domain skip
        '92' => apbct_is_plugin_active('checkout-for-woocommerce/checkout-for-woocommerce.php') && Get::equal('wc-ajax', 'cfw_validate_email_domain'),
        // FunnelKit Automations
        '93' => (apbct_is_rest_route('autonami/v1/events') && apbct_is_plugin_active('wp-marketing-automations/wp-marketing-automations.php')),
        '94' => (apbct_is_rest_route('woofunnels/v1/worker') && apbct_is_plugin_active('wp-marketing-automations/wp-marketing-automations.php')),
        // Mailchimp for woocommerce
        '95' => (apbct_is_rest_route('mailchimp-for-woocommerce/v1/member-sync')
            && apbct_is_plugin_active('mailchimp-for-woocommerce/mailchimp-woocommerce.php')),
        '96' => (
            apbct_is_plugin_active('woo-stripe-payment/stripe-payments.php') &&
            strpos(Get::get('wc-ajax'), 'wc_stripe') !== false &&
            strpos(Get::get('path'), 'wc-stripe/v1/checkout/payment') !== false &&
            Post::get('stripe_cc_token_key')
        ),
        // OptimizePress
        '97' => (
            apbct_is_plugin_active('op-dashboard/op-dashboard.php') &&
            apbct_is_rest_route('') &&
            apbct_is_path_end('/trackFacebookCAPIEvents')
        ),
        // WS Form has a direct integration
        '98' => (apbct_is_plugin_active('ws-form/ws-form.php') && apbct_is_rest_route('ws-form/v1/submit')),
        '99' => (apbct_is_plugin_active('give/give.php') && Get::equal('givewp-route', 'validate')),
        // woocommerce apple pay gateway
        '100' => (
            apbct_is_plugin_active('woocommerce/woocommerce.php') &&
            Get::equal('wc-ajax', 'wc_stripe_frontend_request') &&
            ! empty(Post::getString('stripe_applepay_token_key'))
        ),
        // BuddyBoss REST API has a direct integration
        '101' => (
            apbct_is_plugin_active('buddyboss-platform/buddyboss-platform.php') &&
            apbct_is_rest_route('buddyboss/v1/signup')
        ),
        // aMember Pro signup — has a direct integration AmemberRegister
        '102' => (
            apbct_is_plugin_active('amember4/amember4.php') &&
            (
                isset($_POST['_save_']) ||
                (isset($_POST['pass']) && isset($_POST['_pass']))
            ) &&
            $apbct->settings['forms__registrations_test'] == 1 &&
            ! empty($_POST['email']) && ! empty($_POST['name_f']) && ! empty($_POST['name_l']) &&
            ! apbct_is_email_in_post(array('email'))
        ),
        // Contact Form by BestWebSoft has a direct integration
        '104' => (
            isset($_POST['cntctfrm_contact_action']) &&
            $apbct->settings['forms__contact_forms_test'] == 1 &&
            apbct_is_bws_contact_form_processed() &&
            ! apbct_is_email_in_post(array(), array('cntctfrm_'))
        ),
        // Gwolle Guestbook has a direct integration
        '105' => (
            isset($_POST['gwolle_gb_function']) && $_POST['gwolle_gb_function'] === 'add_entry' &&
            $apbct->settings['forms__contact_forms_test'] == 1 &&
            apbct_is_plugin_active('gwolle-gb/gwolle-gb.php') &&
            ! apbct_is_email_in_post(array(), array('gwolle_gb_'))
        ),
        // Tutor LMS registration
        '106' => apbct_is_tutor_registration(),
    );

    foreach ( $exclusions as $exclusion_key => $state ) {
        if ( $state === true ) {
            return $exclusion_key;
        }
    }

    return false;
}
