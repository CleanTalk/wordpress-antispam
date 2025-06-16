<?php

use Cleantalk\ApbctWP\DTO\GetFieldsAnyDTO;
use Cleantalk\ApbctWP\Escape;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\Honeypot;
use Cleantalk\ApbctWP\LinkConstructor;
use Cleantalk\ApbctWP\Localize\CtPublicFunctionsLocalize;
use Cleantalk\ApbctWP\Localize\CtPublicLocalize;
use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\AltSessions;
use Cleantalk\ApbctWP\Variables\Request;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;

//MailChimp premium. Prepare block message for AJAX response.
if ( class_exists('Cleantalk\Antispam\Integrations\MailChimp') ) {
    add_filter('mc4wp_form_messages', array('Cleantalk\Antispam\Integrations\MailChimp', 'addFormResponse'));
}

/*
 * Fluent Booking shortcode localize CT script and vars.
 */
add_action('fluent_booking/before_calendar_event_landing_page', function () {
    echo CtPublicFunctionsLocalize::getCode();
    echo CtPublicLocalize::getCode();
    $js_url = APBCT_URL_PATH . '/js/apbct-public-bundle.min.js?' . APBCT_VERSION;
    echo '<script src="' . $js_url . '" type="application/javascript"></script>';
}, 1);

/**
 * Function to set validate function for CCF form
 * Input - Consistently each form field
 * Returns - String. Validate function
 */
function ct_ccf($_callback, $_value, $_field_id, $_type)
{
    return 'ct_validate_ccf_submission';
}

$ct_global_temporary_data = array();
/**
 * Validate function for CCF form. Gathering data. Multiple calls.
 * Input - void. Global $ct_global_temporary_data
 * Returns - String. CleanTalk comment.
 *
 * @param $value
 * @param $_field_id
 * @param $_required
 *
 * @return bool|string|null
 * @psalm-suppress InvalidArrayOffset
 */
function ct_validate_ccf_submission($value, $_field_id, $_required)
{
    global $ct_global_temporary_data, $apbct;

    //If the check for contact forms enabled
    if ( ! $apbct->settings['forms__contact_forms_test'] ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return true;
    }

    //If the check for logged in users enabled
    if ( $apbct->settings['data__protect_logged_in'] == 1 && is_user_logged_in() ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return true;
    }

    //Accumulate data
    $ct_global_temporary_data[] = $value;

    //If it's the last field of the form
    (! isset($ct_global_temporary_data['count']) ? $ct_global_temporary_data['count'] = 1 : $ct_global_temporary_data['count']++);
    $form_id = Sanitize::cleanInt(Post::get('form_id'));
    if ( $ct_global_temporary_data['count'] != count(get_post_meta($form_id, 'ccf_attached_fields', true)) ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return true;
    }

    unset($ct_global_temporary_data['count']);

    /**
     * Filter for POST
     */
    $input_array = apply_filters('apbct__filter_post', $_POST);

    //Getting request params
    $ct_temp_msg_data = ct_get_fields_any($input_array);

    unset($ct_global_temporary_data);

    $sender_email    = isset($ct_temp_msg_data['email']) ? $ct_temp_msg_data['email'] : '';
    $sender_emails_array = isset($ct_temp_msg_data['emails_array']) ? $ct_temp_msg_data['emails_array'] : '';
    $sender_nickname = isset($ct_temp_msg_data['nickname']) ? $ct_temp_msg_data['nickname'] : '';
    $subject         = isset($ct_temp_msg_data['subject']) ? $ct_temp_msg_data['subject'] : '';
    $message         = isset($ct_temp_msg_data['message']) ? $ct_temp_msg_data['message'] : array();

    if ( $subject !== '' ) {
        $message['subject'] = $subject;
    }

    $post_info = array();
    $post_info['comment_type'] = 'feedback_custom_contact_forms';
    $post_info['post_url']     = Server::get('HTTP_REFERER');

    $checkjs = apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true) ?: apbct_js_test(Sanitize::cleanTextField(Post::get('ct_checkjs')));

    //Making a call
    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => $post_info,
            'js_on'           => $checkjs,
            'sender_info'     => array('sender_url' => null, 'sender_emails_array' => $sender_emails_array),
        )
    );

    $result = true;
    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];
        if ( $ct_result->allow == 0 ) {
            $result = $ct_result->comment;
        }
    }

    return $result;
}

function ct_woocommerce_wishlist_check($args)
{
    global $apbct;

    //Protect logged in users
    if ( $args['wishlist_status'] ) {
        if ( $apbct->settings['data__protect_logged_in'] == 0 ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

            return $args;
        }
    }

    //If the IP is a Google bot
    $hostname = gethostbyaddr(TT::toString(Server::get('REMOTE_ADDR')));
    if ( ! strpos($hostname, 'googlebot.com') ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $args;
    }

    //Getting request params
    $message = '';
    $subject = '';
    $email   = $args['wishlist_owner_email'];
    if ( $args['wishlist_first_name'] !== '' || $args['wishlist_last_name'] !== '' ) {
        $nickname = trim($args['wishlist_first_name'] . " " . $args['wishlist_last_name']);
    } else {
        $nickname = '';
    }

    $post_info = array();
    $post_info['comment_type'] = 'feedback';
    $post_info['post_url']     = Server::get('HTTP_REFERER');

    $checkjs = apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true) ?: apbct_js_test(Sanitize::cleanTextField(Post::get('ct_checkjs')));

    //Making a call
    $base_call_result = apbct_base_call(
        array(
            'message'         => $subject . " " . $message,
            'sender_email'    => $email,
            'sender_nickname' => $nickname,
            'post_info'       => $post_info,
            'js_on'           => $checkjs,
            'sender_info'     => array('sender_url' => null),
        )
    );

    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];

        if ( $ct_result->allow == 0 ) {
            wp_die(
                "<h1>"
                . __('Spam protection by CleanTalk', 'cleantalk-spam-protect')
                . "</h1><h2>" . $ct_result->comment . "</h2>",
                '',
                array(
                    'response'       => 403,
                    "back_link"      => true,
                    "text_direction" => 'ltr'
                )
            );
        }
    }

    return $args;
}

/**
 * Public function - Tests for Pirate contact forms
 * return NULL
 */
function apbct_form__piratesForm__testSpam()
{
    global $apbct;

    //Check for enabled option
    if ( ! $apbct->settings['forms__contact_forms_test'] ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return;
    }

    /**
     * Filter for POST
     */
    $input_array = apply_filters('apbct__filter_post', $_POST);

    //Getting request params
    $ct_temp_msg_data = ct_get_fields_any($input_array);

    $sender_email    = isset($ct_temp_msg_data['email']) ? $ct_temp_msg_data['email'] : '';
    $sender_emails_array = isset($ct_temp_msg_data['emails_array']) ? $ct_temp_msg_data['emails_array'] : '';
    $sender_nickname = isset($ct_temp_msg_data['nickname']) ? $ct_temp_msg_data['nickname'] : '';
    $subject         = isset($ct_temp_msg_data['subject']) ? $ct_temp_msg_data['subject'] : '';
    $message         = isset($ct_temp_msg_data['message']) ? $ct_temp_msg_data['message'] : array();

    if ( $subject !== '' ) {
        $message = array_merge(array('subject' => $subject), $message);
    }

    $post_info = array();
    $post_info['comment_type'] = 'contact_form_wordpress_feedback_pirate';
    $post_info['post_url']     = Server::get('HTTP_REFERER');

    //Making a call
    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => $post_info,
            'js_on'           => apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true),
            'sender_info'     => array('sender_url' => null, 'sender_emails_array' => $sender_emails_array),
        )
    );

    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];

        if ( $ct_result->allow == 0 ) {
            wp_die(
                "<h1>"
                . __('Spam protection by CleanTalk', 'cleantalk-spam-protect')
                . "</h1><h2>" . $ct_result->comment . "</h2>",
                '',
                array(
                    'response'       => 403,
                    "back_link"      => true,
                    "text_direction" => 'ltr'
                )
            );
        }
    }
}

/**
 * Adds hidden filed to comment form
 */
function ct_comment_form($_post_id)
{
    global $apbct;

    if ( apbct_is_user_enable() === false ) {
        return false;
    }

    if ( ! $apbct->settings['forms__comments_test'] ) {
        return false;
    }

    ct_add_hidden_fields();

    if ( $apbct->settings['trusted_and_affiliate__under_forms'] === '1' ) {
        echo Escape::escKsesPreset(
            apbct_generate_trusted_text_html('label'),
            'apbct_public__trusted_text'
        );
    }

    return null;
}

/**
 * Public function - Insert JS code for spam tests
 *
 * @param $_fields
 * @param $form
 *
 * @return false|null
 */
function apbct_form__formidable__footerScripts($_fields, $form)
{
    global $apbct, $ct_checkjs_frm;

    if ( ! $apbct->settings['forms__contact_forms_test'] ) {
        return false;
    }

    $ct_checkjs_key   = ct_get_checkjs_value();
    $ct_frm_base_name = 'form_';
    $ct_frm_name      = $ct_frm_base_name . $form->form_key;

    echo "var input = document.createElement('input');
    input.setAttribute('type', 'hidden');
    input.setAttribute('name', '$ct_checkjs_frm');
    input.setAttribute('value', '$ct_checkjs_key');
    for (i = 0; i < document.forms.length; i++) {
        if (typeof document.forms[i].id == 'string'){
			if(document.forms[i].id.search('$ct_frm_name') != -1) {
            document.forms[i].appendChild(input);
			}
        }
    }";
}

/**
 * Public function - Test Formidable data for spam activity
 *
 * @param $errors
 * @param $form
 *
 * @return array with errors if spam has found
 * @psalm-suppress InvalidScalarArgument
 */
function apbct_form__formidable__testSpam($errors, $_form)
{
    global $apbct, $ct_comment;

    if ( ! $apbct->settings['forms__contact_forms_test'] ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $errors;
    }

    // Skip processing for logged in users.
    if ( ! $apbct->settings['data__protect_logged_in'] && is_user_logged_in() ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $errors;
    }

    // Skipping, if not sending, but filling out the form step by step. For Formidable Pro
    if (apbct_is_plugin_active('formidable-pro/formidable-pro.php')) {
        foreach (array_keys($_POST) as $key) {
            if (strpos($key, 'frm_page_order') === 0) {
                return $errors;
            }
        }
    }

    /**
     * Filter for POST
     */
    $input_array = apply_filters('apbct__filter_post', Post::get('item_meta'));

    $form_data = array();
    foreach ( $input_array as $key => $value ) {
        $form_data['item_meta[' . $key . ']'] = $value;
    }

    $ct_temp_msg_data = ct_get_fields_any($form_data);

    $sender_email    = isset($ct_temp_msg_data['email']) ? $ct_temp_msg_data['email'] : '';
    $sender_emails_array = isset($ct_temp_msg_data['emails_array']) ? $ct_temp_msg_data['emails_array'] : '';
    $sender_nickname = isset($ct_temp_msg_data['nickname']) ? $ct_temp_msg_data['nickname'] : '';
    $message         = isset($ct_temp_msg_data['message']) ? $ct_temp_msg_data['message'] : array();

    // @todo convert key 'NUM' to 'input_meta[NUM]'
    // Adding 'input_meta[]' to every field /Formidable fix/
    // because filed names is 'input_meta[NUM]'
    // Get all scalar values
    $tmp_message  = array();
    $tmp_message2 = array();
    foreach ( $message as $key => $value ) {
        if ( is_scalar($value) ) {
            $tmp_message[$key] = $value;
        } else {
            $tmp_message2[$key] = $value;
        }
    }
    // Replacing key to input_meta[NUM] for scalar values
    $tmp_message = array_flip($tmp_message);
    foreach ( $tmp_message as &$value ) {
        if ( strpos($value, 'item_meta[') === false ) {
            $value = 'item_meta[' . $value . ']';
        }
    }
    unset($value);
    // @ToDO Need to be solved psalm notice about InvalidScalarArgument
    $tmp_message = array_flip($tmp_message);
    // Combine it with non-scalar values
    $message = array_merge($tmp_message, $tmp_message2);

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => array('comment_type' => 'contact_form_wordpress_formidable'),
            'sender_info'     => array(
                'sender_emails_array' => $sender_emails_array,
            ),
        )
    );

    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];

        if ( $ct_result->allow == 0 ) {
            $ct_comment = $ct_result->comment;
            if (apbct_is_ajax()) {
                // search for a suitable field
                $key_field = apbct__formidable_get_key_field_for_ajax_response($_form);

                $result = array (
                    'errors' =>
                        array (
                            $key_field => $ct_result->comment
                        ),
                    'content' => '',
                    'pass' => false,
                    'error_message' => '<div class="frm_error_style" role="status"><p>' . $ct_result->comment . '</p></div>',
                );

                echo json_encode($result, JSON_FORCE_OBJECT);
                die();
            }

            ct_die(null, null);
        }
    }

    return $errors;
}

