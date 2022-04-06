<?php

use Cleantalk\Variables\Server;

/**
 * General test for any contact form
 */
function ct_contact_form_validate()
{
    global $pagenow, $apbct, $ct_checkjs_frm;

    // Exclude the XML-RPC requests
    if ( defined('XMLRPC_REQUEST') ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);
        return null;
    }

    // Exclusios common function
    if ( apbct_exclusions_check(__FUNCTION__) ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);
        return null;
    }

    if ( @sizeof($_POST) == 0 ||
         (isset($_POST['signup_username']) && isset($_POST['signup_email']) && isset($_POST['signup_password'])) ||
         (isset($pagenow) && $pagenow == 'wp-login.php') || // WordPress log in form
         (isset($pagenow) && $pagenow == 'wp-login.php' && isset($_GET['action']) && $_GET['action'] == 'lostpassword') ||
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
         (isset($_POST['action']) && $_POST['action'] == 'save_account_details') ||       // WooCommerce edit account action
         apbct_is_in_uri('/peepsoajax/profilefieldsajax.validate_register') ||
         (isset($_GET['ptype']) && $_GET['ptype'] == 'login') ||
         isset($_POST['ct_checkjs_register_form']) ||
         (isset($_POST['signup_username']) && isset($_POST['signup_password_confirm']) && isset($_POST['signup_submit'])) ||
         $apbct->settings['forms__general_contact_forms_test'] == 0 ||
         isset($_POST['bbp_topic_content']) ||
         isset($_POST['bbp_reply_content']) ||
         isset($_POST['fscf_submitted']) ||
         apbct_is_in_uri('/wc-api') ||
         (isset($_POST['log']) && isset($_POST['pwd']) && isset($_POST['wp-submit'])) ||
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
         (isset($_POST['wpforms']['submit']) && $_POST['wpforms']['submit'] == 'wpforms-submit') || // WPForms
         (isset($_POST['action']) && $_POST['action'] == 'grunion-contact-form') || // JetPack
         (isset($_POST['action']) && $_POST['action'] == 'bbp-update-user') || //BBP update user info page
         apbct_is_in_referer('?wc-api=WC_Gateway_Transferuj') || //WC Gateway
         (isset($_GET['mbr'], $_GET['amp;appname'], $_GET['amp;master'])) || //  ticket_id=10773
         (isset($_POST['call_function']) && $_POST['call_function'] == 'push_notification_settings') || // Skip mobile requests (push settings)
         apbct_is_in_uri('membership-login') || // Skip login form
         (isset($_GET['cookie-state-change'])) || //skip GDPR plugin
         (apbct_get_server_variable('HTTP_USER_AGENT') == 'MailChimp' && apbct_is_in_uri('mc4wp-sync-api/webhook-listener')) || // Mailchimp webhook skip
         apbct_is_in_uri('researcher-log-in') || // Skip login form
         apbct_is_in_uri('admin_aspcms/_system/AspCms_SiteSetting.asp?action=saves') || // Skip admin save callback
         apbct_is_in_uri('?profile_tab=postjobs') || // Skip post vacancies
         (isset($_POST['btn_insert_post_type_hotel']) && $_POST['btn_insert_post_type_hotel'] == 'SUBMIT HOTEL') || // Skip adding hotel
         (isset($_POST['action']) && $_POST['action'] == 'updraft_savesettings') || // Updraft save settings
         isset($_POST['quform_submit']) || //QForms multi-paged form skip
         (isset($_POST['wpum_form']) && $_POST['wpum_form'] == 'login') || //WPUM login skip
         (isset($_POST['password']) && ! apbct_custom_forms_trappings()) || // Exception for login form. From Analysis uid=406596
         (isset($_POST['action']) && $_POST['action'] == 'wilcity_reset_password') || // Exception for reset password form. From Analysis uid=430898
         (isset($_POST['action']) && $_POST['action'] == 'wilcity_login') || // Exception for login form. From Analysis uid=430898
         apbct_is_in_uri('tin-canny-learndash-reporting/src/h5p-xapi/process-xapi-statement.php?v=asd') || //Skip Tin Canny plugin
         (isset($_POST['na'], $_POST['ts'], $_POST['nhr']) && ! apbct_is_in_uri('?na=s')) ||  // The Newsletter Plugin double requests fix. Ticket #14772
         (isset($_POST['spl_action']) && $_POST['spl_action'] == 'register') || //Skip interal action with empty params
         (isset($_POST['action']) && $_POST['action'] == 'bwfan_insert_abandoned_cart' && apbct_is_in_uri('my-account/edit-address')) || //Skip edit account
         apbct_is_in_uri('login-1') || //Skip login form
         apbct_is_in_uri('recuperacao-de-senha-2') || //Skip form reset password
         (apbct_is_in_uri('membermouse/api/request.php') && isset($_POST['membership_level_id'], $_POST['apikey'], $_POST['apisecret'])) || // Membermouse API
         (isset($_POST['AppKey']) && (isset($_POST['cbAP']) && $_POST['cbAP'] == 'Caspio')) ||  // Caspio exclusion (ticket #16444)
         isset($_POST['wpforms_id'], $_POST['wpforms_author']) || //Skip wpforms
         (isset($_POST['somfrp_action'], $_POST['submitted']) && $_POST['somfrp_action'] == 'somfrp_lost_pass') || // Frontend Reset Password exclusion
         (isset($_POST['action']) && $_POST['action'] == 'dokan_save_account_details') ||
         \Cleantalk\Variables\Post::get('action') === 'frm_get_lookup_text_value' || // Exception for Formidable multilevel form
         (isset($_POST['ihcaction']) && $_POST['ihcaction'] == 'reset_pass') || //Reset pass exclusion
         (isset($_POST['action'], $_POST['register_unspecified_nonce_field']) && $_POST['action'] == 'register') || // Profile Builder have a direct integration
         (isset($_POST['_wpmem_register_nonce']) && wp_verify_nonce($_POST['_wpmem_register_nonce'], 'wpmem_longform_nonce')) || // WP Members have a direct integration
         (apbct_is_in_uri('/settings/') && isset($_POST['submit'])) || // Buddypress integration
         (apbct_is_in_uri('/settings/notifications/') && isset($_POST['submit'])) || // Buddypress integration
         (apbct_is_in_uri('/settings/profile/') && isset($_POST['submit'])) || // Buddypress integration
         (apbct_is_in_uri('/settings/data/') && isset($_POST['submit'])) || // Buddypress integration
         (apbct_is_in_uri('/settings/delete-account/') && isset($_POST['submit'])) || // Buddypress integration
         (apbct_is_in_uri('/profile/') && isset($_POST['submit'])) || // Buddypress integration
         (isset($_POST['action']) && $_POST['action'] == 'bwfan_insert_abandoned_cart') || // Autonami Marketing Automations - WC Plugin - integration
         (isset($_POST['action']) && $_POST['action'] == 'check_email_exists') ||             // Handling an unknown action check_email_exists
         Server::inUri('cleantalk-antispam/v1/alt_sessions') // Skip test for alt sessions
        /* !! Do not add actions here. Use apbct_is_skip_request() function below !! */
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return null;
    }

    // Skip REST API requests
    if ( Server::isPost() && Server::inUri('rest_route') ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return null;
    }

    //Skip woocommerce checkout
    if ( apbct_is_in_uri('wc-ajax=update_order_review') ||
         apbct_is_in_uri('wc-ajax=checkout') ||
         ! empty($_POST['woocommerce_checkout_place_order']) ||
         apbct_is_in_uri('wc-ajax=wc_ppec_start_checkout') ||
         apbct_is_in_referer('wc-ajax=update_order_review')
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return null;
    }

    //Skip woocommerce add_to_cart
    if ( ! empty($_POST['add-to-cart']) ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return null;
    }

    // Do not execute anti-spam test for logged in users.
    if ( isset($_COOKIE[LOGGED_IN_COOKIE]) && $apbct->settings['data__protect_logged_in'] != 1 ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return null;
    }
    //Skip WP Fusion web hooks
    if ( apbct_is_in_uri('wpf_action') && apbct_is_in_uri('access_key') && isset($_GET['access_key']) ) {
        if ( function_exists('wp_fusion') ) {
            $key = wp_fusion()->settings->get('access_key');
            if ( $key == $_GET['access_key'] ) {
                do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

                return null;
            }
        }
    }
    //Skip system fields for divi
    if ( strpos(\Cleantalk\Variables\Post::get('action'), 'et_pb_contactform_submit') === 0 ) {
        foreach ( $_POST as $key => $value ) {
            if ( strpos($key, 'et_pb_contact_email_fields') === 0 ) {
                unset($_POST[$key]);
            }
        }
    }

    if ( apbct_is_skip_request(false) ) {
        do_action(
            'apbct_skipped_request',
            __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__ . '(' . apbct_is_skip_request() . ')',
            $_POST
        );

        return false;
    }

    // Skip CalculatedFieldsForm
    if (
        apbct_is_plugin_active('calculated-fields-form/cp_calculatedfieldsf.php') ||
        apbct_is_plugin_active('calculated-fields-form/cp_calculatedfieldsf_free.php')
    ) {
        foreach ( $_POST as $key => $value ) {
            if ( strpos($key, 'calculatedfields') !== false ) {
                do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

                return null;
            }
        }
    }

    $post_info['comment_type'] = 'feedback_general_contact_form';

    /**
     * Filter for POST
     */
    $input_array = apply_filters('apbct__filter_post', $_POST);

    $ct_temp_msg_data = ct_get_fields_any($input_array);

    $sender_email    = ($ct_temp_msg_data['email'] ? $ct_temp_msg_data['email'] : '');
    $sender_nickname = ($ct_temp_msg_data['nickname'] ? $ct_temp_msg_data['nickname'] : '');
    $subject         = ($ct_temp_msg_data['subject'] ? $ct_temp_msg_data['subject'] : '');
    $contact_form    = ($ct_temp_msg_data['contact'] ? $ct_temp_msg_data['contact'] : true);
    $message         = ($ct_temp_msg_data['message'] ? $ct_temp_msg_data['message'] : array());
    if ( $subject != '' ) {
        $message = array_merge(array('subject' => $subject), $message);
    }

    // Skip submission if no data found
    if ( ! $contact_form ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return false;
    }

    if ( isset($_POST['TellAFriend_Link']) ) {
        $tmp = $_POST['TellAFriend_Link'];
        unset($_POST['TellAFriend_Link']);
    }

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => $post_info,
            'sender_info'     => array('sender_email' => urlencode($sender_email)),
        )
    );

    if ( isset($_POST['TellAFriend_Link']) ) {
        $_POST['TellAFriend_Link'] = $tmp;
    }

    $ct_result = $base_call_result['ct_result'];
    if ( $ct_result->allow == 0 ) {
        // Recognize contact form an set it's name to $contact_form to use later
        $contact_form = null;
        foreach ( $_POST as $param => $value ) {
            if ( strpos($param, 'et_pb_contactform_submit') === 0 ) {
                $contact_form            = 'contact_form_divi_theme';
                $contact_form_additional = str_replace('et_pb_contactform_submit', '', $param);
            }
            if ( strpos($param, 'avia_generated_form') === 0 ) {
                $contact_form            = 'contact_form_enfold_theme';
                $contact_form_additional = str_replace('avia_generated_form', '', $param);
            }
            if ( ! empty($contact_form) ) {
                break;
            }
        }

        $ajax_call = false;
        if ( (defined('DOING_AJAX') && DOING_AJAX)
        ) {
            $ajax_call = true;
        }
        if ( $ajax_call ) {
            echo $ct_result->comment;
        } else {
            global $ct_comment;
            $ct_comment = $ct_result->comment;
            if ( isset($_POST['cma-action']) && $_POST['cma-action'] == 'add' ) {
                $result = array('success' => 0, 'thread_id' => null, 'messages' => array($ct_result->comment));
                header("Content-Type: application/json");
                print json_encode($result);
                die();
            } elseif ( isset($_POST['TellAFriend_email']) ) {
                echo $ct_result->comment;
                die();
            } elseif ( isset($_POST['gform_submit']) ) { // Gravity forms submission
                $response = sprintf(
                    "<!DOCTYPE html><html><head><meta charset='UTF-8' /></head><body class='GF_AJAX_POSTBACK'><div id='gform_confirmation_wrapper_1' class='gform_confirmation_wrapper '><div id='gform_confirmation_message_1' class='gform_confirmation_message_1
 gform_confirmation_message'>%s</div></div></body></html>",
                    $ct_result->comment
                );
                echo $response;
                die();
            } elseif ( isset($_POST['action']) && $_POST['action'] == 'ct_check_internal' ) {
                return $ct_result->comment;
            } elseif ( isset($_POST['vfb-submit']) && defined('VFB_VERSION') ) {
                wp_die(
                    "<h1>" . __(
                        'Spam protection by CleanTalk',
                        'cleantalk-spam-protect'
                    ) . "</h1><h2>" . $ct_result->comment . "</h2>",
                    '',
                    array('response' => 403, "back_link" => true, "text_direction" => 'ltr')
                );
                // Caldera Contact Forms
            } elseif ( isset($_POST['action']) && $_POST['action'] == 'cf_process_ajax_submit' ) {
                print "<h3 style='color: red;'><red>" . $ct_result->comment . "</red></h3>";
                die();
                // Mailster
            } elseif ( isset($_POST['_referer'], $_POST['formid'], $_POST['email']) ) {
                $return = array(
                    'success' => false,
                    'html'    => '<p>' . $ct_result->comment . '</p>',
                );
                print json_encode($return);
                die();
                // Divi Theme Contact Form. Using $contact_form
            } elseif ( ! empty($contact_form) && $contact_form == 'contact_form_divi_theme' ) {
                echo "<div id='et_pb_contact_form{$contact_form_additional}'><h1>Your request looks like spam.</h1><div><p>{$ct_result->comment}</p></div></div>";
                die();
                // Enfold Theme Contact Form. Using $contact_form
            } elseif ( ! empty($contact_form) && $contact_form == 'contact_form_enfold_theme' ) {
                echo "<div id='ajaxresponse_1' class='ajaxresponse ajaxresponse_1' style='display: block;'><div id='ajaxresponse_1' class='ajaxresponse ajaxresponse_1'><h3 class='avia-form-success'>Anti-Spam by CleanTalk: " . $ct_result->comment . "</h3><a href='.'><-Back</a></div></div>";
                die();
            } else {
                ct_die(null, null);
            }
        }
        exit;
    }

    return null;
}

