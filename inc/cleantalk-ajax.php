<?php

/**
 * AJAX functions
 */

use Cleantalk\Variables\Get;
use Cleantalk\Variables\Post;

$_cleantalk_ajax_actions_to_check[] = 'qcf_validate_form';            //Quick Contact Form
$_cleantalk_ajax_actions_to_check[] = 'amoforms_submit';            //amoForms

$_cleantalk_hooked_actions[] = 'rwp_ajax_action_rating'; //Don't check Reviewer plugin

$_cleantalk_hooked_actions[] = 'ct_feedback_comment';

/* MailChimp Premium*/
add_filter('mc4wp_form_errors', 'ct_mc4wp_ajax_hook');

/*hooks for Usernoise Form*/
add_action('un_feedback_form_body', 'ct_add_hidden_fields', 1);
add_filter('un_validate_feedback', 'ct_ajax_hook', 1, 2);

/*hooks for AJAX Login & Register email validation*/
add_action('wp_ajax_nopriv_validate_email', 'ct_validate_email_ajaxlogin', 1);
add_action('wp_ajax_validate_email', 'ct_validate_email_ajaxlogin', 1);
$_cleantalk_hooked_actions[] = 'validate_email';

/*hooks for user registration*/
add_action('user_register', 'ct_user_register_ajaxlogin', 1);

/*hooks for WPUF pro */
add_action('wp_ajax_nopriv_wpuf_submit_register', 'ct_ajax_hook', 1);
add_action('wp_ajax_wpuf_submit_register', 'ct_ajax_hook', 1);
$_cleantalk_hooked_actions[] = 'submit_register';

/*hooks for MyMail */
add_action('wp_ajax_nopriv_mymail_form_submit', 'ct_ajax_hook', 1);
add_action('wp_ajax_mymail_form_submit', 'ct_ajax_hook', 1);
$_cleantalk_hooked_actions[] = 'form_submit';

/*hooks for MailPoet */
add_action('wp_ajax_nopriv_wysija_ajax', 'ct_ajax_hook', 1);
add_action('wp_ajax_wysija_ajax', 'ct_ajax_hook', 1);
$_cleantalk_hooked_actions[] = 'wysija_ajax';

/*hooks for cs_registration_validation */
add_action('wp_ajax_nopriv_cs_registration_validation', 'ct_ajax_hook', 1);
add_action('wp_ajax_cs_registration_validation', 'ct_ajax_hook', 1);
$_cleantalk_hooked_actions[] = 'cs_registration_validation';

/*hooks for send_message and request_appointment */
add_action('wp_ajax_nopriv_send_message', 'ct_ajax_hook', 1);
add_action('wp_ajax_send_message', 'ct_ajax_hook', 1);
add_action('wp_ajax_nopriv_request_appointment', 'ct_ajax_hook', 1);
add_action('wp_ajax_request_appointment', 'ct_ajax_hook', 1);
$_cleantalk_hooked_actions[] = 'send_message';
$_cleantalk_hooked_actions[] = 'request_appointment';

/*hooks for zn_do_login */
add_action('wp_ajax_nopriv_zn_do_login', 'ct_ajax_hook', 1);
add_action('wp_ajax_zn_do_login', 'ct_ajax_hook', 1);
$_cleantalk_hooked_actions[] = 'zn_do_login';

/*hooks for zn_do_login */
if ( Post::get('action') === 'cscf-submitform' ) {
    add_filter('preprocess_comment', 'ct_ajax_hook', 1);
    $_cleantalk_hooked_actions[] = 'cscf-submitform';
}


/*hooks for visual form builder */
add_action('wp_ajax_nopriv_vfb_submit', 'ct_ajax_hook', 1);
add_action('wp_ajax_vfb_submit', 'ct_ajax_hook', 1);
$_cleantalk_hooked_actions[] = 'vfb_submit';

/*hooks for woocommerce_checkout*/
add_action('wp_ajax_nopriv_woocommerce_checkout', 'ct_ajax_hook', 1);
add_action('wp_ajax_woocommerce_checkout', 'ct_ajax_hook', 1);
$_cleantalk_hooked_actions[] = 'woocommerce_checkout';
$_cleantalk_hooked_actions[] = 'wcfm_ajax_controller';

