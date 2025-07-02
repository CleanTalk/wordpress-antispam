<?php

/**
 * AJAX functions
 */

use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;

$_cleantalk_ajax_actions_to_check = array();
$_cleantalk_ajax_actions_to_check[] = 'qcf_validate_form';            //Quick Contact Form
$_cleantalk_ajax_actions_to_check[] = 'amoforms_submit';            //amoForms

$_cleantalk_hooked_actions = array();
$_cleantalk_hooked_actions[] = 'rwp_ajax_action_rating'; //Don't check Reviewer plugin

$_cleantalk_hooked_actions[] = 'ct_feedback_comment';

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

/*hooks for visual form builder */
add_action('wp_ajax_nopriv_vfb_submit', 'ct_ajax_hook', 1);
add_action('wp_ajax_vfb_submit', 'ct_ajax_hook', 1);
$_cleantalk_hooked_actions[] = 'vfb_submit';

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

/* UsersWP plugin integration */
add_action('wp_ajax_nopriv_uwp_ajax_register', 'ct_ajax_hook', 1);
$_cleantalk_hooked_actions[] = 'uwp_ajax_register';

/**
 * AjaxLogin plugin handler
 *
 * @param null $email
 */
function ct_validate_email_ajaxlogin($email = null)
{
    if ( class_exists('AjaxLogin') && Post::get('action') === 'validate_email' ) {
        $email   = is_null($email) ? $email : Post::get('email');
        $email   = \Cleantalk\ApbctWP\Sanitize::cleanEmail($email);
        $is_good = ! ( ! filter_var($email, FILTER_VALIDATE_EMAIL) || email_exists($email));

        $sender_info = array();
        $checkjs                            = apbct_js_test(TT::toString(Post::get('ct_checkjs')));
        $sender_info['post_checkjs_passed'] = $checkjs;

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

        if (isset($base_call_result['ct_result'])) {
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
    //TODO probably dead code there, there is no such plugins with such hook found on wp.org
    if ( class_exists('AjaxLogin') && Post::get('action') === 'register_submit' ) {
        $sender_info = array();
        $checkjs                            = apbct_js_test(TT::toString(Post::get('ct_checkjs')));
        $sender_info['post_checkjs_passed'] = $checkjs;

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

        if (isset($base_call_result['ct_result'])) {
            $ct_result = $base_call_result['ct_result'];

            if ( $ct_result->allow === 0 ) {
                wp_delete_user($user_id);
            }
        }
    }

    return $user_id;
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
    global $apbct, $current_user;
    $reg_flag = false;

    $message_obj = (array)$message_obj;

    // Get current_user and set it globally
    apbct_wp_set_current_user($current_user instanceof WP_User ? $current_user : apbct_wp_get_current_user());

    if ( apbct_is_skip_request(true, $message_obj) ) {
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
        'post_url'     => Server::get('HTTP_REFERER'), // Page URL must be an previous page
    );

    $ct_post_temp = array();

    //QAEngine Theme answers
    if ( ! empty($message_obj) && isset($message_obj['post_type'], $message_obj['post_content']) ) {
        if (isset($message_obj['author'])) {
            $curr_user = get_user_by('id', $message_obj['author']);
            if ( ! $curr_user && isset($message_obj['post_author']) ) {
                $curr_user = get_user_by('id', $message_obj['post_author']);
            }
            if ( is_object($curr_user) ) {
                $ct_post_temp['comment'] = $message_obj['post_content'];
                $ct_post_temp['email']   = $curr_user->data->user_email;
                $ct_post_temp['name']    = $curr_user->data->user_login;
            }
        }
    }

    // SiteReviews integration
    if ( Post::getString('action', 'glsr_public_action') &&
        apbct_is_plugin_active('site-reviews/site-reviews.php')
    ) {
        $post_info['comment_type'] = 'site_reviews_integration';
        if (isset($_POST['site-reviews']['title'])) {
            $ct_post_temp['title'] = $_POST['site-reviews']['title'];
        }
        if (isset($_POST['site-reviews']['name'])) {
            $ct_post_temp['nickname'] = $_POST['site-reviews']['name'];
        }
        if (isset($_POST['site-reviews']['email'])) {
            $ct_post_temp['email'] = $_POST['site-reviews']['email'];
        }
        if (isset($_POST['site-reviews']['content'])) {
            $ct_post_temp['comment'] = $_POST['site-reviews']['content'];
        }
    }

    // Nasa registration
    if ( Post::get('action') === 'nasa_process_register' ) {
        $post_info['comment_type'] = 'nasa_process_register';
        $reg_flag = true;
    }

    //NSL integration
    if ( Post::get('action') === 'cleantalk_nsl_ajax_check' ) {
        $post_info['comment_type'] = 'contact_form_wordpress_nsl';
        $reg_flag = true;
    }

    // protect outside iframes
    if ( Post::get('action') === 'cleantalk_outside_iframe_ajax_check' ) {
        $post_info['comment_type'] = 'contact_form_wordpress_outside_iframe';
    }

    //CSCF fix
    if ( Post::get('action') === 'cscf-submitform' &&
        isset($message_obj['comment_author'], $message_obj['comment_author_email'], $message_obj['comment_content'])
    ) {
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

    //Woocommerce.  Skip Paystation gateway service request.
    if (
        Post::get('action') === 'complete_order'  &&
        in_array('paystation_payment_gateway', array_values($_POST))
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);
        return false;
    }
    //Easy Forms for Mailchimp
    if ( Post::get('action') === 'process_form_submission' ) {
        $post_info['comment_type'] = 'contact_enquire_wordpress_easy_forms_for_mailchimp';
        if ( Post::get('form_data') ) {
            $form_data     = explode('&', urldecode(TT::toString(Post::get('form_data'))));
            $form_data_arr = array();
            foreach ( $form_data as $val ) {
                $form_data_element                    = explode('=', $val);
                if (isset($form_data_element[0], $form_data_element[1])) {
                    $form_data_arr[$form_data_element[0]] = @$form_data_element[1];
                }
            }
            $ct_post_temp = array();
            if ( isset($form_data_arr['EMAIL']) ) {
                $ct_post_temp['email'] = $form_data_arr['EMAIL'];
            }
            if ( isset($form_data_arr['FNAME']) ) {
                $ct_post_temp['nickname'] = $form_data_arr['FNAME'];
            }
        }
    }

    // FixTeam Integration - preparation data for post filter
    if (
        apbct_is_theme_active('fixteam') &&
        Post::equal('action', 'send_sc_form') &&
        Post::get('data') !== ''
    ) {
        $form_data = Post::get('data');
        $form_data = explode('&', TT::toString($form_data));

        for ($index = 0; $index < count($form_data); $index++) {
            if (stripos($form_data[$index], 'apbct_visible_fields') === 0) {
                unset($form_data[$index]);
            }
        }

        $form_data = implode('&', $form_data);
        parse_str($form_data, $ct_post_temp);
        $_POST['data'] = $form_data;
    }

    if (Post::hasString('action', 'rx_front_end_review_submit')) {
        if (Post::get('formInput')) {
            $form_data = Post::get('formInput');
            $prepare_form_data = array();

            if (is_array($form_data)) {
                foreach ($form_data as $row) {
                    if (isset($row['name']) && $row['name'] === 'apbct_visible_fields') {
                        continue;
                    }

                    if (isset($row['name']) && $row['name'] === 'ct_bot_detector_event_token') {
                        continue;
                    }

                    if (isset($row['name']) && $row['name'] === 'ct_no_cookie_hidden_field') {
                        if ($apbct->data['cookies_type'] === 'none') {
                            $no_cookie_data = isset($row['value']) ? $row['value'] : '';
                            $apbct->stats['no_cookie_data_taken'] = \Cleantalk\ApbctWP\Variables\NoCookie::setDataFromHiddenField($no_cookie_data);
                            $apbct->save('stats');
                        }
                        continue;
                    }

                    $prepare_form_data[] = $row;
                }
            }

            $_POST['formInput'] = $prepare_form_data;
        }
    }

    //divi subscription form needs to force alt cookies
    if ( Post::hasString('action', 'et_pb_submit_subscribe_form') ) {
        Cookie::$force_alt_cookies_global = true;
    }

    // thriveleads modification to check gravity forms
    if ( Post::get('action') === 'tve_api_form_submit' ) {
        unset($_POST['ct_checkjs']);
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

    $sender_email    = isset($ct_temp_msg_data['email']) ? $ct_temp_msg_data['email'] : '';
    $sender_emails_array = isset($ct_temp_msg_data['emails_array']) ? $ct_temp_msg_data['emails_array'] : '';
    $sender_nickname = isset($ct_temp_msg_data['nickname']) ? $ct_temp_msg_data['nickname'] : '';
    $subject         = isset($ct_temp_msg_data['subject']) ? $ct_temp_msg_data['subject'] : '';
    $contact_form    = isset($ct_temp_msg_data['contact']) ? $ct_temp_msg_data['contact'] : true;
    $message         = isset($ct_temp_msg_data['message']) ? $ct_temp_msg_data['message'] : array();
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
        if ( strpos((string)$param, 'et_pb_contactform_submit') === 0 ) {
            $contact_form = 'contact_form_divi_theme';
        }
        if ( strpos((string)$param, 'avia_generated_form') === 0 ) {
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
        'post_info'       => $post_info,
    );

    if ( apbct_is_exception_arg_request() ) {
        $base_call_params['exception_action'] = 1;
        $base_call_params['sender_info']['exception_description'] = apbct_is_exception_arg_request();
        $base_call_params['sender_info']['sender_emails_array'] = $sender_emails_array;
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

    $base_call_result = apbct_base_call($base_call_params, $reg_flag);

    if (!isset($base_call_result['ct_result'])) {
        return null;
    }

    $ct_result = $base_call_result['ct_result'];

    if ( $ct_result->allow == 0 ) {
        if ( Post::get('action') === 'wpuf_submit_register' ) {
            $result = array('success' => false, 'error' => $ct_result->comment);
            @header('Content-Type: application/json; charset=' . get_option('blog_charset'));
            print json_encode($result);
            die();
        }

        if ( Post::getString('action', 'glsr_public_action') ) {
            $result = array(
                'success' => false,
                'data' => array(
                    'errors' => array(),
                    'message' => $ct_result->comment,
                ),
            );
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
            $output = array();
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
            $errors = array();
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
        if ( strpos(TT::toString(Post::get('action')), '_add_subscriber') !== false ) {
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
            strpos(TT::toString(Post::get('_wp_http_referer')), '/register/account') !== false &&
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
                'data'    => array('error' => 'Invalid email address.', 'style_slug' => 'convertprot-form'), //cannot use custom message because of ConvertPro JS error message forcing
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
        if ( strpos(TT::toString(Post::get('action')), 'forminator_submit') !== false ) {
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
        if ( strpos(TT::toString(Post::get('action')), 'erf_submit_form') !== false ) {
            wp_send_json_error(array(0 => array('username_error', $ct_result->comment)));
        }

        // Site Reviews Integration
        if ( Post::hasString('action', 'glsr_action') ) {
            wp_send_json_error(
                array(
                    'code' => 'CODE',
                    'error' => 'ERROR',
                    'message' => $ct_result->comment,
                    'notices' => 'NOTICES',
                )
            );
        }

        if (
            apbct_is_plugin_active('qsm-save-resume/qsm-save-resume.php') &&
            Post::hasString('action', 'qmn_process_quiz')
        ) {
            die(
                json_encode(
                    array(
                        'quizExpired' => false,
                        'display' => $ct_result->comment,
                        'redirect' => '',
                        'result_status' => array(
                            'save_response' => 0
                        )
                    )
                )
            );
        }

        // bricksextras/bricksextras.php
        if (
            apbct_is_plugin_active('bricksextras/bricksextras.php') &&
            Post::hasString('action', 'bricks_form_submit')
        ) {
            die(
                json_encode(
                    array(
                        'success' => false,
                        'data' => array(
                            'type' => 'error',
                            'message' => $ct_result->comment,
                        )
                    )
                )
            );
        }

        // Plugin Name: DIGITS: WordPress Mobile Number Signup and Login; ajax register action digits_forms_ajax
        if (
            apbct_is_plugin_active('digits/digit.php') &&
            Post::get('action') === 'digits_forms_ajax' &&
            Post::get('type') === 'register'
        ) {
            wp_send_json_error(
                array(
                    'message' => $ct_result->comment
                )
            );
            die();
        }

        // Plugin Name: User Registration; ajax register action user_registration_user_form_submit
        if (
            (
                apbct_is_plugin_active('user-registration/user-registration.php')
                ||
                apbct_is_plugin_active('user-registration-pro/user-registration.php')
            ) &&
            Post::get('action') === 'user_registration_user_form_submit'
        ) {
            wp_send_json_error(
                array(
                    'message' => $ct_result->comment
                )
            );
            die();
        }

        // Plugin Name: eForm - WordPress Form Builder; ajax action ipt_fsqm_save_form
        if (
            apbct_is_plugin_active('wp-fsqm-pro/ipt_fsqm.php') &&
            Post::get('action') === 'ipt_fsqm_save_form'
        ) {
            $return = array(
                'success' => false,
                'errors' => array(
                    0 => array(
                        'id' => '',
                        'msgs' => array( $ct_result->comment ),
                    ),
                ),
            );
            echo json_encode((object)$return);
            die();
        }

        if ( Post::get('action') === 'rx_front_end_review_submit' ) {
            wp_send_json($ct_result->comment);
        }

        // Plugin Name: Nextend Social Login and Register; jax register action cleantalk_nsl_ajax_check
        if ( Post::get('action') === 'cleantalk_nsl_ajax_check' ) {
            echo json_encode(
                array(
                    'apbct' => array(
                        'blocked'     => true,
                        'comment'     => $ct_result->comment,
                        'stop_script' => apbct__stop_script_after_ajax_checking()
                    )
                )
            );
            die();
        }

        // protect outside iframes
        if ( Post::get('action') === 'cleantalk_outside_iframe_ajax_check' ) {
            echo json_encode(
                array(
                    'apbct' => array(
                        'blocked'     => true,
                        'comment'     => $ct_result->comment,
                    )
                )
            );
            die();
        }

        if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'wpmlsubscribe') {
            $block_msg = sprintf('<div class="newsletters-acknowledgement"><p style="color: darkred">%s</p></div>', $ct_result->comment);
            echo $block_msg;
            die();
        }

        // Porto theme register action on login popup
        if (Post::get('action') === 'porto_account_login_popup_register') {
            echo json_encode(
                array(
                    'loggedin' => false,
                    'message'  => $ct_result->comment,
                )
            );
            die();
        }

        // ACF forms
        if ( Post::get('action') === 'acf/validate_save_post' ) {
            echo json_encode(
                array(
                    'success' => true,
                    'data'    => array(
                        'valid'  => 0,
                        'errors' => array(
                            array(
                                'message' => $ct_result->comment,
                            )
                        )
                    )
                )
            );
            die();
        }

        // Regular block output
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked'     => true,
                        'comment'     => $ct_result->comment,
                        'stop_script' => apbct__stop_script_after_ajax_checking()
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

    // Nextend Social Login and Register AJAX check
    if ( Post::get('action') === 'cleantalk_nsl_ajax_check' ) {
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked' => false,
                        'allow'   => true,
                        'comment' => $ct_result->comment,
                    )
                )
            )
        );
    }

    // protect outside iframes
    if ( Post::get('action') === 'cleantalk_outside_iframe_ajax_check' ) {
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked' => false,
                        'allow'   => true,
                        'comment' => $ct_result->comment,
                    )
                )
            )
        );
    }

    return null;
}

function apbct__stop_script_after_ajax_checking()
{
    if (
        Post::hasString('action', 'tve_leads_ajax_') ||
        (Post::hasString('action', 'xoo_el_form_action') && Post::hasString('_xoo_el_form', 'register')) ||
        (Post::get('elqFormName') && Post::get('elqSiteId') && Post::get('elqFormSubmissionToken'))
    ) {
        return 1;
    }

    return 0;
}