/**
 * Get field key for ajax response of formidable form
 */
function apbct__formidable_get_key_field_for_ajax_response($_form = array())
{
    $key_field = '113';

    $item_meta = Post::get('item_meta');
    if (is_array($item_meta) && count($item_meta) > 1) {
        $keys = array_keys($item_meta);
        $key_field = isset($keys[1]) ? $keys[1] : '113';
    } elseif (is_array($_form) && isset($_form['item_meta'])) {
        foreach ($_form['item_meta'] as $key => $value) {
            if ($value) {
                $key_field = $key;
                break;
            }
        }
    }

    return $key_field;
}

/**
 * Public filter 'bbp_*' - Get new topic name to global $ct_bbp_topic
 *
 * @param mixed[] $comment Comment string
 *
 * @return  mixed[] $comment Comment string
 * @psalm-suppress UnusedVariable
 */
function ct_bbp_get_topic($topic)
{
    global $ct_bbp_topic;

    $ct_bbp_topic = $topic;

    return $topic;
}

/**
 * Public filter 'bbp_*' - Checks topics, replies by cleantalk
 *
 * @param string $comment Comment string
 */
function ct_bbp_new_pre_content($comment)
{
    global $apbct, $current_user;

    if ( ! $apbct->settings['forms__comments_test'] ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $comment;
    }

    // Skip processing for logged in users and admin.
    if ( ! $apbct->settings['data__protect_logged_in'] && (is_user_logged_in() || apbct_exclusions_check()) ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $comment;
    }

    $current_filter = current_filter();
    if ( 'bbp_new_reply_pre_content' === $current_filter ) {
        $hooked_action = 'bbp_new_reply_pre_extras';
    } else {
        $hooked_action = 'bbp_new_topic_pre_extras';
    }
    add_action($hooked_action, function () use ($current_user, $comment) {
        $post_info = array();
        $post_info['comment_type'] = 'bbpress_comment';
        /** @psalm-suppress UndefinedFunction */
        $post_info['post_url']     = bbp_get_topic_permalink();

        if ( is_user_logged_in() ) {
            $sender_email    = $current_user->user_email;
            $sender_nickname = $current_user->display_name;
        } else {
            $sender_email    = Sanitize::cleanEmail(Post::get('bbp_anonymous_email'));
            $sender_nickname = Sanitize::cleanUser(Post::get('bbp_anonymous_name'));
        }

        $base_call_result = apbct_base_call(
            array(
                'message'         => $comment,
                'sender_email'    => $sender_email,
                'sender_nickname' => $sender_nickname,
                'post_info'       => $post_info,
                'sender_info'     => array('sender_url' => Sanitize::cleanUrl(Post::get('bbp_anonymous_website'))),
            )
        );

        if ( isset($base_call_result['ct_result']) ) {
            $ct_result = $base_call_result['ct_result'];

            if ( $ct_result->allow == 0 ) {
                /** @psalm-suppress UndefinedFunction */
                bbp_add_error('bbp_reply_content', $ct_result->comment);
            }
        }
    }, 1);

    return $comment;
}

/**
 * Public filter 'bbp_*' - Checks edit replies by cleantalk
 *
 * @param string $comment Comment string
 * @param int $comment_id Comment ID
 * @psalm-suppress UnusedParam
 */
function ct_bbp_edit_pre_content($comment, $comment_id)
{
    global $apbct, $current_user;

    if ( ! $apbct->settings['forms__comments_test'] ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $comment;
    }

    // Skip processing for logged in users and admin.
    if ( ! $apbct->settings['data__protect_logged_in'] && (is_user_logged_in() || apbct_exclusions_check()) ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $comment;
    }

    $post_info = array();
    $post_info['comment_type'] = 'bbpress_edit_comment';
    /** @psalm-suppress UndefinedFunction */
    $post_info['post_url']     = bbp_get_topic_permalink();

    if ( is_user_logged_in() ) {
        $sender_email    = $current_user->user_email;
        $sender_nickname = $current_user->display_name;
    } else {
        $sender_email    = Sanitize::cleanEmail(Post::get('bbp_anonymous_email'));
        $sender_nickname = Sanitize::cleanUser(Post::get('bbp_anonymous_name'));
    }

    $base_call_result = apbct_base_call(
        array(
            'message'         => $comment,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => $post_info,
            'sender_info'     => array('sender_url' => Sanitize::cleanUrl(Post::get('bbp_anonymous_website'))),
        )
    );

    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];

        if ( $ct_result->allow == 0 ) {
            /** @psalm-suppress UndefinedFunction */
            bbp_add_error('bbp_reply_content', $ct_result->comment);
        }
    }

    return $comment;
}

/**
 * Insert a hidden field to registration form
 * @return null|bool
 */
function ct_register_form()
{
    global $ct_checkjs_register_form, $apbct;

    if ( $apbct->settings['forms__registrations_test'] == 0 ) {
        return false;
    }

    ct_add_hidden_fields($ct_checkjs_register_form, false, false, false, false);
    echo Honeypot::generateHoneypotField('wp_register');
    if ( $apbct->settings['trusted_and_affiliate__under_forms'] === '1' ) {
        echo Escape::escKsesPreset(
            apbct_generate_trusted_text_html('label'),
            'apbct_public__trusted_text'
        );
    }
    return null;
}

/**
 * Adds notification text to login form - to inform about approved registration
 * @return null
 */
function ct_login_message($message)
{
    global $errors, $apbct, $apbct_cookie_register_ok_label;

    if ( $apbct->settings['forms__registrations_test'] != 0 ) {
        if ( 'registered' === Get::get('checkemail') ) {
            if ( Cookie::get($apbct_cookie_register_ok_label) ) {
                if ( is_wp_error($errors) ) {
                    $errors->add(
                        'ct_message',
                        sprintf(
                            __('Registration approved by %s.', 'cleantalk-spam-protect'),
                            '<b style="color: #49C73B;">Clean</b><b style="color: #349ebf;">Talk</b>'
                        ),
                        'message'
                    );
                }
            }
        }
    }

    return $message;
}


/**
 * Test users registration for pPress
 * @return void|WP_Error with errors
 */
function ct_registration_errors_ppress($reg_errors, $_form_id)
{
    $email = Sanitize::cleanEmail(Post::get('reg_email'));
    $login = Sanitize::cleanUser(Post::get('reg_username'));

    $reg_errors = ct_registration_errors($reg_errors, $login, $email);

    return $reg_errors;
}

/**
 * Test users registration for multisite environment
 * @return array|mixed with errors
 */
function ct_registration_errors_wpmu($errors)
{
    $wpmu = false;

    // Multisite actions
    $sanitized_user_login = null;
    if ( isset($errors['user_name']) ) {
        $sanitized_user_login = $errors['user_name'];
        $wpmu                 = true;
    }

    $user_email = null;
    if ( isset($errors['user_email']) ) {
        $user_email = $errors['user_email'];
        $wpmu       = true;
    }

    if ( $wpmu && isset($errors['errors']->errors) && count($errors['errors']->errors) > 0 ) {
        return $errors;
    }

    $errors['errors'] = ct_registration_errors($errors['errors'], $sanitized_user_login, $user_email);

    // Show CleanTalk errors in user_name field
    if (isset($errors['errors']) &&
        is_object($errors['errors']) &&
        property_exists($errors['errors'], 'errors') &&
        is_array($errors['errors']->errors) &&
        isset($errors['errors']->errors['ct_error'])
    ) {
        $errors['errors']->errors['user_name'] = $errors['errors']->errors['ct_error'];
        unset($errors['errors']->errors['ct_error']);
    }

    return $errors;
}

/**
 * Shell for action register_post
 *
 * @param $sanitized_user_login
 * @param $user_email
 * @param $errors
 *
 * @return void|WP_Error
 */
function ct_register_post($sanitized_user_login, $user_email, $errors)
{
    return ct_registration_errors($errors, $sanitized_user_login, $user_email);
}


/**
 * Check messages for external plugins
 * @return array with checking result;
 */
function ct_test_message($nickname, $email, $_ip, $text)
{
    $base_call_result = apbct_base_call(
        array(
            'message'         => $text,
            'sender_email'    => $email,
            'sender_nickname' => $nickname,
            'post_info'       => array('comment_type' => 'feedback_plugin_check'),
            'js_on'           => apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true),
        )
    );

    $result = array(
        'allow'   => true,
        'comment' => 'OK',
    );
    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];
        $result = array(
            'allow'   => $ct_result->allow,
            'comment' => $ct_result->comment,
        );
    }

    return $result;
}

/**
 * Check registrations for external plugins
 * @return array with checking result;
 */
function ct_test_registration($nickname, $email, $ip = null)
{
    global $ct_checkjs_register_form;

    $sender_info = array();
    if ( apbct_js_test(Sanitize::cleanTextField(Post::get($ct_checkjs_register_form))) ) {
        $checkjs                            = apbct_js_test(Sanitize::cleanTextField(Post::get($ct_checkjs_register_form)));
        $sender_info['post_checkjs_passed'] = $checkjs;
    } else {
        $checkjs                              = apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true);
        $sender_info['cookie_checkjs_passed'] = $checkjs;
    }

    //Making a call
    $base_call_result = apbct_base_call(
        array(
            'sender_ip'       => $ip,
            'sender_email'    => $email,
            'sender_nickname' => $nickname,
            'sender_info'     => $sender_info,
            'js_on'           => $checkjs,
        ),
        true
    );

    $result = array(
        'allow'   => true,
        'comment' => 'OK',
    );
    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];
        ct_hash($ct_result->id);
        $result = array(
            'allow'   => $ct_result->allow,
            'comment' => $ct_result->comment,
        );
    }

    return $result;
}

/**
 * Test users registration
 *
 * @param      $errors
 * @param null|mixed $sanitized_user_login
 * @param null|mixed $user_email
 *
 * @return void|WP_Error
 * @psalm-suppress UnusedVariable
 */
