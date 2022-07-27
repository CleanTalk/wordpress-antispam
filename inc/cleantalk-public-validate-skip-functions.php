<?php

use Cleantalk\Variables\Server;
use Cleantalk\Variables\Post;

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
         apbct_is_in_uri('/checkout/') ||
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
         apbct_is_in_uri('/wp-admin/') ||
         apbct_is_in_uri('wp-login.php') ||
         apbct_is_in_uri('wp-comments-post.php') ||
         apbct_is_in_referer('/wp-admin/') ||
         apbct_is_in_uri('/login/') ||
         apbct_is_in_uri('?provider=facebook&') ||
         (isset($_GET['ptype']) && $_GET['ptype'] === 'login') ||
         isset($_POST['ct_checkjs_register_form']) ||
         (isset($_POST['signup_username'], $_POST['signup_password_confirm']) && isset($_POST['signup_submit'])) ||
         $apbct->settings['forms__general_contact_forms_test'] == 0 ||
         isset($_POST['bbp_topic_content']) ||
         isset($_POST['bbp_reply_content']) ||
         isset($_POST['fscf_submitted']) ||
         (isset($_POST['log'], $_POST['pwd'], $_POST['wp-submit'])) ||
         apbct_is_in_uri('/wc-api') ||
         apbct_is_in_uri('wc-api=WC_Gateway_Tpay_Basic') || // Tpay payment Gateway plugin
         (isset($_POST['wc_reset_password'], $_POST['_wpnonce'], $_POST['_wp_http_referer'])) || //WooCommerce recovery password form
         (isset($_POST['woocommerce-login-nonce'], $_POST['login'], $_POST['password'], $_POST['_wp_http_referer'])) || //WooCommerce login form
         (isset($_POST['provider'], $_POST['authcode']) && $_POST['provider'] === 'Two_Factor_Totp') || //TwoFactor authorization
         (isset($_GET['wc-ajax']) && $_GET['wc-ajax'] === 'sa_wc_buy_now_get_ajax_buy_now_button') || //BuyNow add to cart
         apbct_is_in_uri('/wp-json/wpstatistics/v1/hit') || //WPStatistics
         (isset($_POST['ihcaction']) && $_POST['ihcaction'] === 'login') || //Skip login form
         (isset($_POST['action']) && $_POST['action'] === 'infinite_scroll') || //Scroll
         isset($_POST['gform_submit']) || //Skip gravity checking because of direct integration
         (isset($_POST['lrm_action']) && $_POST['lrm_action'] === 'login') || //Skip login form
         apbct_is_in_uri('xmlrpc.php?for=jetpack') ||
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
 * Function for skip in ct_contact_form_validate()
 * @return bool
 */