/*hooks for frm_action*/
add_action('wp_ajax_nopriv_frm_entries_create', 'ct_ajax_hook', 1);
add_action('wp_ajax_frm_entries_create', 'ct_ajax_hook', 1);
$_cleantalk_hooked_actions[] = 'frm_entries_create';

add_action('wp_ajax_nopriv_td_mod_register', 'ct_ajax_hook', 1);
add_action('wp_ajax_td_mod_register', 'ct_ajax_hook', 1);
$_cleantalk_hooked_actions[] = 'td_mod_register';

/*hooks for tevolution theme*/
add_action('wp_ajax_nopriv_tmpl_ajax_check_user_email', 'ct_ajax_hook', 1);
add_action('wp_ajax_tmpl_ajax_check_user_email', 'ct_ajax_hook', 1);
add_action('wp_ajax_nopriv_tevolution_submit_from_preview', 'ct_ajax_hook', 1);
add_action('wp_ajax_tevolution_submit_from_preview', 'ct_ajax_hook', 1);
add_action('wp_ajax_nopriv_submit_form_recaptcha_validation', 'ct_ajax_hook', 1);
add_action('wp_ajax_tmpl_submit_form_recaptcha_validation', 'ct_ajax_hook', 1);
$_cleantalk_hooked_actions[] = 'tmpl_ajax_check_user_email';
$_cleantalk_hooked_actions[] = 'tevolution_submit_from_preview';
$_cleantalk_hooked_actions[] = 'submit_form_recaptcha_validation';

/* hooks for contact forms by web settler ajax*/
add_action('wp_ajax_nopriv_smuzform-storage', 'ct_ajax_hook', 1);
$_cleantalk_hooked_actions[] = 'smuzform_form_submit';

/* hooks for reviewer plugin*/
add_action('wp_ajax_nopriv_rwp_ajax_action_rating', 'ct_ajax_hook', 1);
$_cleantalk_hooked_actions[] = 'rwp-submit-wrap';

$_cleantalk_hooked_actions[] = 'post_update';

/* Ninja Forms hoocked actions */
$_cleantalk_hooked_actions[] = 'ninja_forms_ajax_submit';
$_cleantalk_hooked_actions[] = 'nf_ajax_submit';
$_cleantalk_hooked_actions[] = 'ninja_forms_process'; // Deprecated ?

/* Follow-Up Emails */
$_cleantalk_hooked_actions[] = 'fue_wc_set_cart_email';  // Don't check email via this plugin

/* Follow-Up Emails */
$_cleantalk_hooked_actions[] = 'fue_wc_set_cart_email';  // Don't check email via this plugin

/* The Fluent Form have the direct integration */
$_cleantalk_hooked_actions[] = 'fluentform_submit';

/* Estimation Forms have the direct integration */
if ( class_exists('LFB_Core') ) {
    $_cleantalk_hooked_actions[] = 'send_email';
}

/* Fusion Builder Avada Forms integration */
add_action('wp_ajax_nopriv_fusion_form_submit_form_to_database_email', 'ct_ajax_hook', 1);
$_cleantalk_hooked_actions[] = 'fusion_form_submit_form_to_database_email';
add_action('wp_ajax_nopriv_fusion_form_submit_form_to_email', 'ct_ajax_hook', 1);
$_cleantalk_hooked_actions[] = 'fusion_form_submit_form_to_email';
add_action('wp_ajax_nopriv_fusion_form_submit_ajax', 'ct_ajax_hook', 1);
$_cleantalk_hooked_actions[] = 'fusion_form_submit_ajax';

// Elementor Pro page builder forms
add_action('wp_ajax_elementor_pro_forms_send_form', 'apbct_form__elementor_pro__testSpam');
add_action('wp_ajax_nopriv_elementor_pro_forms_send_form', 'apbct_form__elementor_pro__testSpam');