function ct_registration_errors($errors, $sanitized_user_login = null, $user_email = null)
{
    global $ct_checkjs_register_form, $apbct_cookie_request_id_label, $apbct_cookie_register_ok_label, $apbct_cookie_request_id, $bp, $ct_signup_done, $ct_negative_comment, $apbct, $ct_registration_error_comment, $cleantalk_executed;
    $reg_flag = true;

    // Go out if a registered user action
    if ( apbct_is_user_enable() === false ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $errors;
    }

    if ( $apbct->settings['forms__registrations_test'] == 0 ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $errors;
    }

    // The function already executed
    // It happens when used ct_register_post();
    if ( $ct_signup_done && is_object($errors) && count($errors->errors) > 0 ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        if ($errors instanceof WP_Error) {
            return $errors;
        } else {
            return new WP_Error('registration_error', 'An unexpected error occurred.');
        }
    }

    if ( Post::get('wpmem_reg_page') && apbct_is_plugin_active('wp-members/wp-members.php') ) {
        return $errors;
    }

    $facebook = false;
    // Facebook registration
    $fb_userdata = Post::get('FB_userdata');
    if ( is_array($fb_userdata) ) {
        if ( $sanitized_user_login === null && isset($fb_userdata['name']) ) {
            $sanitized_user_login = Sanitize::cleanUser($fb_userdata['name']);
            $facebook = true;
        }
        if ($user_email === null && isset($fb_userdata['email'])) {
            $user_email = Sanitize::cleanEmail($fb_userdata['email']);
            $facebook = true;
        }
    }

    // BuddyPress actions
    $buddypress = false;
    if ( $sanitized_user_login === null && Post::get('signup_username') ) {
        $sanitized_user_login = Sanitize::cleanUser(Post::get('signup_username'));
        $buddypress           = true;
    }
    if ( $user_email === null && Post::get('signup_email') ) {
        $user_email = Sanitize::cleanEmail(Post::get('signup_email'));
        $buddypress = true;
    }

    // Get BuddyPress core instance if available
    $bp = function_exists('buddypress') ? buddypress() : null;

    // Skip BuddyPress request already contained validation errors
    if ( ! empty($bp->signup->errors) ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);
        return $errors;
    }

    // Break tests because we already have servers response
    if ( $buddypress && $ct_signup_done ) {
        if ( $ct_negative_comment ) {
            $bp->signup->errors['signup_username'] = $ct_negative_comment;
        }
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $errors;
    }

    if ( current_filter() === 'woocommerce_registration_errors' ) {
        $checkjs        = apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true);
        $checkjs_post   = null;
        $checkjs_cookie = $checkjs;
    } else {
        // This hack can be helpful when plugin uses with untested themes&signups plugins.
        $checkjs_post   = apbct_js_test(Sanitize::cleanTextField(Post::get($ct_checkjs_register_form)));
        $checkjs_cookie = apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true);
        $checkjs        = $checkjs_cookie ?: $checkjs_post;
    }

    $sender_info = array(
        'post_checkjs_passed'   => $checkjs_post,
        'cookie_checkjs_passed' => $checkjs_cookie,
        'form_validation'       => ! empty($errors)
            ? json_encode(
                array(
                    'validation_notice' => $errors->get_error_message(),
                    'page_url'          => TT::toString(Server::get('HTTP_HOST')) . TT::toString(Server::get('REQUEST_URI')),
                )
            )
            : null,
    );

    /**
     * Changing the type of check for BuddyPress
     */
    if ( Post::get('signup_username') && Post::get('signup_email') ) {
        // if buddy press set up custom fields
        $reg_flag = ! empty(Post::get('signup_profile_field_ids'));
    }

    /**
     * Changing the type of check for Avada Fusion
     */
    if ( Post::get('fusion_login_box') ) {
        $reg_flag = true;
    }

    if (current_filter() === 'woocommerce_registration_errors') {
        if (!is_null($sanitized_user_login) && strpos($sanitized_user_login, '.') !== false) {
            $username_parts = explode('.', $sanitized_user_login);
            $sanitized_user_login = implode(' ', $username_parts);
        }
    }

    $base_call_array = array(
        'sender_email'    => $user_email,
        'sender_nickname' => $sanitized_user_login,
        'sender_info'     => $sender_info,
        'js_on'           => $checkjs,
    );

    if ( !$reg_flag ) {
        $field_values = '';
        $fields_numbers_to_check = explode(',', TT::toString(Post::get('signup_profile_field_ids')));
        foreach ( $fields_numbers_to_check as $field_number ) {
            $field_name = 'field_' . $field_number;
            $field_value = Post::get($field_name) ? Sanitize::cleanTextareaField(Post::get($field_name)) : '';
            $field_values .= $field_value . "\n";
        }
        $base_call_array['message'] = $field_values;
    }

    $base_call_result = apbct_base_call(
        $base_call_array,
        $reg_flag
    );

    if ( ! isset($base_call_result['ct_result']) ) {
        return $errors;
    }

    $ct_result = $base_call_result['ct_result'];
    ct_hash($ct_result->id);

    // Change mail notification if license is out of date
    if ( $apbct->data['moderate'] == 0 &&
        ($ct_result->fast_submit == 1 || $ct_result->blacklisted == 1 || $ct_result->js_disabled == 1)
    ) {
        $apbct->sender_email = $user_email;
        $apbct->sender_ip    = Helper::ipGet('real');
        add_filter(
            'wp_new_user_notification_email_admin',
            'apbct_registration__Wordpress__changeMailNotification',
            100,
            3
        );
    }

    $ct_signup_done = true;
    $cleantalk_executed = true;

    if ( $ct_result->inactive != 0 ) {
        ct_send_error_notice($ct_result->comment);
        return $errors;
    }

    if ( $ct_result->allow == 0 ) {
        $ct_negative_comment = $ct_result->comment;
        $ct_registration_error_comment = $ct_result->comment;

        if (current_filter() === 'woocommerce_registration_errors') {
            add_action('woocommerce_store_api_checkout_order_processed', ['Cleantalk\Antispam\IntegrationsByClass\Woocommerce', 'storeApiCheckoutOrderProcessed'], 10, 2);
        }

        if ( $buddypress === true ) {
            $bp->signup->errors['signup_username'] = $ct_result->comment;
        }

        if ( $facebook ) {
            /** @psalm-suppress InvalidArrayOffset */
            $_POST['FB_userdata']['email'] = '';
            /** @psalm-suppress InvalidArrayOffset */
            $_POST['FB_userdata']['name']  = '';
            return;
        }

        if ((defined('MGM_PLUGIN_NAME') || apbct_is_plugin_active('bbpress/bbpress.php')) &&
            current_filter() !== 'woocommerce_registration_errors'
        ) {
            ct_die_extended($ct_result->comment);
        }

        if ( is_wp_error($errors) ) {
            $errors->add('ct_error', $ct_result->comment);
        }

        return $errors;
    }

    if ( $ct_result->id !== null ) {
        $apbct_cookie_request_id = $ct_result->id;
        Cookie::set($apbct_cookie_register_ok_label, $ct_result->id, time() + 10, '/');
        Cookie::set($apbct_cookie_request_id_label, $ct_result->id, time() + 10, '/');
    }

    return $errors;
}

/**
 * Changes email notification for newly registered user
 *
 * @param array $wp_new_user_notification_email_admin Body of email notification
 * @param $_user
 * @param $_blogname
 *
 * @return array Body for email notification
 */
function apbct_registration__Wordpress__changeMailNotification(
    $wp_new_user_notification_email_admin,
    $_user,
    $_blogname
) {
    global $apbct;
    $link = LinkConstructor::buildCleanTalkLink(
        'email_wp_spam_registration_passed',
        'my',
        array(
            'user_token' => $apbct->user_token,
            'cp_mode' => 'antispam'
        )
    );
    $wp_new_user_notification_email_admin['message'] = PHP_EOL
        . __(
            'CleanTalk Anti-Spam: This registration is spam.',
            'cleantalk-spam-protect'
        )
        . "\n" . __(
            'CleanTalk\'s Anti-Spam database:',
            'cleantalk-spam-protect'
        )
        . "\n" . 'IP: ' . $apbct->sender_ip
        . "\n" . 'Email: ' . $apbct->sender_email
        . PHP_EOL . PHP_EOL .
        __(
            'Activate protection in your Anti-Spam Dashboard: ',
            'cleantalk-spam-protect'
        )
        . $link
        . PHP_EOL . '---'
        . PHP_EOL
        . (isset($wp_new_user_notification_email_admin['message']) ? $wp_new_user_notification_email_admin['message'] : '');

    return $wp_new_user_notification_email_admin;
}

/**
 * Checks Ultimate Members registration for spam
 *
 * @param array $args forms arguments with names and values
 *
 * @return mixed
 * @psalm-suppress UndefinedFunction
 * @psalm-suppress UnusedVariable
 */
function apbct_registration__UltimateMembers__check($args)
{
    global $apbct, $cleantalk_executed;

    $sender_info = array();

    if ( isset(UM()->form()->errors) ) {
        $sender_info['previous_form_validation'] = true;
        $sender_info['validation_notice']        = json_encode(UM()->form()->errors);
    }

    if ( $apbct->settings['forms__registrations_test'] == 0 ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $args;
    }

    $checkjs                            = apbct_js_test('ct_checkjs_register_form', (bool)$args);
    $sender_info['post_checkjs_passed'] = $checkjs;

    // This hack can be helpfull when plugin uses with untested themes&signups plugins.
    if ( $checkjs == 0 ) {
        $checkjs                              = apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true);
        $sender_info['cookie_checkjs_passed'] = $checkjs;
    }

    $base_call_result = apbct_base_call(
        array(
            'sender_email'    => isset($args['user_email']) ? $args['user_email'] : '',
            'sender_nickname' => isset($args['user_login']) ? $args['user_login'] : '',
            'sender_info'     => $sender_info,
            'js_on'           => $checkjs,
        ),
        true
    );

    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];

        $cleantalk_executed = true;

        if ( $ct_result->inactive != 0 ) {
            ct_send_error_notice($ct_result->comment);

            return $args;
        }

        if ( $ct_result->allow == 0 ) {
            UM()->form()->add_error('user_password', $ct_result->comment);
        }
    }

    return $args;
}

/**
 * Checks registration error and set it if it was dropped
 *
 * @param $errors
 * @param null $_sanitized_user_login
 * @param null $_user_email
 *
 * @return mixed
 */
function ct_check_registration_errors($errors, $_sanitized_user_login = null, $_user_email = null)
{
    global $bp, $ct_registration_error_comment;

    if ( $ct_registration_error_comment ) {
        if ( isset($bp) ) {
            if ( method_exists($bp, 'signup') ) {
                if ( method_exists($bp->signup, 'errors') ) {
                    if ( isset($bp->signup->errors['signup_username']) ) {
                        if ( $bp->signup->errors['signup_username'] != $ct_registration_error_comment ) {
                            $bp->signup->errors['signup_username'] = $ct_registration_error_comment;
                        }
                    }
                }
            }
        }

        if ( isset($errors) ) {
            if ( method_exists($errors, 'errors') ) {
                if ( isset($errors->errors['ct_error']) ) {
                    if ( $errors->errors['ct_error'][0] != $ct_registration_error_comment ) {
                        $errors->add('ct_error', $ct_registration_error_comment);
                    }
                }
            }
        }
    }

    return $errors;
}


/**
 * Set user meta (ct_hash) for successes registration
 */
function apbct_user_register($user_id)
{
    $hash = ct_hash();
    if ( ! empty($hash) ) {
        update_user_meta($user_id, 'ct_hash', $hash);
    }
}


/**
 * Test for JetPack contact form
 */
function ct_grunion_contact_form_field_html($r, $_field_label)
{
    global $ct_checkjs_jpcf, $ct_jpcf_patched, $ct_jpcf_fields, $apbct;

    if (
        $apbct->settings['forms__contact_forms_test'] == 1 &&
        $ct_jpcf_patched === false &&
        preg_match("/(text|email)/i", $r)
    ) {
        // Looking for element name prefix
        $name_patched = false;
        foreach ( $ct_jpcf_fields as $v ) {
            if ( $name_patched === false && preg_match("/(g\d-)$v/", $r, $matches) ) {
                if ( isset($matches[1]) ) {
                    $ct_checkjs_jpcf = $matches[1] . $ct_checkjs_jpcf;
                    $name_patched    = true;
                }
            }
        }

        $r               .= ct_add_hidden_fields($ct_checkjs_jpcf, true);
        $ct_jpcf_patched = true;
    }

    return $r;
}

/**
 * Test for JetPack contact form
 * @psalm-suppress UnusedVariable
 */
function ct_contact_form_is_spam($form)
{
    global $ct_checkjs_jpcf, $apbct, $ct_comment;

    if ( $apbct->settings['forms__contact_forms_test'] == 0 ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return null;
    }

    $sender_email    = null;
    $sender_nickname = null;
    $message         = '';
    if ( isset($form['comment_author_email']) ) {
        $sender_email = $form['comment_author_email'];
    }

    if ( isset($form['comment_author']) ) {
        $sender_nickname = $form['comment_author'];
    }

    if ( isset($form['comment_content']) ) {
        $message = $form['comment_content'];
    }

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => array('comment_type' => 'contact_form_wordpress_grunion'),
            'sender_info'     => array('sender_url' => @$form['comment_author_url']),
        )
    );

    $result = true;
    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];
        $result = ! $ct_result->allow;

        if ( $ct_result->allow == 0 ) {
            $ct_comment = $ct_result->comment;
            ct_die(null, null);
            exit;
        }
    }

    return $result;
}

/**
 * @param $_is_spam
 * @param $form
 *
 * @return bool|null
 * @psalm-suppress UnusedVariable
 */
function ct_contact_form_is_spam_jetpack($_is_spam, $form)
{
    global $apbct, $ct_comment, $ct_checkjs_jpcf;

    if ( $apbct->settings['forms__contact_forms_test'] == 0 ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return null;
    }

    $base_call_result = apbct_base_call(
        array(
            'message'         => isset($form['comment_content']) ? $form['comment_content'] : '',
            'sender_email'    => isset($form['comment_author_email']) ? $form['comment_author_email'] : null,
            'sender_nickname' => isset($form['comment_author']) ? $form['comment_author'] : null,
            'post_info'       => array('comment_type' => 'contact_form_wordpress_grunion'),
            'sender_info'     => array('sender_url' => @$form['comment_author_url']),
        )
    );

    $result = true;
    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];
        $result = ! $ct_result->allow;

        if ( $ct_result->allow == 0 ) {
            $ct_comment = $ct_result->comment;
            ct_die(null, null);
            exit;
        }
    }

    return $result;
}

/**
 * Inserts anti-spam hidden to WP Maintenance Mode (wpmm)
 */
function apbct_form__wpmm__addField()
{
    ct_add_hidden_fields('ct_checkjs', false, true, true);
}

/**
 * Inserts anti-spam hidden to CF7
 */
function apbct_form__contactForm7__addField($html)
{
    global $ct_checkjs_cf7, $apbct;

    if ( $apbct->settings['forms__contact_forms_test'] == 0 ) {
        return $html;
    }

    $html .= ct_add_hidden_fields($ct_checkjs_cf7, true);
    $html .= Honeypot::generateHoneypotField('wp_contact_form_7');
    if ( $apbct->settings['trusted_and_affiliate__under_forms'] === '1' ) {
        $html .= Escape::escKsesPreset(
            apbct_generate_trusted_text_html('label_left'),
            'apbct_public__trusted_text'
        );
    }
    return $html;
}