function skip_for_ct_contact_form_validate()
{
    global $apbct, $pagenow, $ct_checkjs_frm;

    if ( @sizeof($_POST) === 0 ||
         (isset($_POST['signup_username'], $_POST['signup_email'], $_POST['signup_password'])) ||
         (isset($pagenow) && $pagenow === 'wp-login.php') || // WordPress log in form
         (isset($pagenow, $_GET['action']) && $pagenow === 'wp-login.php' && $_GET['action'] === 'lostpassword') ||
         apbct_is_in_referer('lostpassword') ||
         apbct_is_in_referer('lost-password') || //Skip lost-password form check
         (apbct_is_in_uri('/wp-admin/') && (empty($_POST['your-phone']) && empty($_POST['your-email']) && empty($_POST['your-message']))) || //Bitrix24 Contact
         apbct_is_in_uri('wp-login.php') ||
         apbct_is_in_uri('wp-comments-post.php') ||
         apbct_is_in_uri('?provider=facebook&') ||
         apbct_is_in_uri('reset-password/') || // Ticket #13668. Password reset.
         apbct_is_in_referer('/wp-admin/') ||
         apbct_is_in_uri('/login/') ||
         apbct_is_in_uri('/my-account/edit-account/') ||   // WooCommerce edit account page
         apbct_is_in_uri('/my-account/edit-address/') ||   // WooCommerce edit account page
         (isset($_POST['action']) && $_POST['action'] === 'save_account_details') ||       // WooCommerce edit account action
         apbct_is_in_uri('/peepsoajax/profilefieldsajax.validate_register') ||
         (isset($_GET['ptype']) && $_GET['ptype'] === 'login') ||
         isset($_POST['ct_checkjs_register_form']) ||
         (isset($_POST['signup_username'], $_POST['signup_password_confirm'], $_POST['signup_submit'])) ||
         ( $apbct->settings['forms__general_contact_forms_test'] == 0 && $apbct->settings['forms__check_external'] == 0 && $apbct->settings['forms__check_internal'] == 0 ) ||
         isset($_POST['bbp_topic_content']) ||
         isset($_POST['bbp_reply_content']) ||
         isset($_POST['fscf_submitted']) ||
         apbct_is_in_uri('/wc-api') ||
         (isset($_POST['log'], $_POST['pwd'], $_POST['wp-submit'])) ||
         (isset($_POST[$ct_checkjs_frm]) && $apbct->settings['forms__contact_forms_test'] == 1) || // Formidable forms
         (isset($_POST['comment_post_ID']) && ! isset($_POST['comment-submit'])) || // The comment form && ! DW Question & Answer
         isset($_GET['for']) ||
         (isset($_POST['log'], $_POST['pwd'])) || //WooCommerce Sensei login form fix
         (isset($_POST['wc_reset_password'], $_POST['_wpnonce'], $_POST['_wp_http_referer'])) || // WooCommerce recovery password form
         ((isset($_POST['woocommerce-login-nonce']) || isset($_POST['_wpnonce'])) && isset($_POST['login'], $_POST['password'], $_POST['_wp_http_referer'])) || // WooCommerce login form
         (isset($_POST['wc-api']) && strtolower($_POST['wc-api']) === 'wc_gateway_systempay') || // Woo Systempay payment plugin
         apbct_is_in_uri('wc-api=WC_Gateway_Realex_Redirect') || // Woo Realex payment Gateway plugin
         apbct_is_in_uri('wc-api=WC_Gateway_Tpay_Basic') || // Tpay payment Gateway plugin
         (isset($_POST['_wpcf7'], $_POST['_wpcf7_version'], $_POST['_wpcf7_locale'])) || //CF7 fix)
         (isset($_POST['hash'], $_POST['device_unique_id'], $_POST['device_name'])) || //Mobile Assistant Connector fix
         isset($_POST['gform_submit']) || //Gravity form
         apbct_is_in_uri('wc-ajax=get_refreshed_fragments') ||
         (isset($_POST['ccf_form']) && intval($_POST['ccf_form']) == 1) ||
         (isset($_POST['contact_tags']) && strpos($_POST['contact_tags'], 'MBR:') !== false) ||
         (apbct_is_in_uri('bizuno.php') && ! empty($_POST['bizPass'])) ||
         apbct_is_in_referer('my-dashboard/') || // ticket_id=7885
         isset($_POST['slm_action'], $_POST['license_key'], $_POST['secret_key'], $_POST['registered_domain']) || // ticket_id=9122
         (isset($_POST['wpforms']['submit']) && $_POST['wpforms']['submit'] === 'wpforms-submit') || // WPForms
         (isset($_POST['action']) && $_POST['action'] === 'grunion-contact-form') || // JetPack
         (isset($_POST['action']) && $_POST['action'] === 'bbp-update-user') || //BBP update user info page
         apbct_is_in_referer('?wc-api=WC_Gateway_Transferuj') || //WC Gateway
         (isset($_GET['mbr'], $_GET['amp;appname'], $_GET['amp;master'])) || //  ticket_id=10773
         (isset($_POST['call_function']) && $_POST['call_function'] === 'push_notification_settings') || // Skip mobile requests (push settings)
         apbct_is_in_uri('membership-login') || // Skip login form
         (isset($_GET['cookie-state-change'])) || //skip GDPR plugin
         (apbct_get_server_variable('HTTP_USER_AGENT') === 'MailChimp' && apbct_is_in_uri('mc4wp-sync-api/webhook-listener')) || // Mailchimp webhook skip
         apbct_is_in_uri('researcher-log-in') || // Skip login form
         apbct_is_in_uri('admin_aspcms/_system/AspCms_SiteSetting.asp?action=saves') || // Skip admin save callback
         apbct_is_in_uri('?profile_tab=postjobs') || // Skip post vacancies
         (isset($_POST['btn_insert_post_type_hotel']) && $_POST['btn_insert_post_type_hotel'] === 'SUBMIT HOTEL') || // Skip adding hotel
         (isset($_POST['action']) && $_POST['action'] === 'updraft_savesettings') || // Updraft save settings
         isset($_POST['quform_submit']) || //QForms multi-paged form skip
         (isset($_POST['wpum_form']) && $_POST['wpum_form'] === 'login') || //WPUM login skip
         (isset($_POST['password']) && ! apbct_custom_forms_trappings()) || // Exception for login form. From Analysis uid=406596
         (isset($_POST['action']) && $_POST['action'] === 'wilcity_reset_password') || // Exception for reset password form. From Analysis uid=430898
         (isset($_POST['action']) && $_POST['action'] === 'wilcity_login') || // Exception for login form. From Analysis uid=430898
         apbct_is_in_uri('tin-canny-learndash-reporting/src/h5p-xapi/process-xapi-statement.php?v=asd') || //Skip Tin Canny plugin
         (isset($_POST['na'], $_POST['ts'], $_POST['nhr']) && ! apbct_is_in_uri('?na=s')) ||  // The Newsletter Plugin double requests fix. Ticket #14772
         (isset($_POST['spl_action']) && $_POST['spl_action'] === 'register') || //Skip interal action with empty params
         (isset($_POST['action']) && $_POST['action'] === 'bwfan_insert_abandoned_cart' && apbct_is_in_uri('my-account/edit-address')) || //Skip edit account
         apbct_is_in_uri('login-1') || //Skip login form
         apbct_is_in_uri('recuperacao-de-senha-2') || //Skip form reset password
         (apbct_is_in_uri('membermouse/api/request.php') && isset($_POST['membership_level_id'], $_POST['apikey'], $_POST['apisecret'])) || // Membermouse API
         (isset($_POST['AppKey'], $_POST['cbAP']) && $_POST['cbAP'] === 'Caspio') ||  // Caspio exclusion (ticket #16444)
         isset($_POST['wpforms_id'], $_POST['wpforms_author']) || //Skip wpforms
         (isset($_POST['somfrp_action'], $_POST['submitted']) && $_POST['somfrp_action'] === 'somfrp_lost_pass') || // Frontend Reset Password exclusion
         (isset($_POST['action']) && $_POST['action'] === 'dokan_save_account_details') ||
         Post::get('action') === 'frm_get_lookup_text_value' || // Exception for Formidable multilevel form
         (isset($_POST['ihcaction']) && $_POST['ihcaction'] === 'reset_pass') || //Reset pass exclusion
         (isset($_POST['action'], $_POST['register_unspecified_nonce_field']) && $_POST['action'] === 'register') || // Profile Builder have a direct integration
         (isset($_POST['_wpmem_register_nonce']) && wp_verify_nonce($_POST['_wpmem_register_nonce'], 'wpmem_longform_nonce')) || // WP Members have a direct integration
         (apbct_is_in_uri('/settings/') && isset($_POST['submit'])) || // Buddypress integration
         (apbct_is_in_uri('/settings/notifications/') && isset($_POST['submit'])) || // Buddypress integration
         (apbct_is_in_uri('/settings/profile/') && isset($_POST['submit'])) || // Buddypress integration
         (apbct_is_in_uri('/settings/data/') && isset($_POST['submit'])) || // Buddypress integration
         (apbct_is_in_uri('/settings/delete-account/') && isset($_POST['submit'])) || // Buddypress integration
         (apbct_is_in_uri('/profile/') && isset($_POST['submit'])) || // Buddypress integration
         (isset($_POST['action']) && $_POST['action'] === 'bwfan_insert_abandoned_cart') || // Autonami Marketing Automations - WC Plugin - integration
         (isset($_POST['action']) && $_POST['action'] === 'check_email_exists') ||             // Handling an unknown action check_email_exists
         Server::inUri('cleantalk-antispam/v1/alt_sessions') // Skip test for alt sessions
        /* !! Do not add actions here. Use apbct_is_skip_request() function below !! */
    ) {
        return true;
    }

    return false;
}