/**
 * General test for any post data
 */
function ct_contact_form_validate_postdata()
{
    global $apbct, $pagenow, $cleantalk_executed;

    // Exclusios common function
    if ( apbct_exclusions_check(__FUNCTION__) ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return null;
    }

    if ( @sizeof($_POST) == 0 ||
         (isset($_POST['signup_username']) && isset($_POST['signup_email']) && isset($_POST['signup_password'])) ||
         (isset($pagenow) && $pagenow == 'wp-login.php') || // WordPress log in form
         (isset($pagenow) && $pagenow == 'wp-login.php' && isset($_GET['action']) && $_GET['action'] == 'lostpassword') ||
         apbct_is_in_uri('/checkout/') ||
         /* WooCommerce Service Requests - skip them */
         isset($_GET['wc-ajax']) && (
             $_GET['wc-ajax'] == 'checkout' ||
             $_GET['wc-ajax'] == 'get_refreshed_fragments' ||
             $_GET['wc-ajax'] == 'apply_coupon' ||
             $_GET['wc-ajax'] == 'remove_coupon' ||
             $_GET['wc-ajax'] == 'update_shipping_method' ||
             $_GET['wc-ajax'] == 'get_cart_totals' ||
             $_GET['wc-ajax'] == 'update_order_review' ||
             $_GET['wc-ajax'] == 'add_to_cart' ||
             $_GET['wc-ajax'] == 'remove_from_cart' ||
             $_GET['wc-ajax'] == 'get_variation' ||
             $_GET['wc-ajax'] == 'get_customer_location'
         ) ||
         /* END: WooCommerce Service Requests  */
         apbct_is_in_uri('/wp-admin/') ||
         apbct_is_in_uri('wp-login.php') ||
         apbct_is_in_uri('wp-comments-post.php') ||
         apbct_is_in_referer('/wp-admin/') ||
         apbct_is_in_uri('/login/') ||
         apbct_is_in_uri('?provider=facebook&') ||
         isset($_GET['ptype']) && $_GET['ptype'] == 'login' ||
         isset($_POST['ct_checkjs_register_form']) ||
         (isset($_POST['signup_username']) && isset($_POST['signup_password_confirm']) && isset($_POST['signup_submit'])) ||
         $apbct->settings['forms__general_contact_forms_test'] == 0 ||
         isset($_POST['bbp_topic_content']) ||
         isset($_POST['bbp_reply_content']) ||
         isset($_POST['fscf_submitted']) ||
         isset($_POST['log']) && isset($_POST['pwd']) && isset($_POST['wp-submit']) ||
         apbct_is_in_uri('/wc-api') ||
         apbct_is_in_uri('wc-api=WC_Gateway_Tpay_Basic') || // Tpay payment Gateway plugin
         (isset($_POST['wc_reset_password'], $_POST['_wpnonce'], $_POST['_wp_http_referer'])) || //WooCommerce recovery password form
         (isset($_POST['woocommerce-login-nonce'], $_POST['login'], $_POST['password'], $_POST['_wp_http_referer'])) || //WooCommerce login form
         (isset($_POST['provider'], $_POST['authcode']) && $_POST['provider'] == 'Two_Factor_Totp') || //TwoFactor authorization
         (isset($_GET['wc-ajax']) && $_GET['wc-ajax'] == 'sa_wc_buy_now_get_ajax_buy_now_button') || //BuyNow add to cart
         apbct_is_in_uri('/wp-json/wpstatistics/v1/hit') || //WPStatistics
         (isset($_POST['ihcaction']) && $_POST['ihcaction'] == 'login') || //Skip login form
         (isset($_POST['action']) && $_POST['action'] == 'infinite_scroll') || //Scroll
         isset($_POST['gform_submit']) || //Skip gravity checking because of direct integration
         (isset($_POST['lrm_action']) && $_POST['lrm_action'] == 'login') || //Skip login form
         apbct_is_in_uri('xmlrpc.php?for=jetpack') ||
         apbct_is_in_uri('connector=bridge&task=put_sql') ||
         Server::inUri('cleantalk-antispam/v1/alt_sessions') || // Skip test for alt sessions
         (apbct_is_in_uri('bvMethod=') && apbct_is_in_uri('bvVersion=') && isset($_POST['apipage']) && $_POST['apipage'] === 'blogvault') ||
         (isset($_POST['wpstg-username'], $_POST['wpstg-pass'], $_POST['wpstg-submit']) && $_POST['wpstg-submit'] == 'Log In') //Accept Stripe Payments
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return null;
    }

    /**
     * Filter for POST
     */
    $input_array = apply_filters('apbct__filter_post', $_POST);

    $message = ct_get_fields_any_postdata($input_array);

    // ???
    if ( strlen(json_encode($message)) < 10 ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return null;
    }


    // Skip if request contains params
    $skip_params = array(
        'ipn_track_id',   // PayPal IPN #
        'txn_type',       // PayPal transaction type
        'payment_status', // PayPal payment status
    );
    foreach ( $skip_params as $key => $value ) {
        if ( @array_key_exists($value, $_GET) || @array_key_exists($value, $_POST) ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

            return null;
        }
    }

    $base_call_result = apbct_base_call(
        array(
            'message'   => $message,
            'post_info' => array('comment_type' => 'feedback_general_postdata'),
        )
    );

    $cleantalk_executed = true;

    $ct_result = $base_call_result['ct_result'];

    if ( $ct_result->allow == 0 ) {
        if ( ! (defined('DOING_AJAX') && DOING_AJAX) ) {
            global $ct_comment;
            $ct_comment = $ct_result->comment;
            if ( isset($_POST['cma-action']) && $_POST['cma-action'] == 'add' ) {
                $result = array('success' => 0, 'thread_id' => null, 'messages' => array($ct_result->comment));
                header("Content-Type: application/json");
                print json_encode($result);
                die();
            } else {
                ct_die(null, null);
            }
        } else {
            echo $ct_result->comment;
        }
        exit;
    }

    return null;
}