/**
 * AjaxLogin plugin handler
 *
 * @param null $email
 */
function ct_validate_email_ajaxlogin($email = null)
{
    $email   = is_null($email) ? $email : Post::get('email');
    $email   = \Cleantalk\ApbctWP\Sanitize::cleanEmail($email);
    $is_good = ! ( ! filter_var($email, FILTER_VALIDATE_EMAIL) || email_exists($email));

    if ( class_exists('AjaxLogin') && Post::get('action') === 'validate_email' ) {
        $checkjs                            = apbct_js_test('ct_checkjs', $_POST);
        $sender_info['post_checkjs_passed'] = $checkjs;
        if ( $checkjs === null ) {
            $checkjs                              = apbct_js_test('ct_checkjs', $_COOKIE, true);
            $sender_info['cookie_checkjs_passed'] = $checkjs;
        }

        //Making a call
        $base_call_result = apbct_base_call(
            array(
                'sender_email'    => $email,
                'sender_nickname' => '',
                'sender_info'     => $sender_info,
                'js_on'           => $checkjs,
            ),
            true
        );

        $ct_result = $base_call_result['ct_result'];

        if ( $ct_result->allow === 0 ) {
            $is_good = false;
        }
    }

    if ( $is_good ) {
        $ajaxresult = array(
            'description' => null,
            'cssClass'    => 'noon',
            'code'        => 'success'
        );
    } else {
        $ajaxresult = array(
            'description' => 'Invalid Email',
            'cssClass'    => 'error-container',
            'code'        => 'error'
        );
    }

    $ajaxresult = json_encode($ajaxresult);
    print $ajaxresult;
    wp_die();
}

/**
 * AjaxLogin plugin handler
 *
 * @param $user_id
 *
 * @return mixed
 */
function ct_user_register_ajaxlogin($user_id)
{
    if ( class_exists('AjaxLogin') && Post::get('action') === 'register_submit' ) {
        $checkjs                            = apbct_js_test('ct_checkjs', $_POST);
        $sender_info['post_checkjs_passed'] = $checkjs;
        if ( $checkjs === null ) {
            $checkjs                              = apbct_js_test('ct_checkjs', $_COOKIE, true);
            $sender_info['cookie_checkjs_passed'] = $checkjs;
        }

        //Making a call
        $base_call_result = apbct_base_call(
            array(
                'sender_email'    => Post::get('email', null, 'cleanEmail'),
                'sender_nickname' => Post::get('login', null, 'cleanEmail'),
                'sender_info'     => $sender_info,
                'js_on'           => $checkjs,
            ),
            true
        );

        $ct_result = $base_call_result['ct_result'];

        if ( $ct_result->allow === 0 ) {
            wp_delete_user($user_id);
        }
    }

    return $user_id;
}

/**
 * Hook into MailChimp for WordPress `mc4wp_form_errors` filter.
 *
 * @param array $errors
 *
 * @return array
 * @throws Exception
 */
function ct_mc4wp_ajax_hook(array $errors)
{
    $result = ct_ajax_hook();

    // only return modified errors array when function returned a string value (the message key)
    if ( is_string($result) ) {
        $errors[] = $result;
    }

    return $errors;
}

/**
 * Main handler of ajax forms checking
 *
 * @param array|object $message_obj
 *
 * @return array|bool|string|null
 *
 * @throws Exception
 *
 * @psalm-suppress ComplexFunction
 */