/**
 * Test spam for Contact Form 7 (CF7) right before validation
 *
 * @param null|object $result
 * @param null $_tags
 *
 * @global State $apbct
 */
function apbct_form__contactForm7__tesSpam__before_validate($result = null, $_tags = null)
{
    global $apbct;

    if ( $result && method_exists($result, 'get_invalid_fields') ) {
        $invalid_fields = $result->get_invalid_fields();
        if ( ! empty($invalid_fields) && is_array($invalid_fields) ) {
            $apbct->validation_error = $invalid_fields[key($invalid_fields)]['reason'];
            apbct_form__contactForm7__testSpam(false);
        }
    }

    return $result;
}

/**
 * Test CF7 message for spam
 * @psalm-suppress UnusedVariable
 */
function apbct_form__contactForm7__testSpam($spam, $_submission = null)
{
    global $ct_checkjs_cf7, $apbct, $ct_cf7_comment;

    if (
        $apbct->settings['forms__contact_forms_test'] == 0 ||
        ($spam === false && defined('WPCF7_VERSION') && WPCF7_VERSION < '3.0.0') ||
        ($spam === true && defined('WPCF7_VERSION') && WPCF7_VERSION >= '3.0.0' && ! Post::get('apbct_visible_fields')) ||
        ($apbct->settings['data__protect_logged_in'] != 1 && apbct_is_user_logged_in()) || // Skip processing for logged in users.
        apbct_exclusions_check__url() ||
        isset($apbct->cf7_checked)
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $spam;
    }

    apbct_form__get_no_cookie_data();

    $checkjs = apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true) ?: apbct_js_test(Sanitize::cleanTextField(Post::get($ct_checkjs_cf7)));
    /**
     * Filter for POST
     */
    $input_array = apply_filters('apbct__filter_post', $_POST);

    $ct_temp_msg_data = ct_get_fields_any($input_array);

    $sender_email    = isset($ct_temp_msg_data['email']) ? $ct_temp_msg_data['email'] : '';
    $sender_emails_array = isset($ct_temp_msg_data['emails_array']) ? $ct_temp_msg_data['emails_array'] : '';
    $sender_nickname = isset($ct_temp_msg_data['nickname']) ? $ct_temp_msg_data['nickname'] : '';
    $subject         = isset($ct_temp_msg_data['subject']) ? $ct_temp_msg_data['subject'] : '';
    $message         = isset($ct_temp_msg_data['message']) ? $ct_temp_msg_data['message'] : array();
    if ( $subject !== '' ) {
        $message = array_merge(array('subject' => $subject), $message);
    }

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'js_on'           => $checkjs,
            'post_info'       => array('comment_type' => 'contact_form_wordpress_cf7'),
            'sender_info'     => array(
                'form_validation' => ! isset($apbct->validation_error)
                    ? null
                    : json_encode(array(
                        'validation_notice' => $apbct->validation_error,
                        'page_url'          => TT::toString(Server::get('HTTP_HOST')) . TT::toString(Server::get('REQUEST_URI')),
                    )),
                'sender_emails_array' => $sender_emails_array,
            ),
        )
    );

    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];

        // Change mail notification if license is out of date
        if ( $apbct->data['moderate'] == 0 &&
            ($ct_result->fast_submit == 1 || $ct_result->blacklisted == 1 || $ct_result->js_disabled == 1)
        ) {
            $apbct->sender_email = $sender_email;
            $apbct->sender_ip    = Helper::ipGet();
            add_filter('wpcf7_mail_components', 'apbct_form__contactForm7__changeMailNotification');
        }

        if ( $ct_result->allow == 0 ) {
            $ct_cf7_comment = $ct_result->comment;

            add_filter('wpcf7_display_message', 'apbct_form__contactForm7__showResponse', 10, 2);

            add_filter('wpcf7_skip_mail', function () {
                add_filter("wpcf7_feedback_response", function ($response) {
                    global $ct_cf7_comment;
                    $response["status"] = "mail_sent_ng";
                    $response["message"] = $ct_cf7_comment;
                    return $response;
                }, 10);
            }, 10);

            // Flamingo: save or not the spam entry
            if ( ! $apbct->settings['forms__flamingo_save_spam'] ) {
                add_filter('wpcf7_flamingo_submit_if', function () {
                    return ['mail_sent', 'mail_failed'];
                });
            }

            $spam = defined('WPCF7_VERSION') && WPCF7_VERSION >= '3.0.0';
        } else {
            //clear form service fields for advanced-cf7-db integration
            if ( apbct_is_plugin_active('advanced-cf7-db/advanced-cf7-db.php') ) {
                add_filter('vsz_cf7_modify_form_before_insert_data', 'apbct_form_contactForm7__advancedCF7DB_clear_ct_service_fields');
            }
        }

        $apbct->cf7_checked = true;
    }

    return $spam;
}

/**
 * Changes CF7 status message
 *
 * @param $message
 * @param string $status
 *
 * @return mixed|string
 */
function apbct_form__contactForm7__showResponse($message, $status = 'spam')
{
    global $ct_cf7_comment;

    if ( $status === 'spam' ) {
        $message = $ct_cf7_comment;
    }

    return $message;
}

/**
 * Changes email notification for success subscription for Contact Form 7
 *
 * @param array $component Arguments for email notification
 *
 * @return array Arguments for email notification
 */
function apbct_form__contactForm7__changeMailNotification($component)
{
    global $apbct;

    $original_body = isset($component['body']) ? $component['body'] : '';

    $component['body'] =
        __('CleanTalk Anti-Spam: This message could be spam.', 'cleantalk-spam-protect')
        . PHP_EOL . __('CleanTalk\'s Anti-Spam database:', 'cleantalk-spam-protect')
        . PHP_EOL . 'IP: ' . $apbct->sender_ip
        . PHP_EOL . 'Email: ' . $apbct->sender_email
        . PHP_EOL . sprintf(
            //HANDLE LINK
            __('If you want to be sure activate protection in your Anti-Spam Dashboard: %s.', 'clentalk'),
            'https://cleantalk.org/my/?cp_mode=antispam&utm_source=newsletter&utm_medium=email&utm_campaign=cf7_activate_antispam&user_token=' . $apbct->user_token
        )
        . PHP_EOL . '---' . PHP_EOL . PHP_EOL
        . $original_body;

    return (array)$component;
}

/**
 * Clear service fields from saving during advanced-cf7-db plugin work.
 * @param stdClass $contact_form_obj
 *
 * @return stdClass
 */
function apbct_form_contactForm7__advancedCF7DB_clear_ct_service_fields(stdClass $contact_form_obj)
{
    $submission_fields = ! empty($contact_form_obj->posted_data)
        ? $contact_form_obj->posted_data
        : array();
    $submission_fields = apbct__filter_form_data($submission_fields);
    foreach ($submission_fields as $key => $_value) {
        if (
            strpos($key, 'apbct__email_id') !== false ||
            strpos($key, 'apbct_event_id') !== false ||
            strpos($key, 'ct_checkjs_cf7') !== false
        ) {
            unset($submission_fields[$key]);
        }
    }
    $contact_form_obj->posted_data = $submission_fields;

    return $contact_form_obj;
}

/**
 * Test Mailoptin subscribe form for spam
 *
 * @return void
 * @global State $apbct
 */
function apbct_form__mo_subscribe_to_email_list__testSpam()
{
    $input_array = apply_filters('apbct__filter_post', $_POST);
    $params = ct_get_fields_any($input_array);
    $sender_emails_array = isset($params['emails_array']) ? $params['emails_array'] : '';

    $base_call_result = apbct_base_call(
        array(
            'sender_email'    => isset($params['email']) ? $params['email'] : '',
            'sender_nickname' => isset($input_array['mo-name']) ? $input_array['mo-name'] : '',
            'post_info'       => array('comment_type' => 'subscribe_form_wordpress_mailoptin'),
            'sender_info'     => array(
                'sender_emails_array' => $sender_emails_array,
            ),
        )
    );

    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];
        if ( $ct_result->allow == 0 ) {
            wp_send_json([
                'success' => false,
                'message' => $ct_result->comment
            ]);
        }
    }
}

/**
 * Test LearnPress form for spam
 *
 * @return void
 */
function apbct_form__learnpress__testSpam()
{
    $params = ct_gfa(apply_filters('apbct__filter_post', $_POST));

    $sender_info = [];
    if ( ! empty($params['emails_array']) ) {
        $sender_info['sender_emails_array'] = $params['emails_array'];
    }
    $base_call_result = apbct_base_call(
        array(
            'sender_email'    => isset($params['email']) ? $params['email'] : Post::get('email'),
            'sender_nickname' => isset($params['nickname']) ? $params['nickname'] : Post::get('first_name'),
            'post_info'       => array('comment_type' => 'signup_form_wordpress_learnpress'),
            'sender_info'     => $sender_info,
        ),
        true
    );

    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];
        if ( $ct_result->allow == 0 ) {
            $data = [
                'result' => 'fail',
                'messages' => $ct_result->comment,
            ];
            echo '<-- LP_AJAX_START -->';
            echo wp_json_encode($data);
            echo '<-- LP_AJAX_END -->';
            die;
        }
    }
}

/**
 * Test Appointment Booking Calendar form for spam
 *
 * @return void
 */
function apbct_form__appointment_booking_calendar__testSpam()
{
    global $ct_comment;

    $params = ct_gfa(apply_filters('apbct__filter_post', $_POST));

    $sender_info = [];

    if ( ! empty($params['emails_array']) ) {
        $sender_info['sender_emails_array'] = $params['emails_array'];
    }

    $base_call_result = apbct_base_call(
        array(
            'sender_email'    => isset($params['email']) ? $params['email'] : Post::get('email'),
            'sender_nickname' => isset($params['nickname']) ? $params['nickname'] : Post::get('first_name'),
            'post_info'       => array('comment_type' => 'signup_form_wordpress_learnpress'),
            'sender_info'     => $sender_info,
        )
    );

    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];
        if ( $ct_result->allow == 0 ) {
            $ct_comment = $ct_result->comment;
            ct_die(null, null);
            exit;
        }
    }
}

/**
 * Test OptimizePress form for spam
 *
 * @return void
 */
function apbct_form__optimizepress__testSpam()
{
    $params = ct_gfa(apply_filters('apbct__filter_post', $_POST));

    $sender_info = [];
    if ( ! empty($params['emails_array']) ) {
        $sender_info['sender_emails_array'] = $params['emails_array'];
    }
    $base_call_result = apbct_base_call(
        array(
            'sender_email'    => isset($params['email']) ? $params['email'] : Post::get('email'),
            'sender_nickname' => isset($params['nickname']) ? $params['nickname'] : Post::get('first_name'),
            'post_info'       => array('comment_type' => 'subscribe_form_wordpress_optimizepress'),
            'sender_info'     => $sender_info,
        )
    );

    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];
        if ( $ct_result->allow == 0 ) {
            if (!headers_sent()) {
                header('HTTP/1.0 409 Forbidden');
            }
            wp_send_json([
                'message' => $ct_result->comment
            ]);
        }
    }
}

/**
 * Test Metform subscribe form for spam
 *
 * @return void
 */
function apbct_form__metform_subscribe__testSpam()
{
    $input_array = apply_filters('apbct__filter_post', $_POST);
    $params = ct_get_fields_any($input_array);
    $sender_info = [];
    if ( ! empty($params['emails_array']) ) {
        $sender_info['sender_emails_array'] = $params['emails_array'];
    }
    $base_call_result = apbct_base_call(
        array(
            'sender_email'    => isset($params['email']) ? $params['email'] : '',
            'sender_nickname' => isset($params['nickname']) ? $params['nickname'] : '',
            'post_info'       => array('comment_type' => 'subscribe_form_wordpress_metform'),
            'sender_info'   => $sender_info,
        )
    );

    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];
        if ( $ct_result->allow == 0 ) {
            wp_send_json([
                'status' => 0,
                'error' => $ct_result->comment,
                'data' => [
                    'message' => '',
                ]
            ]);
        }
    }
}

/**
 * Test Ninja Forms message for spam
 *
 * @return void
 * @global State $apbct
 */