add_filter('apbct__filter_post', 'apbct__filter_form_data', 10);
function apbct__filter_form_data($form_data)
{
    global $apbct;

    // It is a service field. Need to be deleted before the processing.
    if ( isset($form_data['apbct_visible_fields']) ) {
        unset($form_data['apbct_visible_fields']);
    }

    if ($apbct->settings['exclusions__fields']) {
        // regular expression exception
        if ($apbct->settings['exclusions__fields__use_regexp']) {
            $exclusion_regexp = $apbct->settings['exclusions__fields'];

            foreach (array_keys($form_data) as $key) {
                if (preg_match('/' . $exclusion_regexp . '/', $key) === 1) {
                    unset($form_data[$key]);
                }
            }

            return $form_data;
        }

        $excluded_fields = explode(',', $apbct->settings['exclusions__fields']);

        foreach ($excluded_fields as $excluded_field) {
            preg_match_all('/\[(\S*?)\]/', $excluded_field, $matches);

            if (!empty($matches[1])) {
                $excluded_matches = $matches[1];
                $first_el = strstr($excluded_field, '[', true);
                array_unshift($excluded_matches, $first_el);
                foreach ($excluded_matches as $k => $v) {
                    if ($v === '') {
                        unset($excluded_matches[$k]);
                    }
                }

                $form_data = apbct__filter_array_recursive($form_data, $excluded_matches);
            } else {
                $form_data = apbct__filter_array_recursive($form_data, array($excluded_field));
            }
        }
    }

    return $form_data;
}

/**
 * Filtering array to exclude another array
 * Example: delete fields from $_POST
 *
 * @param $array
 * @param array $excluded_matches
 * @param int $level
 *
 * @return array|mixed
 */
function apbct__filter_array_recursive(&$array, $excluded_matches, $level = 0)
{
    if (! is_array($array) || empty($array)) {
        return $array;
    }

    foreach ($array as $key => $value) {
        if ((string) $key !== (string) $excluded_matches[$level]) {
            continue;
        }

        if (is_array($value)) {
            $level++;

            if ($level === count($excluded_matches)) {
                unset($array[$key]);
                return $array;
            }

            $array[$key] = apbct__filter_array_recursive($value, $excluded_matches, $level);
        } else {
            unset($array[$key]);
            return $array;
        }
    }

    return $array;
}