function ct_ajax_hook($message_obj = null)
{
    global $current_user;

    $message_obj = (array)$message_obj;

    // Get current_user and set it globally
    apbct_wp_set_current_user($current_user instanceof WP_User ? $current_user : apbct_wp_get_current_user());

    // $_REQUEST['action'] to skip. Go out because of not spam data
    $skip_post = array(
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
        'acf/validate_save_post',
        //ACF validate post admin
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
        /* !! Do not add actions here. Use apbct_is_skip_request() function below !! */
        //Unknown plugin Ticket #25047
        'alhbrmeu',
        // Ninja Forms
        'nf_preview_update',
        'nf_save_form'
    );

    global $apbct;
    // Skip test if
    if ( ! $apbct->settings['forms__general_contact_forms_test'] || // Test disabled
         ! apbct_is_user_enable($apbct->user) || // User is admin, editor, author
         // (function_exists('get_current_user_id') && get_current_user_id() != 0) || // Check with default wp_* function if it's admin
         ( ! $apbct->settings['data__protect_logged_in'] && ($apbct->user instanceof WP_User) && $apbct->user->ID !== 0) || // Logged in user
         apbct_exclusions_check__url() || // url exclusions
         (Post::get('action') && in_array(Post::get('action'), $skip_post)) || // Special params
         (Get::get('action') && in_array(Get::get('action'), $skip_post)) ||  // Special params
         Post::get('quform_submit') || //QForms multi-paged form skip
         // QAEngine Theme fix
         ((string)current_filter() !== 'et_pre_insert_answer' &&
          (
              (isset($message_obj['author']) && (int)$message_obj['author'] === 0) ||
              (isset($message_obj['post_author']) && (int)$message_obj['post_author'] === 0)
          )
         ) ||
         (Post::get('action') === 'arm_shortcode_form_ajax_action' && Post::get('arm_action') === 'please-login') || //arm forms skip login
         (Post::get('action') === 'erf_login_user' && in_array('easy-registration-forms/erforms.php', apply_filters('active_plugins', get_option('active_plugins')))) || //Easy Registration Forms login form skip
         (Post::get('action') === 'mailpoet' && Post::get('endpoint') === 'ImportExport' && Post::get('method') === 'processImport') //Mailpoet import
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return false;
    }

    if ( apbct_is_skip_request(true) ) {
        do_action(
            'apbct_skipped_request',
            __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__ . '(' . apbct_is_skip_request(true) . ')',
            $_POST
        );

        return false;
    }

    //General post_info for all ajax calls
    $post_info = array(
        'comment_type' => 'feedback_ajax',
        'post_url'     => apbct_get_server_variable('HTTP_REFERER'), // Page URL must be an previous page
    );
    if ( Post::get('action') === 'cleantalk_force_ajax_check' ) {
        $post_info['comment_type'] = 'feedback_ajax_external_form';
    }

    $checkjs = apbct_js_test('ct_checkjs', $_COOKIE, true);

    //QAEngine Theme answers
    if ( ! empty($message_obj) && isset($message_obj['post_type'], $message_obj['post_content']) ) {
        $curr_user = get_user_by('id', $message_obj['author']);
        if ( ! $curr_user ) {
            $curr_user = get_user_by('id', $message_obj['post_author']);
        }
        $ct_post_temp['comment'] = $message_obj['post_content'];
        $ct_post_temp['email']   = $curr_user->data->user_email;
        $ct_post_temp['name']    = $curr_user->data->user_login;
    }

    //CSCF fix
    if ( Post::get('action') === 'cscf-submitform' ) {
        $ct_post_temp[] = $message_obj['comment_author'];
        $ct_post_temp[] = $message_obj['comment_author_email'];
        $ct_post_temp[] = $message_obj['comment_content'];
    }

    //??? fix
    if ( Post::get('target') && (Post::get('action') === 'request_appointment' || Post::get('action') === 'send_message') ) {
        $ct_post_temp           = $_POST;
        $ct_post_temp['target'] = 1;
    }

    //UserPro fix
    if ( Post::get('action') === 'userpro_process_form' && Post::get('template') === 'register' ) {
        $ct_post_temp              = $_POST;
        $ct_post_temp['shortcode'] = '';
    }
    //Pre-filled form 426869223
    if (
        Post::get('action') !== '' &&
        Post::get('response-email-address') !== '' &&
        Post::get('response-email-sender-address') !== '' &&
        Post::get('action') === 'contact-owner:send'
    ) {
        unset($_POST['response-email-address']);
        unset($_POST['response-email-sender-address']);
    }
    //Reviewer fix
    if ( Post::get('action') === 'rwp_ajax_action_rating' ) {
        $ct_post_temp['name']    = Post::get('user_name');
        $ct_post_temp['email']   = Post::get('user_email', null, 'cleanEmail');
        $ct_post_temp['comment'] = Post::get('comment');
    }
    //Woocommerce checkout
    if ( Post::get('action') === 'woocommerce_checkout' || Post::get('action') === 'save_data' ) {
        $post_info['comment_type'] = 'order';
        if ( empty($apbct->settings['forms__wc_checkout_test']) ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

            return false;
        }
    }
    //Easy Forms for Mailchimp
    if ( Post::get('action') === 'process_form_submission' ) {
        $post_info['comment_type'] = 'contact_enquire_wordpress_easy_forms_for_mailchimp';
        if ( Post::get('form_data') ) {
            $form_data     = explode('&', urldecode(Post::get('form_data')));
            $form_data_arr = array();
            foreach ( $form_data as $val ) {
                $form_data_element                    = explode('=', $val);
                $form_data_arr[$form_data_element[0]] = @$form_data_element[1];
            }
            if ( isset($form_data_arr['EMAIL']) ) {
                $ct_post_temp['email'] = $form_data_arr['EMAIL'];
            }
            if ( isset($form_data_arr['FNAME']) ) {
                $ct_post_temp['nickname'] = $form_data_arr['FNAME'];
            }
        }
    }
    if ( Post::get('action') === 'ufbl_front_form_action' ) {
        $ct_post_temp = $_POST;
        foreach ( $ct_post_temp as $key => $_value ) {
            if ( preg_match('/form_data_\d_name/', $key) ) {
                unset($ct_post_temp[$key]);
            }
        }
    }

    // Kali form integration
    if (Post::hasString('action', 'kaliforms_form_process')) {
        $ct_post_temp = Post::get('data');
    }

    // FixTeam Integration - preparation data for post filter
    if (
        apbct_is_theme_active('fixteam') &&
        Post::equal('action', 'send_sc_form') &&
        Post::get('data') !== ''
    ) {
        $form_data = Post::get('data');
        $form_data = explode('&', $form_data);

        for ($index = 0; $index < count($form_data); $index++) {
            if (stripos($form_data[$index], 'apbct_visible_fields') === 0) {
                unset($form_data[$index]);
            }
        }

        $form_data = implode('&', $form_data);
        parse_str($form_data, $ct_post_temp);
        $_POST['data'] = $form_data;
    }

    // Fusion Builder Avada Form integration
    if (
        Post::hasString('action', 'fusion_form_submit_form_to_database_email') ||
        Post::hasString('action', 'fusion_form_submit_form_to_email') ||
        Post::hasString('action', 'fusion_form_submit_ajax')
    ) {
        if (Post::get('formData')) {
            $form_data = Post::get('formData');
            $form_data = explode('&', $form_data);

            for ($index = 0; $index < count($form_data); $index++) {
                if (stripos($form_data[$index], 'apbct_visible_fields') === 0) {
                    unset($form_data[$index]);
                }
            }

            $form_data = implode('&', $form_data);
            $_POST['formData'] = $form_data;
        }
    }

    /**
     * Filter for POST
     */
    if (!empty($ct_post_temp)) {
        $input_array = apply_filters('apbct__filter_post', $ct_post_temp);
    } else {
        $input_array = apply_filters('apbct__filter_post', $_POST);
    }

    $ct_temp_msg_data = ct_get_fields_any($input_array);

    $sender_email    = $ct_temp_msg_data['email'] ?: '';
    $sender_nickname = $ct_temp_msg_data['nickname'] ?: '';
    $subject         = $ct_temp_msg_data['subject'] ?: '';
    $contact_form    = $ct_temp_msg_data['contact'] ?: true;
    $message         = $ct_temp_msg_data['message'] ?: array();
    if ( $subject !== '' ) {
        $message['subject'] = $subject;
    }

    // Skip submission if no data found
    if ( $contact_form === false ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return false;
    }

    // Mailpoet fix
    if ( isset($message['wysijaData'], $message['wysijaplugin'], $message['task'], $message['controller']) && $message['wysijaplugin'] === 'wysija-newsletters' && $message['controller'] === 'campaigns' ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return false;
    }

    // Mailpoet3 admin skip fix
    if ( Post::get('action') === 'mailpoet' && Post::get('method') === 'save' ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return false;
    }


    // WP Foto Vote Fix
    if ( ! empty($_FILES) ) {
        foreach ( $message as $key => $_value ) {
            if ( strpos($key, 'oje') !== false ) {
                do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

                return false;
            }
        }
    }

    /**
     * @todo Contact form detect
     */
    // Detect contact form an set it's name to $contact_form to use later
    $contact_form = null;
    foreach ( $_POST as $param => $_value ) {
        if ( strpos($param, 'et_pb_contactform_submit') === 0 ) {
            $contact_form = 'contact_form_divi_theme';
        }
        if ( strpos($param, 'avia_generated_form') === 0 ) {
            $contact_form = 'contact_form_enfold_theme';
        }
        if ( ! empty($contact_form) ) {
            break;
        }
    }

    $base_call_params = array(
        'message'         => $message,
        'sender_email'    => $sender_email,
        'sender_nickname' => $sender_nickname,
        'sender_info'     => array('post_checkjs_passed' => $checkjs),
        'post_info'       => $post_info,
        'js_on'           => $checkjs,
    );

    if ( apbct_is_exception_arg_request() ) {
        $base_call_params['exception_action'] = 1;
        $base_call_params['sender_info']['exception_description'] = apbct_is_exception_arg_request();
    }

    // EZ Form Calculator - clearing the message
    if (
        apbct_is_plugin_active('ez-form-calculator-premium/ezfc.php') &&
        Post::equal('action', 'ezfc_frontend')
    ) {
        if (!is_array($message)) {
            $message = preg_split('/\r\n|\r|\n/', $message);
        }

        foreach ($message as $key => $string) {
            if (
                $string === '__HIDDEN__' ||
                $string === '0' ||
                (int)$string !== 0
            ) {
                unset($message[$key]);
            }
        }

        $base_call_params['message'] = implode('\n', $message);
    }

    $base_call_result = apbct_base_call($base_call_params);
    $ct_result        = $base_call_result['ct_result'];

    if ( $ct_result->allow == 0 ) {
        if ( Post::get('action') === 'wpuf_submit_register' ) {
            $result = array('success' => false, 'error' => $ct_result->comment);
            @header('Content-Type: application/json; charset=' . get_option('blog_charset'));
            print json_encode($result);
            die();
        }

        if ( Post::get('action') === 'mymail_form_submit' ) {
            $result = array('success' => false, 'html' => $ct_result->comment);
            @header('Content-Type: application/json; charset=' . get_option('blog_charset'));
            print json_encode($result);
            die();
        }

        if ( Post::get('action') === 'wysija_ajax' && Post::get('task') !== 'send_preview' && Post::get('task') !== 'send_test_mail' ) {
            $result = array('result' => false, 'msgs' => array('updated' => array($ct_result->comment)));
            print Get::get('callback') . '(' . json_encode($result) . ');';
            die();
        }

        if ( Post::get('action') === 'cs_registration_validation' ) {
            $result = array("type" => "error", "message" => $ct_result->comment);
            print json_encode($result);
            die();
        }

        if ( Post::get('action') === 'request_appointment' || Post::get('action') === 'send_message' ) {
            print $ct_result->comment;
            die();
        }

        if ( Post::get('action') === 'zn_do_login' ) {
            print '<div id="login_error">' . $ct_result->comment . '</div>';
            die();
        }

        if ( Post::get('action') === 'vfb_submit' ) {
            $result = array('result' => false, 'message' => $ct_result->comment);
            @header('Content-Type: application/json; charset=' . get_option('blog_charset'));
            print json_encode($result);
            die();
        }

        if ( Post::get('action') === 'woocommerce_checkout' ) {
            print $ct_result->comment;
            die();
        }

        if ( Post::get('cma-action') === 'add' ) {
            $result = array('success' => 0, 'thread_id' => null, 'messages' => array($ct_result->comment));
            print json_encode($result);
            die();
        }

        if ( Post::get('action') === 'td_mod_register' ) {
            print json_encode(array('register', 0, $ct_result->comment));
            die();
        }

        if ( Post::get('action') === 'tmpl_ajax_check_user_email' ) {
            print "17,email";
            die();
        }

        if ( Post::get('action') === 'tevolution_submit_from_preview' || Post::get('action') === 'submit_form_recaptcha_validation' ) {
            print $ct_result->comment;
            die();
        }

        // WooWaitList
        // http://codecanyon.net/item/woowaitlist-woocommerce-back-in-stock-notifier/7103373
        if ( Post::get('action') === 'wew_save_to_db_callback' ) {
            $result            = array();
            $result['error']   = 1;
            $result['message'] = $ct_result->comment;
            $result['code']    = 5; // Unused code number in WooWaitlist
            print json_encode($result);
            die();
        }

        // UserPro
        if ( Post::get('action') === 'userpro_process_form' && Post::get('template') === 'register' ) {
            foreach ( $_POST as $key => $value ) {
                $output[\Cleantalk\ApbctWP\Sanitize::cleanXss($key)] = \Cleantalk\ApbctWP\Sanitize::cleanXss($value);
            }
            $output['template'] = $ct_result->comment;
            $output             = json_encode($output);
            print_r($output);
            die;
        }

        // Quick event manager
        if ( Post::get('action') === 'qem_validate_form' ) {
            $errors[] = 'registration_forbidden';
            $result   = array(
                'success' => 'false',
                'errors'  => $errors,
                'title'   => $ct_result->comment
            );
            print json_encode($result);
            die();
        }

        // Quick Contact Form
        if ( Post::get('action') === 'qcf_validate_form' ) {
            $result = array(
                'blurb'   => "<h1>" . $ct_result->comment . "</h1>",
                'display' => "Oops, got a few problems here",
                'errors'  => array(
                    0 => array(
                        'error' => 'error',
                        'name'  => 'name'
                    ),
                ),
                'success' => 'false',
            );
            print json_encode($result);
            die();
        }

        // Usernoise Contact Form
        if (
            Post::get('title') &&
            Post::get('email') &&
            Post::get('type') &&
            Post::get('ct_checkjs')
        ) {
            return array($ct_result->comment);
        }

        // amoForms
        if ( Post::get('action') === 'amoforms_submit' ) {
            $result = array(
                'result' => true,
                'type'   => "html",
                'value'  => "<h1 style='font-size: 25px; color: red;'>" . $ct_result->comment . "</h1>",
                'fast'   => false
            );
            print json_encode($result);
            die();
        }

        // MailChimp for Wordpress Premium
        if ( ! empty(Post::get('_mc4wp_form_id')) ) {
            return 'ct_mc4wp_response';
        }

        // QAEngine Theme answers
        if ( ! empty($message_obj) && isset($message_obj['post_type'], $message_obj['post_content']) ) {
            throw new Exception($ct_result->comment);
        }

        //ES Add subscriber
        if ( Post::get('action') === 'es_add_subscriber' ) {
            $result = array(
                'error' => 'unexpected-error',
            );
            print json_encode($result);
            die();
        }

        //Convertplug. Strpos because action value dynamically changes and depends on mailing service
        if ( strpos(Post::get('action'), '_add_subscriber') !== false ) {
            $result = array(
                'action'       => "message",
                'detailed_msg' => "",
                'email_status' => false,
                'message'      => "<h1 style='font-size: 25px; color: red;'>" . $ct_result->comment . "</h1>",
                'status'       => "error",
                'url'          => "none"
            );
            print json_encode($result);
            die();
        }

        // Ultimate Form Builder
        if ( Post::get('action') === 'ufbl_front_form_action' ) {
            $result = array(
                'error_keys'       => array(),
                'error_flag'       => 1,
                'response_message' => $ct_result->comment
            );
            print json_encode($result);
            die();
        }

        // Smart Forms
        if ( Post::get('action') === 'rednao_smart_forms_save_form_values' ) {
            $result = array(
                'message'        => $ct_result->comment,
                'refreshCaptcha' => 'n',
                'success'        => 'n'
            );
            print json_encode($result);
            die();
        }

        //cFormsII
        if ( Post::get('action') === 'submitcform' ) {
            header('Content-Type: application/json');
            $result = array(
                'no'          => Post::get('cforms_id', null, 'xss'),
                'result'      => 'failure',
                'html'        => $ct_result->comment,
                'hide'        => false,
                'redirection' => null
            );
            print json_encode($result);
            die();
        }

        //Contact Form by Web-Settler
        if ( Post::get('smFieldData') ) {
            $result = array(
                'signal'      => true,
                'code'        => 0,
                'thanksMsg'   => $ct_result->comment,
                'errors'      => array(),
                'isMsg'       => true,
                'redirectUrl' => null
            );
            print json_encode($result);
            die();
        }

        //Reviewer
        if ( Post::get('action') === 'rwp_ajax_action_rating' ) {
            $result = array(
                'success' => false,
                'data'    => array(0 => $ct_result->comment)
            );
            print json_encode($result);
            die();
        }

        // CouponXXL Theme
        if (
            Post::get('_wp_http_referer') &&
            Post::get('register_field') &&
            Post::get('action') &&
            strpos(Post::get('_wp_http_referer'), '/register/account') !== false &&
            Post::get('action') === 'register'
        ) {
            $result = array(
                'message' => '<div class="alert alert-error">' . $ct_result->comment . '</div>',
            );
            die(json_encode($result));
        }

        //ConvertPro
        if ( Post::get('action') === 'cp_v2_notify_admin' || Post::get('action') === 'cpro_notify_via_email' ) {
            $result = array(
                'success' => false,
                'data'    => array('error' => $ct_result->comment, 'style_slug' => 'convertprot-form'),
            );
            print json_encode($result);
            die();
        }

        //Easy Forms for Mailchimp
        if ( Post::get('action') === 'process_form_submission' ) {
            wp_send_json_error(
                array(
                    'error'    => 1,
                    'response' => $ct_result->comment
                )
            );
        }

        //Optin wheel
        if ( Post::get('action') === 'wof-lite-email-optin' || Post::get('action') === 'wof-email-optin' ) {
            wp_send_json_error(__($ct_result->comment, 'wp-optin-wheel'));
        }

        // Forminator
        if ( strpos(Post::get('action'), 'forminator_submit') !== false ) {
            wp_send_json_error(
                array(
                    'message' => $ct_result->comment,
                    'success' => false,
                    'errors'  => array(),
                    'behav'   => 'behaviour-thankyou',
                )
            );
        }

        // Easy Registration Form
        if ( strpos(Post::get('action'), 'erf_submit_form') !== false ) {
            wp_send_json_error(array(0 => array('username_error', $ct_result->comment)));
        }

        // Kali Form Integration
        if ( Post::hasString('action', 'kaliforms_form_process') ) {
            die(
                json_encode(
                    array(
                        'status' => 'ok',
                        'thank_you_message' => $ct_result->comment
                    )
                )
            );
        }

        if ( Post::hasString('action', 'fusion_form_submit_form_to_') ) {
            die(
                json_encode(
                    array(
                        'status' => 'error',
                        'info' => $ct_result->comment
                    )
                )
            );
        }

        // Regular block output
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked'     => true,
                        'comment'     => $ct_result->comment,
                        'stop_script' => Post::hasString('action', 'tve_leads_ajax_')
                            ? 1
                            : 0
                    )
                )
            )
        );
    }

    // Allow == 1
    //QAEngine Theme answers
    if ( ! empty($message_obj) && isset($message_obj['post_type'], $message_obj['post_content']) ) {
        return $message_obj;
    }
    // Force AJAX check
    if ( Post::get('action') === 'cleantalk_force_ajax_check' ) {
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked' => false,
                        'allow'   => true,
                    )
                )
            )
        );
    }

    return null;
}