function apbct_form__ninjaForms__testSpam()
{
    global $apbct, $cleantalk_executed;

    Cookie::$force_alt_cookies_global = true;

    if ( $cleantalk_executed ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return;
    }

    if (
        $apbct->settings['forms__contact_forms_test'] == 0 ||
        ($apbct->settings['data__protect_logged_in'] != 1 && is_user_logged_in()) || // Skip processing for logged in users.
        apbct_exclusions_check__url()
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return;
    }

    //skip ninja PRO service requests
    if ( Post::get('nonce_ts') ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return;
    }

    $checkjs = apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true);

    try {
        $gfa_dto = apbct_form__ninjaForms__collect_fields_new();
    } catch (\Exception $_e) {
        // It is possible here check the reason if the new way collecting fields is not available.
        $gfa_dto = apbct_form__ninjaForms__collect_fields_old();
    }

    if ( $gfa_dto->nickname === '' || $gfa_dto->email === '' ) {
        $form_data = json_decode(TT::toString(Post::get('formData')), true);
        if ( ! $form_data ) {
            $form_data = json_decode(stripslashes(TT::toString(Post::get('formData'))), true);
        }
        if ( function_exists('Ninja_Forms') && isset($form_data['fields']) ) {
            /** @psalm-suppress UndefinedFunction */
            $nf_form_fields_info = Ninja_Forms()->form()->get_fields();
            $nf_form_fields_info_array = [];
            foreach ($nf_form_fields_info as $field) {
                $nf_form_fields_info_array[$field->get_id()] = [
                    'field_key' => $field->get_setting('key'),
                    'field_type' => $field->get_setting('type'),
                    'field_label' => $field->get_setting('label'),
                ];
            }

            $nf_form_fields = $form_data['fields'];
            $nickname = '';
            $email = '';
            // $fields = [];
            foreach ($nf_form_fields as $field) {
                $field_info = $nf_form_fields_info_array[$field['id']];
                // $fields['nf-field-' . $field['id'] . '-' . $field_info['field_type']] = $field['value'];
                if ( stripos($field_info['field_key'], 'name') !== false ) {
                    $nickname = $field['value'];
                }
                if ( stripos($field_info['field_key'], 'email') !== false ) {
                    $email = $field['value'];
                }
            }

            $gfa_dto->nickname = $nickname;
            $gfa_dto->email = $email;
        }
    }

    $sender_email           = $gfa_dto->email;
    $sender_emails_array    = $gfa_dto->emails_array;
    $sender_nickname        = $gfa_dto->nickname;
    $subject                = $gfa_dto->subject;
    $message                = $gfa_dto->message;
    if ( $subject != '' ) {
        $message = array_merge(array('subject' => $subject), $message);
    }

    //Ninja Forms xml fix
    foreach ( $message as $key => $value ) {
        if ( strpos($value, '<xml>') !== false ) {
            unset($message[$key]);
        }
    }

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => array('comment_type' => 'contact_form_wordpress_ninja_froms'),
            'sender_info'   => array('sender_emails_array' => $sender_emails_array),
            'js_on'           => $checkjs,
            'event_token'     => Cookie::get('ct_bot_detector_event_token'),
        )
    );

    Cookie::$force_alt_cookies_global = false;

    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];

        // Change mail notification if license is out of date
        if ( $apbct->data['moderate'] == 0 &&
            ($ct_result->fast_submit == 1 || $ct_result->blacklisted == 1 || $ct_result->js_disabled == 1)
        ) {
            $apbct->sender_email = $sender_email;
            $apbct->sender_ip    = Helper::ipGet('real');
            add_filter('ninja_forms_action_email_message', 'apbct_form__ninjaForms__changeMailNotification', 1, 3);
        }

        if ( $ct_result->allow == 0 ) {
            // We have to use GLOBAL variable to transfer the comment to apbct_form__ninjaForms__changeResponse() function :(
            $apbct->response = $ct_result->comment;
            add_action('ninja_forms_before_response', 'apbct_form__ninjaForms__changeResponse', 10, 1);
            add_action(
                'ninja_forms_action_email_send',
                'apbct_form__ninjaForms__stopEmail',
                1,
                5
            ); // Prevent mail notification
            add_action(
                'ninja_forms_save_submission',
                'apbct_form__ninjaForms__preventSubmission',
                1,
                2
            ); // Prevent mail notification
        }
    }
}

/**
 * Old way to collecting NF fields data.
 *
 * @return GetFieldsAnyDTO
 */
function apbct_form__ninjaForms__collect_fields_old()
{
    /**
     * Filter for POST
     */
    $input_array = apply_filters('apbct__filter_post', $_POST);

    // Choosing between sanitized GET and POST
    $input_data = Get::get('ninja_forms_ajax_submit') || Get::get('nf_ajax_submit')
        ? array_map(function ($value) {
            return is_string($value) ? htmlspecialchars($value) : $value;
        }, $_GET)
        : $input_array;

    // Return the collected fields data
    return ct_gfa_dto($input_data);
}

/**
 * New way to collecting NF fields data - try to get username and email.
 *
 * @return GetFieldsAnyDTO
 * @throws Exception
 * @psalm-suppress UndefinedClass
 */
function apbct_form__ninjaForms__collect_fields_new()
{
    $form_data = json_decode(TT::toString(Post::get('formData')), true);
    if ( ! $form_data ) {
        $form_data = json_decode(stripslashes(TT::toString(Post::get('formData'))), true);
    }
    if ( ! isset($form_data['fields']) ) {
        throw new Exception('No form data is provided');
    }
    if ( ! function_exists('Ninja_Forms') ) {
        throw new Exception('No `Ninja_Forms` class exists');
    }
    $nf_form_info = Ninja_Forms()->form();
    if ( ! ($nf_form_info instanceof NF_Abstracts_ModelFactory) ) {
        throw new Exception('Getting NF form failed');
    }
    $nf_form_fields_info = $nf_form_info->get_fields();
    if ( ! is_array($nf_form_fields_info) && count($nf_form_fields_info) === 0 ) {
        throw new Exception('No fields are provided');
    }
    $nf_form_fields_info_array = [];
    foreach ($nf_form_fields_info as $field) {
        if ( $field instanceof NF_Database_Models_Field) {
            $nf_form_fields_info_array[$field->get_id()] = [
                'field_key' => TT::toString($field->get_setting('key')),
                'field_type' => TT::toString($field->get_setting('type')),
                'field_label' => TT::toString($field->get_setting('label')),
            ];
        }
    }

    $nf_form_fields = $form_data['fields'];
    $nickname = '';
    $nf_prior_email = '';
    $nf_emails_array = array();
    $fields = [];
    foreach ($nf_form_fields as $field) {
        if ( isset($nf_form_fields_info_array[$field['id']]) ) {
            $field_info = $nf_form_fields_info_array[$field['id']];
            if ( isset($field_info['field_key'], $field_info['field_type']) ) {
                $field_key = TT::toString($field_info['field_key']);
                $field_type = TT::toString($field_info['field_type']);
                $fields['nf-field-' . $field['id'] . '-' . $field_type] = $field['value'];
                if ( stripos($field_key, 'name') !== false && stripos($field_type, 'name') !== false ) {
                    $nickname .= ' ' . $field['value'];
                }
                if (
                    (stripos($field_key, 'email') !== false && $field_type === 'email') ||
                    (function_exists('is_email') && is_string($field['value']) && is_email($field['value']))
                ) {
                    /**
                     * On the plugin side we can not decide which of presented emails have to be used for check as sender_email,
                     * so we do collect any of them and provide to GFA as $emails_array param.
                     */
                    if (empty($nf_prior_email)) {
                        $nf_prior_email = $field['value'];
                    } else {
                        $nf_emails_array[] = $field['value'];
                    }
                }
            }
        }
    }

    return ct_gfa_dto($fields, $nf_prior_email, $nickname, $nf_emails_array);
}

/**
 * Inserts anti-spam hidden to ninja forms
 *
 * @return void
 * @global State $apbct
 * @psalm-suppress UnusedParam
 */
function apbct_form__ninjaForms__addField($form_id)
{
    global $apbct;

    static $second_execute = false;

    if ( $apbct->settings['forms__contact_forms_test'] == 1 && !is_user_logged_in() ) {
        if ( $apbct->settings['trusted_and_affiliate__under_forms'] === '1' && $second_execute) {
            echo Escape::escKsesPreset(
                apbct_generate_trusted_text_html('center'),
                'apbct_public__trusted_text'
            );
        }
    }

    $second_execute = true;
}

function apbct_form__ninjaForms__preventSubmission($_some, $_form_id)
{
    return false;
}


/**
 * @param $_some
 * @param $_action_settings
 * @param $_message
 * @param $_headers
 * @param $_attachments
 *
 * @throws Exception
 */
function apbct_form__ninjaForms__stopEmail($_some, $_action_settings, $_message, $_headers, $_attachments)
{
    global $apbct;
    throw new Exception($apbct->response);
}

/**
 * @param $data
 *
 * @psalm-suppress InvalidArrayOffset
 */
function apbct_form__ninjaForms__changeResponse($data)
{
    global $apbct;

    $nf_field_id = 1;

    // Show error message below field found by ID
    if (
        isset($data['fields_by_key']) &&
        array_key_exists('email', $data['fields_by_key']) &&
        !empty($data['fields_by_key']['email']['id'])
    ) {
        // Find ID of EMAIL field
        $nf_field_id = $data['fields_by_key']['email']['id'];
    } else {
        // Find ID of last field (usually SUBMIT)
        if (isset($data['fields'])) {
            $fields_keys = array_keys($data['fields']);
            $nf_field_id = array_pop($fields_keys);
        }
    }

    // Below is modified NJ logic
    $error = array(
        'fields' => array(
            $nf_field_id => $apbct->response,
        ),
    );

    $response = array('data' => $data, 'errors' => $error, 'debug' => '');

    $json_response = wp_json_encode($response, JSON_FORCE_OBJECT);
    if ($json_response === false) {
        $json_response = '{"error": "JSON encoding failed"}';
    }
    die($json_response);
}

/**
 * @psalm-suppress UnusedVariable
 */
function apbct_form__seedprod_coming_soon__testSpam()
{
    global $apbct, $ct_comment;

    if (
        $apbct->settings['forms__contact_forms_test'] == 0 ||
        ($apbct->settings['data__protect_logged_in'] != 1 && is_user_logged_in()) || // Skip processing for logged in users.
        apbct_exclusions_check__url()
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return;
    }

    /**
     * Filter for POST
     */
    $input_array = apply_filters('apbct__filter_post', $_REQUEST);

    $ct_temp_msg_data = ct_get_fields_any($input_array);

    $sender_email    = isset($ct_temp_msg_data['email']) ? $ct_temp_msg_data['email'] : '';
    $sender_emails_array = isset($ct_temp_msg_data['emails_array']) ? $ct_temp_msg_data['emails_array'] : '';
    $sender_nickname = isset($ct_temp_msg_data['nickname']) ? $ct_temp_msg_data['nickname'] : '';
    $subject         = isset($ct_temp_msg_data['subject']) ? $ct_temp_msg_data['subject'] : '';
    $message         = isset($ct_temp_msg_data['message']) ? $ct_temp_msg_data['message'] : array();
    if ( $subject != '' ) {
        $message = array_merge(array('subject' => $subject), $message);
    }

    $post_info = array();
    $post_info['comment_type'] = 'contact_form_wordpress_seedprod_coming_soon';

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => $post_info,
            'sender_info'   => array('sender_emails_array' => $sender_emails_array),
        )
    );

    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];
        if ( $ct_result->allow == 0 ) {
            $ct_comment = $ct_result->comment;

            $response = array(
                'status' => 200,
                'html'   =>
                    "<h1>"
                    . __('Spam protection by CleanTalk', 'cleantalk-spam-protect')
                    . "</h1><h2>" . $ct_result->comment . "</h2>"
            );

            echo sanitize_text_field(TT::toString(Get::get('callback'))) . '(' . json_encode($response) . ')';
            exit();
        }
    }
}

/**
 * Changes email notification for success subscription for Ninja Forms
 *
 * @param string $message Body of email notification
 *
 * @return string Body for email notification
 */
function apbct_form__ninjaForms__changeMailNotification($message, $_data, $action_settings)
{
    global $apbct;

    if ( $action_settings['to'] !== $apbct->sender_email ) {
        $message .= wpautop(
            PHP_EOL . '---'
            . PHP_EOL
            . __('CleanTalk Anti-Spam: This message could be spam.', 'cleantalk-spam-protect')
            . PHP_EOL . __('CleanTalk\'s Anti-Spam database:', 'cleantalk-spam-protect')
            . PHP_EOL . 'IP: ' . $apbct->sender_ip
            . PHP_EOL . 'Email: ' . $apbct->sender_email
            . PHP_EOL .
            __('If you want to be sure activate protection in your Anti-Spam Dashboard: ', 'clentalk') .
            //HANDLE LINK
            'https://cleantalk.org/my/?cp_mode=antispam&utm_source=newsletter&utm_medium=email&utm_campaign=ninjaform_activate_antispam' . $apbct->user_token
        );
    }

    return $message;
}

/**
 *  QuForms check spam
 *    works with single-paged forms
 *    and with multi-paged forms - check only last step of the forms
 *
 * @param $result
 * @param $form
 *
 * @return mixed
 */
function ct_quform_post_validate($result, $form)
{
    if ( $form->hasPages() ) {
        $comment_type = 'contact_form_wordpress_quforms_multipage';
    } else {
        $comment_type = 'contact_form_wordpress_quforms_singlepage';
    }

    /**
     * Filter for POST
     */
    $input_array = apply_filters('apbct__filter_post', $form->getValues());

    $ct_temp_msg_data = ct_get_fields_any($input_array);
    $sender_email = isset($ct_temp_msg_data['email']) ? $ct_temp_msg_data['email'] : '';
    $sender_emails_array = isset($ct_temp_msg_data['emails_array']) ? $ct_temp_msg_data['emails_array'] : '';

    $checkjs          = apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true);
    $base_call_result = apbct_base_call(
        array(
            'message'      => $form->getValues(),
            'sender_email' => $sender_email,
            'post_info'    => array('comment_type' => $comment_type),
            'sender_info'     => array('sender_emails_array' => $sender_emails_array),
            'js_on'        => $checkjs,
        )
    );

    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];
        if ( $ct_result->allow == 0 ) {
            die(
                json_encode(
                    array('type' => 'error', 'apbct' => array('blocked' => true, 'comment' => $ct_result->comment)),
                    JSON_HEX_QUOT | JSON_HEX_TAG
                )
            );
        }
    }

    return $result;
}

/**
 * Inserts anti-spam hidden to Fast Secure contact form
 */
function ct_si_contact_display_after_fields($string = '', $_style = '', $_form_errors = array(), $_form_id_num = 0)
{
    global $apbct;
    $string .= ct_add_hidden_fields('ct_checkjs', true);
    if ( $apbct->settings['trusted_and_affiliate__under_forms'] === '1' ) {
        $string .= Escape::escKsesPreset(
            apbct_generate_trusted_text_html('label_left'),
            'apbct_public__trusted_text'
        );
    }
    return $string;
}

/**
 * Test for Fast Secure contact form
 * @psalm-suppress UnusedVariable
 */
function ct_si_contact_form_validate($form_errors = array(), $_form_id_num = 0)
{
    global $apbct, $cleantalk_executed, $ct_comment;
    if ( ! empty($form_errors) ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $form_errors;
    }

    if ( $apbct->settings['forms__contact_forms_test'] == 0 ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $form_errors;
    }

    // Skip processing because data already processed.
    if ( $cleantalk_executed ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $form_errors;
    }

    /**
     * Filter for POST
     */
    $input_array = apply_filters('apbct__filter_post', $_POST);

    //getting info from custom fields
    $ct_temp_msg_data = ct_get_fields_any($input_array);

    $sender_email    = isset($ct_temp_msg_data['email']) ? $ct_temp_msg_data['email'] : '';
    $sender_emails_array = isset($ct_temp_msg_data['emails_array']) ? $ct_temp_msg_data['emails_array'] : '';
    $sender_nickname = isset($ct_temp_msg_data['nickname']) ? $ct_temp_msg_data['nickname'] : '';
    $subject         = isset($ct_temp_msg_data['subject']) ? $ct_temp_msg_data['subject'] : '';
    $message         = isset($ct_temp_msg_data['message']) ? $ct_temp_msg_data['message'] : array();
    if ( $subject !== '' ) {
        $message['subject'] = $subject;
    }

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => array('comment_type' => 'contact_form_wordpress_fscf'),
            'sender_info'     => array('sender_emails_array' => $sender_emails_array),
            'js_on'           => apbct_js_test(Sanitize::cleanTextField(Post::get('ct_checkjs'))),
        )
    );

    if ( isset($base_call_result['ct_result']) ) {
        $ct_result = $base_call_result['ct_result'];
        $cleantalk_executed = true;

        if ( $ct_result->allow == 0 ) {
            $ct_comment = $ct_result->comment;
            ct_die(null, null);
            exit;
        }
    }

    return $form_errors;
}

/**
 * Notice for commentators which comment has automatically approved by plugin
 *
 * @param string $hook URL of hooked page
 */
function ct_comment_text($comment_text)
{
    global $comment, $ct_approved_request_id_label;

    if ( isset($_COOKIE[$ct_approved_request_id_label]) && isset($comment->comment_ID) ) {
        $ct_hash = get_comment_meta($comment->comment_ID, 'ct_hash', true);

        if ( $ct_hash !== '' && $_COOKIE[$ct_approved_request_id_label] == $ct_hash ) {
            $comment_text .=
                '<br /><br /> <em class="comment-awaiting-moderation">'
                . __(
                    'Comment approved. Anti-spam by CleanTalk.',
                    'cleantalk-spam-protect'
                )
                . '</em>';
        }
    }

    return $comment_text;
}


/**
 * Checks WordPress Landing Pages raw $_POST values
 */
function ct_check_wplp()
{
    global $ct_wplp_result_label, $apbct;

    if ( ! isset($_COOKIE[$ct_wplp_result_label]) ) {
        // First AJAX submit of WPLP form
        if ( $apbct->settings['forms__contact_forms_test'] == 0 ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

            return;
        }

        $sender_email = '';
        foreach ( $_POST as $v ) {
            $sanitized_value = filter_var($v, FILTER_SANITIZE_EMAIL);
            if ( filter_var($sanitized_value, FILTER_VALIDATE_EMAIL) ) {
                $sender_email = $sanitized_value;
                break;
            }
        }

        $message = '';
        if ( array_key_exists('form_input_values', $_POST) && is_string($_POST['form_input_values']) ) {
            $form_input_values = json_decode(stripslashes($_POST['form_input_values']), true);
            if ( is_array($form_input_values) && array_key_exists('null', $form_input_values) ) {
                $message = Sanitize::cleanTextareaField($form_input_values['null']);
            }
        } elseif ( Post::get('null') ) {
            $message = Sanitize::cleanTextareaField(Post::get('null'));
        }

        $base_call_result = apbct_base_call(
            array(
                'message'      => $message,
                'sender_email' => $sender_email,
                'post_info'    => array('comment_type' => 'contact_form_wordpress_wplp'),
            )
        );

        $cleantalk_comment = 'OK';
        if ( isset($base_call_result['ct_result']) ) {
            $ct_result = $base_call_result['ct_result'];
            if ( $ct_result->allow == 0 ) {
                $cleantalk_comment = $ct_result->comment;
            }
        }
        Cookie::set($ct_wplp_result_label, $cleantalk_comment, strtotime("+5 seconds"), '/');
    } else {
        // Next POST/AJAX submit(s) of same WPLP form
        $cleantalk_comment = Sanitize::cleanTextField(Cookie::get($ct_wplp_result_label));
    }
    if ( $cleantalk_comment !== 'OK' ) {
        ct_die_extended($cleantalk_comment);
    }
}

/**
 * Places a hiding field to Gravity forms.
 * @return string
 */
function apbct_form__gravityForms__addField($form_string, $form)
{
    global $apbct;
    $ct_hidden_field = 'ct_checkjs';

    // Do not add a hidden field twice.
    if ( preg_match("/$ct_hidden_field/", $form_string) ) {
        return $form_string;
    }

    $search = "</form>";

    // Adding JS code
    $js_code  = ct_add_hidden_fields($ct_hidden_field, true, false);
    $honeypot = Honeypot::generateHoneypotField('gravity_form');
    $form_string = str_replace($search, TT::toString($js_code) . $honeypot . $search, $form_string);

    // Adding field for multipage form. Look for cleantalk.php -> apbct_cookie();
    $append_string = isset($form['lastPageButton']) ? "<input type='hidden' name='ct_multipage_form' value='yes'>" : '';
    if ( $apbct->settings['trusted_and_affiliate__under_forms'] === '1' ) {
        $append_string .= Escape::escKsesPreset(
            apbct_generate_trusted_text_html('label_left'),
            'apbct_public__trusted_text'
        );
    }
    $form_string   = str_replace($search, $append_string . $search, $form_string);

    return $form_string;
}

/**
 * Gravity forms anti-spam test.
 * @return boolean
 * @psalm-suppress UnusedVariable
 * @psalm-suppress ArgumentTypeCoercion
 */
function apbct_form__gravityForms__testSpam($is_spam, $form, $entry)
{
    global $apbct, $cleantalk_executed, $ct_gform_is_spam, $ct_gform_response;

    if (
        $is_spam ||
        $apbct->settings['forms__contact_forms_test'] == 0 ||
        ($apbct->settings['data__protect_logged_in'] != 1 && apbct_is_user_logged_in()) || // Skip processing for logged in users.
        apbct_exclusions_check__url() ||
        $cleantalk_executed // Return unchanged result if the submission was already tested.
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $is_spam;
    }

    $form_fields_for_ct       = array();
    $form_fields              = (isset($form['fields'])) ? $form['fields'] : false;
    $form_fields_intermediate = array();
    $email                    = '';
    $nickname                 = array();

    if ( $form_fields ) {
        foreach ( $form_fields as $field ) {
            $field_id         = $field['id'];
            $field_visibility = $field['visibility'];
            $field_type       = $field['type'];
            $field_inputs     = $field['inputs'];

            if ( $field_inputs ) {
                foreach ( $field_inputs as $input ) {
                    $input_id = $input['id'];

                    if ( isset($entry[$input_id]) && $entry[$input_id] ) {
                        $form_fields_intermediate[]               = array(
                            'f_name'       => 'input_' . $input_id,
                            'f_visibility' => $field_visibility,
                            'f_type'       => $field_type,
                            'f_data'       => $entry[$input_id]
                        );
                        $form_fields_for_ct['input_' . $input_id] = $entry[$input_id];
                    }
                }
            } else {
                if ( isset($entry[$field_id]) && $entry[$field_id] ) {
                    $form_fields_intermediate[]               = array(
                        'f_name'       => 'input_' . $field_id,
                        'f_visibility' => $field_visibility,
                        'f_type'       => $field_type,
                        'f_data'       => $entry[$field_id]
                    );
                    $form_fields_for_ct['input_' . $field_id] = $entry[$field_id];
                }
            }
        }
    }

    # Search nickname and email
    if ( $form_fields_intermediate ) {
        $form_fields_intermediate_keys = array();
        foreach ($form_fields_intermediate as $key => $field) {
            $form_fields_intermediate_keys[$field['f_name']] = $key;
        }

        /**
         * Filter for POST
         */
        $input_data = apply_filters('apbct__filter_post', $form_fields_intermediate_keys);

        foreach ($form_fields_intermediate as $key => $field) {
            if (!in_array($field['f_name'], array_keys($input_data))) {
                unset($form_fields_intermediate[$key]);
            }
        }

        foreach ( $form_fields_intermediate as $field ) {
            if ( $field['f_type'] === 'email' && $field['f_visibility'] === 'visible') {
                $email = $field['f_data'];
            }

            if ( $field['f_type'] === 'name' ) {
                $nickname[] = $field['f_data'];
            }
        }
    }

    if ( ! $form_fields_for_ct ) {
        foreach ( $entry as $key => $value ) {
            if ( is_numeric($key) ) {
                $form_fields_for_ct['input_' . $key] = $value;
            }
        }
        unset($key, $value);
    }

    /**
     * Filter for POST
     */
    $input_data = apply_filters('apbct__filter_post', $form_fields_for_ct);

    $ct_temp_msg_data = ct_get_fields_any($input_data, $email, array_shift($nickname));

    $sender_email    = isset($ct_temp_msg_data['email']) ? $ct_temp_msg_data['email'] : '';
    $sender_emails_array = isset($ct_temp_msg_data['emails_array']) ? $ct_temp_msg_data['emails_array'] : '';
    $sender_nickname = isset($ct_temp_msg_data['nickname']) ? $ct_temp_msg_data['nickname'] : '';
    $subject         = isset($ct_temp_msg_data['subject']) ? $ct_temp_msg_data['subject'] : '';
    $message         = isset($ct_temp_msg_data['message']) ? $ct_temp_msg_data['message'] : array();

    if ( $subject !== '' ) {
        $message['subject'] = $subject;
    }

    $checkjs = apbct_js_test(Sanitize::cleanTextField(Post::get('ct_checkjs'))) ?: apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true);

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => array('comment_type' => 'contact_form_wordpress_gravity_forms'),
            'sender_info'     => array('sender_emails_array' => $sender_emails_array),
            'js_on'           => $checkjs,
        )
    );

    if ( isset($base_call_result['ct_result'])) {
        $ct_result = $base_call_result['ct_result'];
        if ( $ct_result->allow == 0 ) {
            $is_spam           = true;
            $ct_gform_is_spam  = true;
            $ct_gform_response = $ct_result->comment;
            if ( isset($apbct->settings['forms__gravityforms_save_spam']) && $apbct->settings['forms__gravityforms_save_spam'] == 1 ) {
                add_action('gform_entry_created', 'apbct_form__gravityForms__add_entry_note');
            } elseif ( class_exists('GFFormsModel') && method_exists('GFFormsModel', 'delete_lead') ) {
                /** @psalm-suppress UndefinedClass */
                GFFormsModel::delete_lead($entry['id']);
            }
        }
    }

    return $is_spam;
}

function apbct_form__gravityForms__showResponse($confirmation, $form, $_entry, $_ajax)
{
    global $ct_gform_is_spam, $ct_gform_response;

    if ( ! empty($ct_gform_is_spam) ) {
        $confirmation = '<a id="gf_' . $form['id'] . '" class="gform_anchor" ></a><div id="gform_confirmation_wrapper_' . $form['id'] . '" class="gform_confirmation_wrapper "><div id="gform_confirmation_message_' . $form['id'] . '" class="gform_confirmation_message_' . $form['id'] . ' gform_confirmation_message"><div class="gform_cleantalk_error" style="color: red">' . $ct_gform_response . '</div></div></div>';
    }

    return $confirmation;
}

/**
 * Adds a note to the entry once the spam status is set (GF 2.4.18+).
 *
 * @param array $_entry The entry that was created.
 *
 * @psalm-suppress UndefinedClass
 * @psalm-suppress UndefinedFunction
 */
function apbct_form__gravityForms__add_entry_note($_entry)
{
    if ( (function_exists('rgar') && rgar($_entry, 'status') !== 'spam') ||
        ! ( class_exists('GFAPI') && is_callable(array('GFAPI', 'add_note')) )
    ) {
        return;
    }

    GFAPI::add_note(
        $_entry['id'],
        0,
        'CleanTalk',
        __('This entry has been marked as spam.', 'cleantalk-spam-protect'),
        'cleantalk',
        'success'
    );
}

/**
 * Test S2member registration
 * @return bool|null with errors
 */
function ct_s2member_registration_test($post_key)
{
    global $apbct;

    if ( $apbct->settings['forms__registrations_test'] == 0 ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);
        return null;
    }

    $post_key_value = Post::get($post_key);

    if ( is_array($post_key_value) && isset($post_key_value['email'], $post_key_value['username']) ) {
        //old way
        $sender_email    = Sanitize::cleanEmail($post_key_value['email']);
        $sender_nickname = Sanitize::cleanUser($post_key_value['username']);
    } else {
        //new way
        $sender_email = Post::get('signup_email') ? Sanitize::cleanEmail(Post::get('signup_email')) : null;
        $sender_nickname = Post::get('signup_username') ? Sanitize::cleanUser(Post::get('signup_username')) : null;
    }

    if ( empty($sender_email) ) {
        return null;
    }

    //Making a call
    $base_call_result = apbct_base_call(
        array(
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
        ),
        true
    );

    if (isset($base_call_result['ct_result'])) {
        $ct_result = $base_call_result['ct_result'];

        if ( $ct_result->allow == 0 ) {
            ct_die_extended($ct_result->comment);
        }
    }

    return true;
}

/**
 * @return false
 * @psalm-suppress UnusedVariable
 */
function apbct_form__the7_contact_form()
{
    global $cleantalk_executed;

    if ( check_ajax_referer('dt_contact_form', 'nonce', false) && ! empty($_POST) ) {
        $post_info = array();
        $post_info['comment_type'] = 'contact_form_wordpress_the7_theme_contact_form';

        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $_POST);

        $ct_temp_msg_data = ct_get_fields_any($input_array);

        $sender_email    = isset($ct_temp_msg_data['email']) ? $ct_temp_msg_data['email'] : '';
        $sender_emails_array = isset($ct_temp_msg_data['emails_array']) ? $ct_temp_msg_data['emails_array'] : '';
        $sender_nickname = isset($ct_temp_msg_data['nickname']) ? $ct_temp_msg_data['nickname'] : '';
        $subject         = isset($ct_temp_msg_data['subject']) ? $ct_temp_msg_data['subject'] : '';
        $contact_form    = isset($ct_temp_msg_data['contact']) && !empty($ct_temp_msg_data['contact']) ? $ct_temp_msg_data['contact'] : '';
        $message         = isset($ct_temp_msg_data['message']) ? $ct_temp_msg_data['message'] : array();
        if ( $subject !== '' ) {
            $message = array_merge(array('subject' => $subject), $message);
        }

        // Skip submission if no data found
        if ( ! $contact_form ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

            return false;
        }
        $cleantalk_executed = true;

        $base_call_result = apbct_base_call(
            array(
                'message'         => $message,
                'sender_email'    => $sender_email,
                'sender_nickname' => $sender_nickname,
                'post_info'       => $post_info,
                'sender_info'     => array('sender_emails_array' => $sender_emails_array),
            )
        );

        if (isset($base_call_result['ct_result'])) {
            $ct_result = $base_call_result['ct_result'];
            if ( $ct_result->allow == 0 ) {
                $response = array(
                    'success' => false,
                    'errors'  => $ct_result->comment,
                    'nonce'   => wp_create_nonce('dt_contact_form')
                );

                wp_send_json($response);

                // IMPORTANT: don't forget to "exit" @todo AG: Why? Exit does not terminate connection, but I can't see how it is applicable
                exit;
            }
        }
    }

    return false;
}

/**
 * Places a hiding field to Gravity forms.
 * @return string
 */
function apbct_form__elementor_pro__addField($content)
{
    global $apbct;

    $search = '</form>';
    if (
        is_string($content) &&
        !preg_match('/search/', $content) &&
        !preg_match('/method.+get./', $content)
    ) {
        $replace = Honeypot::generateHoneypotField('elementor_form') . $search;
        $content = str_replace($search, $replace, $content);
    }

    if ( $apbct->settings['trusted_and_affiliate__under_forms'] === '1' && strpos($content, $search) !== false ) {
        $content .= Escape::escKsesPreset(
            apbct_generate_trusted_text_html('center'),
            'apbct_public__trusted_text'
        );
    }

    return $content;
}

// INEVIO theme integration
function apbct_form__inevio__testSpam()
{
    global $apbct;

    $theme = wp_get_theme();
    if (
        stripos(TT::toString($theme->get('Name')), 'INEVIO') === false ||
        $apbct->settings['forms__contact_forms_test'] == 0 ||
        ($apbct->settings['data__protect_logged_in'] != 1 && is_user_logged_in()) || // Skip processing for logged in users.
        apbct_exclusions_check__url()
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return false;
    }
    $form_data = array();
    parse_str(TT::toString(Post::get('data')), $form_data);

    $name    = isset($form_data['name']) ? $form_data['name'] : '';
    $email   = isset($form_data['email']) ? $form_data['email'] : '';
    $message = isset($form_data['message']) ? $form_data['message'] : '';

    $post_info = array();
    $post_info['comment_type'] = 'contact_form_wordpress_inevio_theme';

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $email,
            'sender_nickname' => $name,
            'post_info'       => $post_info,
        )
    );

    if (isset($base_call_result['ct_result'])) {
        $ct_result = $base_call_result['ct_result'];
        if ( $ct_result->allow == 0 ) {
            die(
                json_encode(
                    array('apbct' => array('blocked' => true, 'comment' => $ct_result->comment)),
                    JSON_HEX_QUOT | JSON_HEX_TAG
                )
            );
        }
    }

    return true;
}


/**
 *  Filters the 'status' array before register the user
 *  using only by WILCITY theme
 *
 * @param $success    array            array( 'status' => 'success' )
 * @param $data       array            ['username'] ['password'] ['email']
 *
 * @return            array            array( 'status' => 'error' ) or array( 'status' => 'success' ) by default
 */
function apbct_wilcity_reg_validation($success, $data)
{
    $check = ct_test_registration($data['username'], $data['email'], '');
    if ( isset($check['allow']) && $check['allow'] == 0 ) {
        return array('status' => 'error');
    }

    return $success;
}

/**
 * Enfold Theme contact form
 *
 * @param $send
 * @param $new_post
 * @param $_form_params
 * @param $obj
 *
 * @return mixed|null
 * @psalm-suppress UnusedVariable
 */
function apbct_form__enfold_contact_form__test_spam($send, $new_post, $_form_params, $obj)
{
    global $cleantalk_executed;

    $url_decoded_data = array();
    foreach ( $new_post as $key => $value ) {
        $url_decoded_data[$key] = urldecode($value);
    }

    $data = ct_get_fields_any($url_decoded_data);

    $base_call_result = apbct_base_call(
        array(
            'message'         => ! empty($data['message']) ? json_encode($data['message']) : '',
            'sender_email'    => ! empty($data['email']) ? $data['email'] : '',
            'sender_nickname' => ! empty($data['nickname']) ? $data['nickname'] : '',
            'post_info'       => array(
                'comment_type' => 'contact_form_wordpress_enfold'
            ),
            'sender_info'     => array(
                'sender_emails_array' => isset($data['emails_array']) ? $data['emails_array'] : '',
            ),
        )
    );

    if (isset($base_call_result['ct_result'])) {
        $ct_result = $base_call_result['ct_result'];
        $cleantalk_executed = true;
        if ( $ct_result->allow == 0 ) {
            $obj->submit_error = $ct_result->comment;

            return null;
        }
    }

    return $send;
}

/**
 * Profile Builder integration
 *
 * @param $errors
 * @param $_fields
 * @param $global_request
 *
 * @return mixed
 * @psalm-suppress UnusedVariable
 */
function apbct_form_profile_builder__check_register($errors, $_fields, $global_request)
{
    global $cleantalk_executed;

    if ( isset($global_request['action']) && $global_request['action'] === 'register' ) {
        $global_request_array = array();
        if (is_array($global_request)) {
            $global_request_array = $global_request;
        } elseif ($global_request instanceof ArrayAccess) {
            foreach ($global_request as $key => $value) {
                $global_request_array[$key] = $value;
            }
        }
        $data = ct_get_fields_any($global_request_array);

        $base_call_result = apbct_base_call(
            array(
                'message'         => ! empty($data['message']) ? json_encode($data['message']) : '',
                'sender_email'    => ! empty($data['email']) ? $data['email'] : '',
                'sender_nickname' => ! empty($data['nickname']) ? $data['nickname'] : '',
                'post_info'       => array(
                    'comment_type' => 'register_profile_builder'
                ),
                'sender_info'     => array(
                    'sender_emails_array' => isset($data['emails_array']) ? $data['emails_array'] : '',
                ),
            ),
            true
        );

        if (isset($base_call_result['ct_result'])) {
            $ct_result = $base_call_result['ct_result'];
            if ( $ct_result->allow == 0 ) {
                $errors['error']                         = $ct_result->comment;
                $GLOBALS['global_profile_builder_error'] = $ct_result->comment;

                add_filter('wppb_general_top_error_message', 'apbct_form_profile_builder__error_message', 1);
            }
        }

        $cleantalk_executed = true;
    }

    return $errors;
}

/**
 * Profile Builder Integration - add error message in response
 */
function apbct_form_profile_builder__error_message()
{
    $error = isset($GLOBALS['global_profile_builder_error']) ? $GLOBALS['global_profile_builder_error'] : '';
    return '<p id="wppb_form_general_message" class="wppb-error">' . $error . '</p>';
}

/**
 * WP Foro register system integration
 *
 * @param $user_fields
 *
 * @return array|mixed
 * @psalm-suppress UnusedVariable
 */
function wpforo_create_profile__check_register($user_fields)
{
    global $ct_signup_done;

    $ip    = Helper::ipGet('real', false);
    $check = ct_test_registration($user_fields['user_login'], $user_fields['user_email'], $ip);
    if ( isset($check['allow'], $check['comment']) && $check['allow'] == 0 ) {
        return array('error' => $check['comment']);
    }

    $ct_signup_done = true;

    return $user_fields;
}

/**
 * Function checks for signs in the post request to perform validation and returns true|false
 */
function apbct_custom_forms_trappings()
{
    global $apbct;

    // Registration form of Wishlist Members plugin
    if ( $apbct->settings['forms__registrations_test'] && Post::get('action') === 'wpm_register' ) {
        return true;
    }

    // Registration form of masteriyo registration
    if ( $apbct->settings['forms__registrations_test'] &&
         Post::get('masteriyo-registration') === 'yes' &&
         (
             apbct_is_plugin_active('learning-management-system/lms.php') ||
             apbct_is_plugin_active('learning-management-system-pro/lms.php')
         )
    ) {
        return true;
    }

    // Registration form of eMember plugin
    if (
        $apbct->settings['forms__registrations_test'] &&
        Request::get('emember-form-builder-submit') &&
        wp_verify_nonce(TT::toString(Request::get('_wpnonce')), 'emember-form-builder-nonce')
    ) {
        return true;
    }

    // Registration form of goodlayers-lms
    if (
        apbct_is_plugin_active('goodlayers-lms/goodlayers-lms.php') &&
        $apbct->settings['forms__registrations_test'] &&
        Post::get('action') === 'create-new-user'
    ) {
        return true;
    }

    return false;
}

/**
 * UsersWP plugin integration
 */
function apbct_form__uwp_validate($result, $_type, $data)
{
    if ( isset($data['username'], $data['email']) ) {
        $check = ct_test_registration($data['username'], $data['email'], Helper::ipGet());
        if ( isset($check['allow'], $check['comment']) && $check['allow'] == 0 ) {
            return new WP_Error('invalid_email', $check['comment']);
        }
    }

    return $result;
}

/**
 * WS-Forms integration
 * @psalm-suppress UnusedClosureParam
 */
add_filter('wsf_submit_field_validate', function ($error_validation_action_field, $field_id, $_field_value, $section_repeatable_index, $_post_mode, $_form_submit_class) {

    global $cleantalk_executed;

    if ( $cleantalk_executed || $_post_mode != 'submit' ) {
        return $error_validation_action_field;
    }

    /**
     * Filter for POST
     */
    $input_array = apply_filters('apbct__filter_post', $_POST);

    $long_email = '';
    foreach ($input_array as $value) {
        if (is_string($value) &&
            preg_match("/^\S+@\S+\.\S+$/", $value) &&
            strlen($value) > strlen($long_email)
        ) {
            $long_email = $value;
        }
    }

    $data = ct_gfa($input_array, $long_email);

    $sender_email        = isset($data['email']) ? $data['email'] : '';
    $sender_nickname     = isset($data['nickname']) ? $data['nickname'] : '';
    $message             = isset($data['message']) ? $data['message'] : array();

    $sender_info = [];
    $sender_info['sender_email'] = urlencode($sender_email);
    if ( ! empty($data['emails_array']) ) {
        $sender_info['sender_emails_array'] = $data['emails_array'];
    }

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => array( 'comment_type' => 'WS_forms' ),
            'sender_info'     => $sender_info,
        )
    );

    if (isset($base_call_result['ct_result'])) {
        if ( $base_call_result['ct_result']->allow == 0 ) {
            $error_validation_action_field[] = array(
                'action'                   => 'message',
                'message'                  => $base_call_result['ct_result']->comment
            );
        }
    }

    $cleantalk_executed = true;

    return $error_validation_action_field;
}, 10, 6);

/**
 * Happyforms integration
 *
 * @param $is_valid
 * @param $request
 * @param $form
 *
 * @return mixed
 * @psalm-suppress UnusedVariable
 */
function apbct_form_happyforms_test_spam($is_valid, $request, $_form)
{
    global $cleantalk_executed;

    if ( ! $cleantalk_executed && $is_valid ) {
        /**
         * Filter for request
         */
        if (isset($request['data'])) {
            apbct_form__get_no_cookie_data($request['data']);
            unset($request['data']);
        }

        $input_array = apply_filters('apbct__filter_post', $request);

        $data = ct_get_fields_any($input_array);

        $base_call_result = apbct_base_call(
            array(
                'message'         => ! empty($data['message']) ? json_encode($data['message']) : '',
                'sender_email'    => ! empty($data['email']) ? $data['email'] : '',
                'sender_nickname' => ! empty($data['nickname']) ? $data['nickname'] : '',
                'post_info'       => array(
                    'comment_type' => 'happyforms_contact_form'
                ),
                'sender_info'     => array(
                    'sender_emails_array' => isset($data['emails_array']) ? $data['emails_array'] : '',
                ),
            )
        );

        if (isset($base_call_result['ct_result'])) {
            $ct_result = $base_call_result['ct_result'];
            if ( $ct_result->allow == 0 ) {
                wp_send_json_error(array(
                    'html' => '<div class="happyforms-form happyforms-styles">
                                <h3 class="happyforms-form__title">Sample Form</h3>
                                <form action="" method="post" novalidate="true">
                                <div class="happyforms-flex"><div class="happyforms-message-notices">
                                <div class="happyforms-message-notice error">
                                <h2>' . $ct_result->comment . '</h2></div></div>
                                </form></div>'
                ));
            }
        }

        $cleantalk_executed = true;
    }

    return $is_valid;
}

/**
 * Advanced Classifieds & Directory Pro
 *
 * @param $response
 * @param $form_name
 *
 * @return mixed
 * @psalm-suppress UnusedVariable
 */
function apbct_advanced_classifieds_directory_pro__check_register($response, $_form_name)
{
    global $cleantalk_executed, $ct_comment;

    if (
        Post::get('username') &&
        Post::get('email')
    ) {
        $data = ct_gfa_dto(apply_filters('apbct__filter_post', $_POST), Sanitize::cleanEmail(Post::get('email')));
        $data = $data->getArray();

        $base_call_result = apbct_base_call(
            array(
                'message'         => ! empty($data['message']) ? json_encode($data['message']) : '',
                'sender_email'    => ! empty($data['email']) ? $data['email'] : '',
                'sender_nickname' => ! empty($data['nickname']) ? $data['nickname'] : '',
                'post_info'       => array(
                    'comment_type' => 'register_advanced_classifieds_directory_pro'
                ),
                'sender_info'     => array(
                    'sender_emails_array' => isset($data['emails_array']) ? $data['emails_array'] : '',
                ),
            ),
            true
        );

        if (isset($base_call_result['ct_result'])) {
            $ct_result = $base_call_result['ct_result'];
            if ( $ct_result->allow == 0 ) {
                $ct_comment = $ct_result->comment;
                ct_die(null, null);
            }
        }

        $cleantalk_executed = true;
    }

    return $response;
}

function ct_mc4wp_hook($errors)
{
    $result = apbct_is_ajax() ? ct_ajax_hook() : ct_contact_form_validate();

    // only return modified errors array when function returned a string value (the message key)
    if ( is_string($result) ) {
        $errors[] = $result;
    }

    return $errors;
}

/***************************************************
 * GiveWP Integration
 *
 * If javascript is disabled, the request
 * from the form will be processed here.
 * ************************************************/
function apbct_givewp_donate_request_test()
{
    global $cleantalk_executed, $ct_comment;

    /* Exclusions */
    if ($cleantalk_executed) {
        return;
    }

    $input_array = apply_filters('apbct__filter_post', $_POST);
    $params = ct_get_fields_any($input_array);

    $base_call_result = apbct_base_call(
        array(
            'sender_email'    => isset($params['email']) ? $params['email'] : '',
            'sender_nickname' => isset($params['nickname']) ? $params['nickname'] : [],
            'post_info'       => array('comment_type' => 'givewp_donate_form'),
            'sender_info'     => array(
                'sender_emails_array' => isset($params['emails_array']) ? $params['emails_array'] : '',
            ),
        )
    );

    if (isset($base_call_result['ct_result'])) {
        $ct_result = $base_call_result['ct_result'];
        if ((int)$ct_result->allow === 0) {
            $ct_comment = $ct_result->comment;
            ct_die(null, null);
        }
    }

    $cleantalk_executed = true;
}

/***************************************************
 * MemberPress Integration
 *
 * Another integration, because the hook
 * "mepr-validate-signup" does not work, and
 * the standard function "ct_contact_form_validate"
 * contains an exception.
 * ************************************************/
function apbct_memberpress_signup_request_test()
{
    global $cleantalk_executed, $ct_comment;

    /* Exclusions */
    if ($cleantalk_executed) {
        return;
    }

    $input_array = apply_filters('apbct__filter_post', $_POST);
    $params = ct_get_fields_any($input_array);

    $base_call_result = apbct_base_call(
        array(
            'sender_email'    => isset($params['email']) ? $params['email'] : '',
            'sender_nickname' => isset($params['nickname']) ? $params['nickname'] : '',
            'post_info'       => array('comment_type' => 'memberpress_signup_form'),
            'sender_info'     => array(
                'sender_emails_array' => isset($params['emails_array']) ? $params['emails_array'] : '',
            ),
        )
    );

    if (isset($base_call_result['ct_result'])) {
        $ct_result = $base_call_result['ct_result'];
        if ((int)$ct_result->allow === 0) {
            $ct_comment = $ct_result->comment;
            ct_die(null, null);
        }
    }

    $cleantalk_executed = true;
}

function apbct_leakyPaywall_request_test()
{
    global $cleantalk_executed, $ct_comment;

    if ($cleantalk_executed) {
        return;
    }

    $input_array = apply_filters('apbct__filter_post', $_POST);
    $params = ct_gfa_dto($input_array);

    $base_call_result = apbct_base_call(
        array(
            'sender_email'    => $params->email,
            'sender_nickname' => $params->nickname,
            'post_info'       => array('comment_type' => 'leakyPaywall_signup_form'),
            'sender_info'     => ['sender_emails_array' => $params->emails_array],
        ),
        true
    );

    if (isset($base_call_result['ct_result'])) {
        $ct_result = $base_call_result['ct_result'];
        if ((int)$ct_result->allow === 0) {
            $ct_comment = $ct_result->comment;
            ct_die(null, null);
        }
    }

    $cleantalk_executed = true;
}

function apbct_jetformbuilder_request_test()
{
    global $ct_comment;

    $input_array = apply_filters('apbct__filter_post', $_POST);
    $params = ct_gfa($input_array);

    $sender_info = [];
    if ( ! empty($params['emails_array']) ) {
        $sender_info['sender_emails_array'] = $params['emails_array'];
    }

    $message = isset($params['message']) ? $params['message'] : [];

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => isset($params['email']) ? $params['email'] : '',
            'sender_nickname' => isset($params['nickname']) ? $params['nickname'] : '',
            'post_info'       => array('comment_type' => 'jetformbuilder_signup_form'),
            'sender_info'     => $sender_info,
        )
    );

    if (isset($base_call_result['ct_result'])) {
        $ct_result = $base_call_result['ct_result'];
        if ((int)$ct_result->allow === 0) {
            if (Get::get('method') === 'ajax') {
                $msg = '<div class="jet-form-builder-message jet-form-builder-message--error">' . $ct_result->comment . '</div>';
                wp_send_json(
                    array(
                        'status' => 'failed',
                        'message' => $msg
                    )
                );
            } else {
                $ct_comment = $ct_result->comment;
                ct_die(null, null);
            }
        }
    }
}

function apbct_dhvcform_request_test()
{
    global $ct_comment;

    $input_array = apply_filters('apbct__filter_post', $_POST);
    $params = ct_gfa($input_array);

    $sender_info = [];
    if ( ! empty($params['emails_array']) ) {
        $sender_info['sender_emails_array'] = $params['emails_array'];
    }

    $base_call_result = apbct_base_call(
        array(
            'sender_email'    => isset($params['email']) ? $params['email'] : '',
            'sender_nickname' => isset($params['nickname']) ? $params['nickname'] : '',
            'post_info'       => array('comment_type' => 'dhvcform_form'),
            'sender_info'     => $sender_info,
        )
    );

    if (isset($base_call_result['ct_result'])) {
        $ct_result = $base_call_result['ct_result'];
        if ((int)$ct_result->allow === 0) {
            $ct_comment = $ct_result->comment;
            ct_die(null, null);
        }
    }
}

/**
 * Test SeedConfirmPro form for spam
 * @return void
 */
function apbct_seedConfirmPro_request_test()
{
    global $ct_comment;

    $input_array = apply_filters('apbct__filter_post', $_POST);
    $params = ct_gfa($input_array);

    $sender_info = [];
    if ( ! empty($params['emails_array']) ) {
        $sender_info['sender_emails_array'] = $params['emails_array'];
    }

    $base_call_result = apbct_base_call(
        array(
            'sender_email'    => isset($params['email']) ? $params['email'] : '',
            'sender_nickname' => isset($params['nickname']) ? $params['nickname'] : '',
            'post_info'       => array('comment_type' => 'seedConfirmPro_form'),
            'sender_info'     => $sender_info,
        )
    );

    if (isset($base_call_result['ct_result'])) {
        $ct_result = $base_call_result['ct_result'];
        if ((int)$ct_result->allow === 0) {
            $ct_comment = $ct_result->comment;
            ct_die(null, null);
        }
    }
}

/**
 * @param $validation_error WP_Error
 * @param $username string
 * @param $_password string
 * @param $email string
 * @return WP_Error
 */
function apbct_wp_delicious($validation_error, $username, $_password, $email)
{
    $base_call_result = apbct_base_call(
        array(
            'sender_email'    => sanitize_email($email),
            'sender_nickname' => sanitize_user($username),
            'post_info'       => array('comment_type' => 'contact_form_wordpress_wp_delicious'),
        ),
        true
    );

    if (isset($base_call_result['ct_result'])) {
        $ct_result = $base_call_result['ct_result'];
        if ((int)$ct_result->allow === 0) {
            $validation_error->add(403, $ct_result->comment);
        }
    }

    return $validation_error;
}
