<?php

use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\Variables\Get;
use Cleantalk\Variables\Post;
use Cleantalk\Variables\Request;
use Cleantalk\Variables\Server;

// MailChimp Premium for Wordpress
function ct_add_mc4wp_error_message($messages)
{
    $messages['ct_mc4wp_response'] = array(
        'type' => 'error',
        'text' => 'Your message looks like spam.'
    );

    return $messages;
}

add_filter('mc4wp_form_messages', 'ct_add_mc4wp_error_message');

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
    $form_id = $_POST['form_id'];
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

    $sender_email    = $ct_temp_msg_data['email'] ?: '';
    $sender_nickname = $ct_temp_msg_data['nickname'] ?: '';
    $subject         = $ct_temp_msg_data['subject'] ?: '';
    $message         = $ct_temp_msg_data['message'] ?: array();

    if ( $subject !== '' ) {
        $message['subject'] = $subject;
    }

    $post_info['comment_type'] = 'feedback_custom_contact_forms';
    $post_info['post_url']     = apbct_get_server_variable('HTTP_REFERER');

    $checkjs = apbct_js_test('ct_checkjs', $_COOKIE, true) ?: apbct_js_test('ct_checkjs', $_POST);

    //Making a call
    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => $post_info,
            'js_on'           => $checkjs,
            'sender_info'     => array('sender_url' => null),
        )
    );

    $ct_result = $base_call_result['ct_result'];

    return $ct_result->allow == 0 ? $ct_result->comment : true;
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
    $hostname = gethostbyaddr(apbct_get_server_variable('REMOTE_ADDR'));
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

    $post_info['comment_type'] = 'feedback';
    $post_info['post_url']     = apbct_get_server_variable('HTTP_REFERER');

    $checkjs = apbct_js_test('ct_checkjs', $_COOKIE, true) ?: apbct_js_test('ct_checkjs', $_POST);

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
    } else {
        return $args;
    }
}

function apbct_integration__buddyPres__getTemplateName(
    $located,
    $_template_name,
    $_template_names,
    $_template_locations,
    $_load,
    $_require_once
) {
    global $apbct;
    preg_match("/\/([a-z-_]+)\/buddypress-functions\.php$/", $located, $matches);
    $apbct->buddy_press_tmpl = isset($matches[1]) ? $matches[1] : 'unknown';
}

/**
 * Test BuddyPress activity for spam (post update only)
 *
 * @param bool $is_spam
 * @param object $activity_obj Activity object (\plugins\buddypress\bp-activity\classes\class-bp-activity-activity.php)
 *
 * @return boolean Spam flag
 * @psalm-suppress UnusedVariable
 * @global State $apbct
 */
function apbct_integration__buddyPres__activityWall($is_spam, $activity_obj = null)
{
    global $apbct;

    $allowed_post_actions = array('post_update', 'new_activity_comment');

    if ( ! in_array(Post::get('action'), $allowed_post_actions) ||
         $activity_obj === null ||
         ! Post::get('action') ||
         $activity_obj->privacy == 'media' ||
         apbct_exclusions_check()
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return false;
    }

    $curr_user = get_user_by('id', $activity_obj->user_id);

    //Making a call
    $base_call_result = apbct_base_call(
        array(
            'message'         => is_string($activity_obj->content) ? $activity_obj->content : '',
            'sender_email'    => $curr_user->data->user_email,
            'sender_nickname' => $curr_user->data->user_login,
            'post_info'       => array(
                'post_url'     => apbct_get_server_variable('HTTP_REFERER'),
                'comment_type' => 'buddypress_activitywall',
            ),
            'js_on'           => apbct_js_test('ct_checkjs', $_COOKIE, true),
            'sender_info'     => array('sender_url' => null),
        )
    );

    $ct_result = $base_call_result['ct_result'];

    if ( $ct_result->allow == 0 ) {
        add_action('bp_activity_after_save', 'apbct_integration__buddyPres__activityWall_showResponse', 1, 1);
        $apbct->spam_notification = $ct_result->comment;

        return true;
    } else {
        return $is_spam;
    }
}

/**
 * Outputs message to AJAX frontend handler
 *
 * @param object $activity_obj Activity object (\plugins\buddypress\bp-activity\classes\class-bp-activity-activity.php)
 *
 * @global State $apbct
 */
function apbct_integration__buddyPres__activityWall_showResponse($_activity_obj)
{
    global $apbct;

    // Legacy template
    if ( $apbct->buddy_press_tmpl === 'bp-legacy' ) {
        die('<div id="message" class="error bp-ajax-message"><p>' . $apbct->spam_notification . '</p></div>');
        // Nouveau template and others
    } else {
        @header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        die(
            json_encode(
                array(
                    'success' => false,
                    'data'    => array('message' => $apbct->spam_notification),
                )
            )
        );
    }
}

/**
 * Public function - Tests new private messages (dialogs)
 *
 * @param object $bp_message_obj
 *
 * @return void with errors if spam has found
 * @psalm-suppress UndefinedClass
 * @psalm-suppress UnusedVariable
 * @global State $apbct
 */
function apbct_integration__buddyPres__private_msg_check($bp_message_obj)
{
    global $apbct;

    //Check for enabled option
    if (
        $apbct->settings['comments__bp_private_messages'] == 0 ||
        apbct_exclusions_check()
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return;
    }

    //Check for quantity of comments
    $comments_check_number = defined('CLEANTALK_CHECK_COMMENTS_NUMBER')
        ? CLEANTALK_CHECK_COMMENTS_NUMBER
        : 3;

    if ( $apbct->settings['comments__check_comments_number'] ) {
        $args             = array(
            'user_id'      => $bp_message_obj->sender_id,
            'box'          => 'sentbox',
            'type'         => 'all',
            'limit'        => $comments_check_number,
            'page'         => null,
            'search_terms' => '',
            'meta_query'   => array()
        );
        $sentbox_msgs     = BP_Messages_Thread::get_current_threads_for_user($args);
        $cnt_sentbox_msgs = $sentbox_msgs['total'];
        $args['box']      = 'inbox';
        $inbox_msgs       = BP_Messages_Thread::get_current_threads_for_user($args);
        $cnt_inbox_msgs   = $inbox_msgs['total'];

        if ( ($cnt_inbox_msgs + $cnt_sentbox_msgs) >= $comments_check_number ) {
            $is_max_comments = true;
        }
    }

    $exception_action = false;
    if ( ! empty($is_max_comments) ) {
        $exception_action = true;
    }

    $sender_user_obj = get_user_by('id', $bp_message_obj->sender_id);

    //Making a call
    $base_call_result = apbct_base_call(
        array(
            'message'         => $bp_message_obj->subject . " " . $bp_message_obj->message,
            'sender_email'    => $sender_user_obj->data->user_email,
            'sender_nickname' => $sender_user_obj->data->user_login,
            'post_info'       => array(
                'comment_type' => 'buddypress_comment',
                'post_url'     => apbct_get_server_variable('HTTP_REFERER'),
            ),
            'js_on'           => apbct_js_test('ct_checkjs', $_COOKIE, true) ?: apbct_js_test('ct_checkjs', $_POST),
            'sender_info'     => array('sender_url' => null),
            'exception_action' => $exception_action === true ? 1 : null
        )
    );

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

/**
 * Adds hidden filed to default search form
 *
 * @param $form string
 *
 * @return string
 */
function apbct_forms__search__addField($form)
{
    global $apbct;
    if ( $apbct->settings['forms__search_test'] == 1 ) {
        $js_filed = ct_add_hidden_fields('ct_checkjs_search_default', true, false, false, false);
        $form     = str_replace('</form>', $js_filed, $form);
    }

    return $form;
}

/**
 * Test default search string for spam
 *
 * @param $search string
 *
 * @return string
 */
function apbct_forms__search__testSpam($search)
{
    global $apbct, $cleantalk_executed;

    if (
        empty($search) ||
        $cleantalk_executed ||
        $apbct->settings['forms__search_test'] == 0 ||
        ($apbct->settings['data__protect_logged_in'] != 1 && is_user_logged_in()) // Skip processing for logged in users.
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $search;
    }

    $user = apbct_is_user_logged_in() ? wp_get_current_user() : null;

    $base_call_result = apbct_base_call(
        array(
            'message'         => $search,
            'sender_email'    => $user !== null ? $user->user_email : null,
            'sender_nickname' => $user !== null ? $user->user_login : null,
            'post_info'       => array('comment_type' => 'site_search_wordpress'),
            'exception_action' => 0,
        )
    );
    $ct_result        = $base_call_result['ct_result'];

    $cleantalk_executed = true;

    if ( $ct_result->allow == 0 ) {
        die($ct_result->comment);
    }

    return $search;
}

function apbct_search_add_noindex()
{
    global $apbct;

    if (
        ! is_search() || // If it is search results
        $apbct->settings['forms__search_test'] == 0 ||
        ($apbct->settings['data__protect_logged_in'] != 1 && is_user_logged_in()) // Skip processing for logged in users.
    ) {
        return;
    }

    echo '<!-- meta by CleanTalk Anti-Spam Protection plugin -->' . "\n";
    echo '<meta name="robots" content="noindex,nofollow" />' . "\n";
}

/**
 * Test woocommerce checkout form for spam
 */
function ct_woocommerce_checkout_check($_data, $errors)
{
    global $apbct, $cleantalk_executed;

    if ( count($errors->errors) ) {
        return;
    }

    /**
     * Filter for POST
     */
    $input_array = apply_filters('apbct__filter_post', $_POST);

    //Getting request params
    $ct_temp_msg_data = ct_get_fields_any($input_array);

    $sender_email    = $ct_temp_msg_data['email'] ?: '';
    $sender_nickname = $ct_temp_msg_data['nickname'] ?: '';
    $subject         = $ct_temp_msg_data['subject'] ?: '';
    $message         = $ct_temp_msg_data['message'] ?: array();

    if ( $subject != '' ) {
        $message = array_merge(array('subject' => $subject), $message);
    }

    $post_info['comment_type'] = 'order';
    $post_info['post_url']     = apbct_get_server_variable('HTTP_REFERER');

    $base_call_data = array(
        'message'         => $message,
        'sender_email'    => $sender_email,
        'sender_nickname' => $sender_nickname,
        'post_info'       => $post_info,
        'js_on'           => apbct_js_test('ct_checkjs', $_COOKIE, true),
        'sender_info'     => array('sender_url' => null)
    );

    //Making a call
    $base_call_result = apbct_base_call($base_call_data);

    if ( $apbct->settings['forms__wc_register_from_order'] ) {
        $cleantalk_executed = false;
    }

    $ct_result = $base_call_result['ct_result'];

    if ( $ct_result->allow == 0 ) {
        wp_send_json(array(
            'result'   => 'failure',
            'messages' => "<ul class=\"woocommerce-error\"><li>" . $ct_result->comment . "</li></ul>",
            'refresh'  => 'false',
            'reload'   => 'false'
        ));
    }
}

/**
 * Triggered when adding an item to the shopping cart
 * for un-logged users
 *
 * @param $cart_item_key
 * @param $product_id
 * @param $quantity
 * @param $variation_id
 * @param $variation
 * @param $cart_item_data
 *
 * @return void
 */

function apbct_wc__add_to_cart_unlogged_user(
    $_cart_item_key,
    $_product_id,
    $_quantity,
    $_variation_id,
    $_variation,
    $_cart_item_data
) {
    global $apbct;

    if ( ! apbct_is_user_logged_in() && $apbct->settings['forms__wc_add_to_cart'] ) {
        /**
         * Getting request params
         * POST contains an array of product information
         * Example: Array
         *(
         *    [product_sku] => woo-beanie
         *    [product_id] => 15
         *    [quantity] => 1
         *)
         */
        $message = $_POST ?: array();

        $post_info['comment_type'] = 'order__add_to_cart';
        $post_info['post_url']     = Server::get('HTTP_REFERER');

        //Making a call
        $base_call_result = apbct_base_call(
            array(
                'message'     => $message,
                'post_info'   => $post_info,
                'js_on'       => apbct_js_test('ct_checkjs', $_COOKIE, true),
                'sender_info' => array('sender_url' => null),
            )
        );

        $ct_result = $base_call_result['ct_result'];

        if ( $ct_result->allow == 0 ) {
            wp_send_json(array(
                'result'        => 'failure',
                'messages'      => "<ul class=\"woocommerce-error\"><li>" . $ct_result->comment . "</li></ul>",
                'refresh'       => 'false',
                'reload'        => 'false',
                'response_type' => 'wc_add_to_cart_block'
            ));
        }
    }
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

    $sender_email    = $ct_temp_msg_data['email'] ?: '';
    $sender_nickname = $ct_temp_msg_data['nickname'] ?: '';
    $subject         = $ct_temp_msg_data['subject'] ?: '';
    $message         = $ct_temp_msg_data['message'] ?: array();

    if ( $subject !== '' ) {
        $message = array_merge(array('subject' => $subject), $message);
    }

    $post_info['comment_type'] = 'contact_form_wordpress_feedback_pirate';
    $post_info['post_url']     = apbct_get_server_variable('HTTP_REFERER');

    //Making a call
    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => $post_info,
            'js_on'           => apbct_js_test('ct_checkjs', $_COOKIE, true),
            'sender_info'     => array('sender_url' => null),
        )
    );

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
    $input_array = apply_filters('apbct__filter_post', $_POST['item_meta']);

    $form_data = array();
    foreach ( $input_array as $key => $value ) {
        $form_data['item_meta[' . $key . ']'] = $value;
    }

    $ct_temp_msg_data = ct_get_fields_any($form_data);

    $sender_email    = $ct_temp_msg_data['email'] ?: '';
    $sender_nickname = $ct_temp_msg_data['nickname'] ?: '';
    $message         = $ct_temp_msg_data['message'] ?: array();

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
        $value = 'item_meta[' . $value . ']';
    }
    unset($value);
    // @ToDO Need to be solved psalm notice about InvalidScalarArgument
    $tmp_message = array_flip($tmp_message);
    // Combine it with non-scalar values
    $message = array_merge($tmp_message, $tmp_message2);

    $checkjs = apbct_js_test('ct_checkjs', $_COOKIE, true) ?: apbct_js_test('ct_checkjs', $_POST);

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => array('comment_type' => 'contact_form_wordpress_formidable'),
            'js_on'           => $checkjs
        )
    );
    $ct_result        = $base_call_result['ct_result'];

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

    return $errors;
}

/**
 * Get field key for ajax response of formidable form
 */
function apbct__formidable_get_key_field_for_ajax_response($_form = array())
{
    $key_field = '113';

    if (
        isset($_POST['item_meta']) &&
        is_array($_POST['item_meta'])
    ) {
        $key_field = array_keys($_POST['item_meta'])[1];
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
 * @param mixed[] $comment Comment string
 *
 * @return  mixed[] $comment Comment string
 * @psalm-suppress UndefinedFunction
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

    $checkjs = apbct_js_test('ct_checkjs', $_COOKIE, true) ?: apbct_js_test('ct_checkjs', $_POST);

    $post_info['comment_type'] = 'bbpress_comment';
    $post_info['post_url']     = bbp_get_topic_permalink();

    if ( is_user_logged_in() ) {
        $sender_email    = $current_user->user_email;
        $sender_nickname = $current_user->display_name;
    } else {
        $sender_email    = isset($_POST['bbp_anonymous_email']) ? $_POST['bbp_anonymous_email'] : null;
        $sender_nickname = isset($_POST['bbp_anonymous_name']) ? $_POST['bbp_anonymous_name'] : null;
    }

    $base_call_result = apbct_base_call(
        array(
            'message'         => $comment,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => $post_info,
            'js_on'           => $checkjs,
            'sender_info'     => array('sender_url' => isset($_POST['bbp_anonymous_website']) ? $_POST['bbp_anonymous_website'] : null),
        )
    );
    $ct_result        = $base_call_result['ct_result'];

    if ( $ct_result->allow == 0 ) {
        bbp_add_error('bbp_reply_content', $ct_result->comment);
    }

    return $comment;
}

function apbct_comment__sanitize_data__before_wp_die($function)
{
    global $apbct;

    $comment_data = wp_unslash($_POST);

    $user_ID = 0;

    $comment_type = '';

    $comment_content = isset($comment_data['comment']) ? (string)$comment_data['comment'] : null;
    $comment_parent  = isset($comment_data['comment_parent']) ? absint($comment_data['comment_parent']) : null;

    $comment_author       = isset($comment_data['author']) ? trim(strip_tags($comment_data['author'])) : null;
    $comment_author_email = isset($comment_data['email']) ? trim($comment_data['email']) : null;
    $comment_author_url   = isset($comment_data['url']) ? trim($comment_data['url']) : null;
    $comment_post_ID      = isset($comment_data['comment_post_ID']) ? (int)$comment_data['comment_post_ID'] : null;

    if ( isset($comment_content, $comment_parent) ) {
        $user = function_exists('apbct_wp_get_current_user') ? apbct_wp_get_current_user() : null;

        if ( $user && $user->exists() ) {
            $comment_author       = empty($user->display_name) ? $user->user_login : $user->display_name;
            $comment_author_email = $user->user_email;
            $comment_author_url   = $user->user_url;
            $user_ID              = $user->ID;
        }

        $apbct->comment_data = compact(
            'comment_post_ID',
            'comment_author',
            'comment_author_email',
            'comment_author_url',
            'comment_content',
            'comment_type',
            'comment_parent',
            'user_ID'
        );

        $function = 'apbct_comment__check_via_wp_die';
    }

    return $function;
}

function apbct_comment__check_via_wp_die($message, $title, $args)
{
    global $apbct;
    if ( $title == __('Comment Submission Failure') ) {
        $apbct->validation_error = $message;
        ct_preprocess_comment($apbct->comment_data);
    }
    _default_wp_die_handler($message, $title, $args);
}

/**
 * Public filter 'preprocess_comment' - Checks comment by cleantalk server
 *
 * @param mixed[] $comment Comment data array
 *
 * @return    mixed[] New data array of comment
 * @psalm-suppress UnusedVariable
 */
function ct_preprocess_comment($comment)
{
    // this action is called just when WP process POST request (adds new comment)
    // this action is called by wp-comments-post.php
    // after processing WP makes redirect to post page with comment's form by GET request (see above)
    global $current_user, $comment_post_id, $ct_comment_done, $ct_jp_comments, $apbct, $ct_comment, $ct_stop_words;

    // Send email notification for chosen groups of users
    if ( $apbct->settings['wp__comment_notify'] && ! empty($apbct->settings['wp__comment_notify__roles']) && $apbct->data['moderate'] ) {
        add_filter('notify_post_author', 'apbct_comment__Wordpress__doNotify', 100, 2);

        $users = get_users(array(
            'role__in' => $apbct->settings['wp__comment_notify__roles'],
            'fileds'   => array('user_email')
        ));

        if ( $users ) {
            add_filter('comment_notification_text', 'apbct_comment__Wordpress__changeMailNotificationGroups', 100, 2);
            add_filter(
                'comment_notification_recipients',
                'apbct_comment__Wordpress__changeMailNotificationRecipients',
                100,
                2
            );
            foreach ( $users as $user ) {
                $emails[] = $user->user_email;
            }
            $apbct->comment_notification_recipients = json_encode($emails);
        }
    }

    // Skip processing admin.
    if ( in_array("administrator", $current_user->roles) ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $comment;
    }

    $comments_check_number = defined('CLEANTALK_CHECK_COMMENTS_NUMBER') ? CLEANTALK_CHECK_COMMENTS_NUMBER : 3;

    if ( $apbct->settings['comments__check_comments_number'] && $comment['comment_author_email'] ) {
        $args            = array(
            'author_email' => $comment['comment_author_email'],
            'status'       => 'approve',
            'count'        => false,
            'number'       => $comments_check_number,
        );
        $cnt             = count(get_comments($args));
        $is_max_comments = $cnt >= $comments_check_number ? true : false;
    }

    if (
        ($comment['comment_type'] !== 'trackback') &&
        (
            apbct_is_user_enable() === false ||
            $apbct->settings['forms__comments_test'] == 0 ||
            $ct_comment_done ||
            (isset($_SERVER['HTTP_REFERER']) && stripos($_SERVER['HTTP_REFERER'], 'page=wysija_campaigns&action=editTemplate') !== false) ||
            (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['REQUEST_URI'], '/wp-admin/') !== false)
        )
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $comment;
    }

    $local_blacklists = apbct_wp_blacklist_check(
        $comment['comment_author'],
        $comment['comment_author_email'],
        $comment['comment_author_url'],
        $comment['comment_content'],
        apbct_get_server_variable('REMOTE_ADDR'),
        apbct_get_server_variable('HTTP_USER_AGENT')
    );

    // Go out if author in local blacklists
    if ( $comment['comment_type'] !== 'trackback' && $local_blacklists === true ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $comment;
    }

    $ct_comment_done = true;

    $comment_post_id = $comment['comment_post_ID'];

    // JetPack comments logic
    $post_info['comment_type'] = $ct_jp_comments ? 'jetpack_comment' : $comment['comment_type'];
    $post_info['post_url']     = ct_post_url(null, $comment_post_id);

    // Comment type
    $post_info['comment_type'] = empty($post_info['comment_type']) ? 'general_comment' : $post_info['comment_type'];

    $checkjs = apbct_js_test('ct_checkjs', $_COOKIE, true) ?: apbct_js_test('ct_checkjs', $_POST);

    $example = null;
    if ( $apbct->data['relevance_test'] ) {
        $post = get_post($comment_post_id);
        if ( $post !== null ) {
            $example['title']    = $post->post_title;
            $example['body']     = $post->post_content;
            $example['comments'] = null;

            $last_comments = get_comments(array('status' => 'approve', 'number' => 10, 'post_id' => $comment_post_id));
            foreach ( $last_comments as $post_comment ) {
                $example['comments'] .= "\n\n" . $post_comment->comment_content;
            }

            $example = json_encode($example);
        }

        // Use plain string format if've failed with JSON
        if ( $example === false || $example === null ) {
            $example = ($post->post_title !== null) ? $post->post_title : '';
            $example .= ($post->post_content !== null) ? "\n\n" . $post->post_content : '';
        }
    }

    $base_call_data = array(
        'message'         => $comment['comment_content'],
        'example'         => $example,
        'sender_email'    => $comment['comment_author_email'],
        'sender_nickname' => $comment['comment_author'],
        'post_info'       => $post_info,
        'js_on'           => $checkjs,
        'sender_info'     => array(
            'sender_url'      => @$comment['comment_author_url'],
            'form_validation' => ! isset($apbct->validation_error)
                ? null
                : json_encode(
                    array(
                        'validation_notice' => $apbct->validation_error,
                        'page_url'          => apbct_get_server_variable('HTTP_HOST') . apbct_get_server_variable('REQUEST_URI'),
                    )
                )
        ),
    );

    if ( isset($is_max_comments) && $is_max_comments ) {
        $base_call_data['exception_action'] = 1;
    }

    /**
     * Add honeypot_field to $base_call_data is comments__hide_website_field on
     */
    if ( isset($apbct->settings['comments__hide_website_field']) && $apbct->settings['comments__hide_website_field'] ) {
        $honeypot_field = 1;

        if (
            $post_info['comment_type'] === 'comment' &&
            Post::get('url') &&
            Post::get('comment_post_ID')
        ) {
            $honeypot_field = 0;
            // if url is filled then pass them to $base_call_data as additional fields
            $base_call_data['sender_info']['honeypot_field_value']  = Post::get('url');
            $base_call_data['sender_info']['honeypot_field_source'] = 'url';
        }

        $base_call_data['honeypot_field'] = $honeypot_field;
    }

    $base_call_result = apbct_base_call($base_call_data);

    $ct_result = $base_call_result['ct_result'];

    ct_hash($ct_result->id);

    //Don't check trusted users
    if ( isset($comment['comment_author_email']) ) {
        $approved_comments = get_comments(
            array('status' => 'approve', 'count' => true, 'author_email' => $comment['comment_author_email'])
        );
        $new_user          = $approved_comments == 0 ? true : false;
    }

    // Change comment flow only for new authors
    if ( ! empty($new_user) || empty($base_call_data['post_info']['post_url']) ) {
        add_action('comment_post', 'ct_set_meta', 10, 2);
    }

    if ( $ct_result->allow ) { // Pass if allowed
        // If moderation is required
        if ( get_option('comment_moderation') === '1' ) {
            add_filter('pre_comment_approved', 'ct_set_not_approved', 999, 2);
        // If new author have to be moderated
        } elseif ( get_option('comment_previously_approved') === '1' && get_option('cleantalk_allowed_moderation', 1) != 1 ) {
            $comment_author = isset($comment['comment_author']) ? $comment['comment_author'] : '';
            $comment_author_email = isset($comment['comment_author_email']) ? $comment['comment_author_email'] : '';
            $comment_author_url = isset($comment['comment_author_url']) ? $comment['comment_author_url'] : '';
            $comment_content = isset($comment['comment_content']) ? $comment['comment_content'] : '';
            $comment_author_IP = isset($comment['comment_author_IP']) ? $comment['comment_author_IP'] : '';
            $comment_agent = isset($comment['comment_agent']) ? $comment['comment_agent'] : '';
            $comment_type = isset($comment['comment_type']) ? $comment['comment_type'] : '';
            if (
                check_comment(
                    $comment_author,
                    $comment_author_email,
                    $comment_author_url,
                    $comment_content,
                    $comment_author_IP,
                    $comment_agent,
                    $comment_type
                )
            ) {
                add_filter('pre_comment_approved', 'ct_set_approved', 999, 2);
            } else {
                add_filter('pre_comment_approved', 'ct_set_not_approved', 999, 2);
            }
        // Allowed comment will be published
        } else {
            add_filter('pre_comment_approved', 'ct_set_approved', 999, 2);
        }
        // Modify the email notification
        add_filter(
            'comment_notification_text',
            'apbct_comment__wordpress__show_blacklists',
            100,
            2
        ); // Add two blacklist links: by email and IP
    } else {
        $ct_comment    = $ct_result->comment;
        $ct_stop_words = $ct_result->stop_words;

        $err_text =
            '<center>'
            . ((defined('CLEANTALK_DISABLE_BLOCKING_TITLE') && CLEANTALK_DISABLE_BLOCKING_TITLE == true)
                ? ''
                : '<b style="color: #49C73B;">Clean</b><b style="color: #349ebf;">Talk.</b> ')
                . __('Spam protection', 'cleantalk-spam-protect')
            . "</center><br><br>\n"
            . $ct_result->comment;
        if ( ! $ct_jp_comments ) {
            $err_text .= '<script>setTimeout("history.back()", 5000);</script>';
        }

        // Terminate. Definitely spam.
        if ( $ct_result->stop_queue == 1 ) {
            wp_die($err_text, 'Blacklisted', array('response' => 200, 'back_link' => ! $ct_jp_comments));
        }

        // Terminate by user's setting.
        if ( $ct_result->spam == 3 ) {
            wp_die($err_text, 'Blacklisted', array('response' => 200, 'back_link' => ! $ct_jp_comments));
        }

        // Trash comment.
        if ( $ct_result->spam == 2 ) {
            add_filter('pre_comment_approved', 'ct_set_comment_spam', 997, 2);
            add_action('comment_post', 'ct_wp_trash_comment', 997, 2);
        }

        // Spam comment
        if ( $ct_result->spam == 1 ) {
            add_filter('pre_comment_approved', 'ct_set_comment_spam', 997, 2);
        }

        // Move to pending folder. Contains stop_words.
        if ( $ct_result->stop_words ) {
            add_filter('pre_comment_approved', 'ct_set_not_approved', 998, 2);
            add_action('comment_post', 'ct_mark_red', 998, 2);
        }

        add_action('comment_post', 'ct_die', 999, 2);
    }

    if ( $apbct->settings['comments__remove_comments_links'] == 1 ) {
        $comment['comment_content'] = preg_replace(
            "~(http|https|ftp|ftps)://(.*?)(\s|\n|[,.?!](\s|\n)|$)~",
            '[Link deleted]',
            $comment['comment_content']
        );
    }

    // Change mail notification if license is out of date
    if ( $apbct->data['moderate'] == 0 ) {
        $apbct->sender_email = $comment['comment_author_email'];
        $apbct->sender_ip    = Helper::ipGet('real');
        add_filter(
            'comment_moderation_text',
            'apbct_comment__Wordpress__changeMailNotification',
            100,
            2
        ); // Comment sent to moderation
        add_filter(
            'comment_notification_text',
            'apbct_comment__Wordpress__changeMailNotification',
            100,
            2
        ); // Comment approved
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
    echo ct_add_honeypot_field('wp_register');

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
    $email = $_POST['reg_email'];
    $login = $_POST['reg_username'];

    $reg_errors = ct_registration_errors($reg_errors, $login, $email);

    return $reg_errors;
}

/**
 * Test users registration for multisite environment
 * @return array|mixed with errors
 */
function ct_registration_errors_wpmu($errors)
{
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
    if ( isset($errors['errors']->errors['ct_error']) ) {
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
            'js_on'           => apbct_js_test('ct_checkjs', $_COOKIE, true),
        )
    );

    $ct_result = $base_call_result['ct_result'];

    return array(
        'allow'   => $ct_result->allow,
        'comment' => $ct_result->comment,
    );
}

/**
 * Check registrations for external plugins
 * @return array with checking result;
 */
function ct_test_registration($nickname, $email, $ip = null)
{
    global $ct_checkjs_register_form;

    if ( apbct_js_test($ct_checkjs_register_form, $_POST) ) {
        $checkjs                            = apbct_js_test($ct_checkjs_register_form, $_POST);
        $sender_info['post_checkjs_passed'] = $checkjs;
    } else {
        $checkjs                              = apbct_js_test('ct_checkjs', $_COOKIE, true);
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
    $ct_result        = $base_call_result['ct_result'];
    ct_hash($ct_result->id);
    $result = array(
        'allow'   => $ct_result->allow,
        'comment' => $ct_result->comment,
    );

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

        return $errors;
    }

    $facebook = false;
    // Facebook registration
    if ( $sanitized_user_login === null && isset($_POST['FB_userdata']) ) {
        $sanitized_user_login = $_POST['FB_userdata']['name'];
        $facebook             = true;
    }
    if ( $user_email === null && isset($_POST['FB_userdata']) ) {
        $user_email = $_POST['FB_userdata']['email'];
        $facebook   = true;
    }

    // BuddyPress actions
    $buddypress = false;
    if ( $sanitized_user_login === null && isset($_POST['signup_username']) ) {
        $sanitized_user_login = $_POST['signup_username'];
        $buddypress           = true;
    }
    if ( $user_email === null && isset($_POST['signup_email']) ) {
        $user_email = $_POST['signup_email'];
        $buddypress = true;
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
        $checkjs        = apbct_js_test('ct_checkjs', $_COOKIE, true);
        $checkjs_post   = null;
        $checkjs_cookie = $checkjs;
    } else {
        // This hack can be helpful when plugin uses with untested themes&signups plugins.
        $checkjs_post   = apbct_js_test($ct_checkjs_register_form, $_POST);
        $checkjs_cookie = apbct_js_test('ct_checkjs', $_COOKIE, true);
        $checkjs        = $checkjs_cookie ?: $checkjs_post;
    }

    $sender_info = array(
        'post_checkjs_passed'   => $checkjs_post,
        'cookie_checkjs_passed' => $checkjs_cookie,
        'form_validation'       => ! empty($errors)
            ? json_encode(
                array(
                    'validation_notice' => $errors->get_error_message(),
                    'page_url'          => apbct_get_server_variable('HTTP_HOST') . apbct_get_server_variable('REQUEST_URI'),
                )
            )
            : null,
    );

    /**
     * Changing the type of check for BuddyPress
     */
    if ( Post::get('signup_username') && Post::get('signup_email') ) {
        // if buddy press set up custom fields
        $reg_flag = empty(Post::get('signup_profile_field_ids'));
    }

    $base_call_array = array(
        'sender_email'    => $user_email,
        'sender_nickname' => $sanitized_user_login,
        'sender_info'     => $sender_info,
        'js_on'           => $checkjs,
    );

    if ( !$reg_flag ) {
        $field_values = '';
        $fields_numbers_to_check = explode(',', Post::get('signup_profile_field_ids'));
        foreach ( $fields_numbers_to_check as $field_number ) {
            $field_name = 'field_' . $field_number;
            $field_value = Post::get($field_name) ? Post::get($field_name) : '';
            $field_values .= $field_value . "\n";
        }
        $base_call_array['message'] = $field_values;
    }

    $base_call_result = apbct_base_call(
        $base_call_array,
        $reg_flag
    );

    $ct_result        = $base_call_result['ct_result'];
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

    $ct_result = ct_change_plugin_resonse($ct_result, $checkjs);

    $cleantalk_executed = true;

    if ( $ct_result->inactive != 0 ) {
        ct_send_error_notice($ct_result->comment);

        return $errors;
    }

    if ( $ct_result->allow == 0 ) {
        if ( $buddypress === true ) {
            $bp->signup->errors['signup_username'] = $ct_result->comment;
        } elseif ( $facebook ) {
            $_POST['FB_userdata']['email'] = '';
            $_POST['FB_userdata']['name']  = '';

            return;
        } elseif ( defined('MGM_PLUGIN_NAME') ) {
            ct_die_extended($ct_result->comment);
        } else {
            if ( is_wp_error($errors) ) {
                $errors->add('ct_error', $ct_result->comment);
            }
            $ct_negative_comment = $ct_result->comment;
        }

        $ct_registration_error_comment = $ct_result->comment;
    } else {
        if ( $ct_result->id !== null ) {
            $apbct_cookie_request_id = $ct_result->id;
            Cookie::set($apbct_cookie_register_ok_label, $ct_result->id, time() + 10, '/');
            Cookie::set($apbct_cookie_request_id_label, $ct_result->id, time() + 10, '/');
        }
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
                                                           'clentalk'
                                                       )
                                                       . 'https://cleantalk.org/my/?cp_mode=antispam&utm_source=newsletter&utm_medium=email&utm_campaign=wp_spam_registration_passed'
                                                       . ($apbct->data['user_token']
            ? '&iser_token=' . $apbct->data['user_token']
            : ''
                                                       )
                                                       . PHP_EOL . '---'
                                                       . PHP_EOL
                                                       . $wp_new_user_notification_email_admin['message'];

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

    if ( isset(UM()->form()->errors) ) {
        $sender_info['previous_form_validation'] = true;
        $sender_info['validation_notice']        = json_encode(UM()->form()->errors);
    }

    if ( $apbct->settings['forms__registrations_test'] == 0 ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $args;
    }

    $checkjs                            = apbct_js_test('ct_checkjs_register_form', $args);
    $sender_info['post_checkjs_passed'] = $checkjs;

    // This hack can be helpfull when plugin uses with untested themes&signups plugins.
    if ( $checkjs == 0 ) {
        $checkjs                              = apbct_js_test('ct_checkjs', $_COOKIE, true);
        $sender_info['cookie_checkjs_passed'] = $checkjs;
    }

    $base_call_result = apbct_base_call(
        array(
            'sender_email'    => $args['user_email'],
            'sender_nickname' => $args['user_login'],
            'sender_info'     => $sender_info,
            'js_on'           => $checkjs,
        ),
        true
    );
    $ct_result        = $base_call_result['ct_result'];

    $cleantalk_executed = true;

    if ( $ct_result->inactive != 0 ) {
        ct_send_error_notice($ct_result->comment);

        return $args;
    }

    if ( $ct_result->allow == 0 ) {
        UM()->form()->add_error('user_password', $ct_result->comment);
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
function ct_check_registration_erros($errors, $_sanitized_user_login = null, $_user_email = null)
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
                $ct_checkjs_jpcf = $matches[1] . $ct_checkjs_jpcf;
                $name_patched    = true;
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

    $js_field_name = $ct_checkjs_jpcf;
    foreach ( $_POST as $k => $_v ) {
        if ( preg_match("/^.+$ct_checkjs_jpcf$/", $k) ) {
            $js_field_name = $k;
        }
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
            'js_on'           => apbct_js_test('ct_checkjs', $_COOKIE, true) ?: apbct_js_test($ct_checkjs_jpcf, $_POST),

        )
    );
    $ct_result        = $base_call_result['ct_result'];

    if ( $ct_result->allow == 0 ) {
        $ct_comment = $ct_result->comment;
        ct_die(null, null);
        exit;
    }

    return ! $ct_result->allow;
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
            'js_on'           => apbct_js_test('ct_checkjs', $_COOKIE, true) ?: apbct_js_test($ct_checkjs_jpcf, $_POST),
        )
    );
    $ct_result        = $base_call_result['ct_result'];

    if ( $ct_result->allow == 0 ) {
        $ct_comment = $ct_result->comment;
        ct_die(null, null);
        exit;
    }

    return ! $ct_result->allow;
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
    $html .= ct_add_honeypot_field('wp_contact_form_7');

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
        apbct_exclusions_check__ip() ||
        isset($apbct->cf7_checked)
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return $spam;
    }

    $checkjs = apbct_js_test('ct_checkjs', $_COOKIE, true) ?: apbct_js_test($ct_checkjs_cf7, $_POST);
    /**
     * Filter for POST
     */
    $input_array = apply_filters('apbct__filter_post', $_POST);

    $ct_temp_msg_data = ct_get_fields_any($input_array);

    $sender_email    = $ct_temp_msg_data['email'] ?: '';
    $sender_nickname = $ct_temp_msg_data['nickname'] ?: '';
    $subject         = $ct_temp_msg_data['subject'] ?: '';
    $message         = $ct_temp_msg_data['message'] ?: array();
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
                        'page_url'          => apbct_get_server_variable('HTTP_HOST') . apbct_get_server_variable('REQUEST_URI'),
                    ))
            ),
        )
    );

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

        $spam = defined('WPCF7_VERSION') && WPCF7_VERSION >= '3.0.0';
    }

    $apbct->cf7_checked = true;

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

    $component['body'] =
        __('CleanTalk Anti-Spam: This message is spam.', 'cleantalk-spam-protect')
        . PHP_EOL . __('CleanTalk\'s Anti-Spam database:', 'cleantalk-spam-protect')
        . PHP_EOL . 'IP: ' . $apbct->sender_ip
        . PHP_EOL . 'Email: ' . $apbct->sender_email
        . PHP_EOL . sprintf(
            __('Activate protection in your Anti-Spam Dashboard: %s.', 'clentalk'),
            'https://cleantalk.org/my/?cp_mode=antispam&utm_source=newsletter&utm_medium=email&utm_campaign=cf7_activate_antispam&user_token=' . $apbct->user_token
        )
        . PHP_EOL . '---' . PHP_EOL . PHP_EOL
        . $component['body'];

    return (array)$component;
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

    $checkjs = apbct_js_test('ct_checkjs', $_COOKIE, true);

    /**
     * Filter for POST
     */
    $input_array = apply_filters('apbct__filter_post', $_POST);

    // Choosing between POST and GET
    $params = ct_get_fields_any(
        Get::get('ninja_forms_ajax_submit') || Get::get('nf_ajax_submit') ? $_GET : $input_array
    );

    $sender_email    = $params['email'] ?: '';
    $sender_nickname = $params['nickname'] ?: '';
    $subject         = $params['subject'] ?: '';
    $message         = $params['message'] ?: array();
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
            'js_on'           => $checkjs,
        )
    );
    $ct_result        = $base_call_result['ct_result'];

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

    // Show error message below field found by ID
    if ( array_key_exists('email', $data['fields_by_key']) ) {
        // Find ID of EMAIL field
        $nf_field_id = $data['fields_by_key']['email']['id'];
    } else {
        // Find ID of last field (usually SUBMIT)
        $fields_keys = array_keys($data['fields']);
        $nf_field_id = array_pop($fields_keys);
    }

    // Below is modified NJ logic
    $error = array(
        'fields' => array(
            $nf_field_id => $apbct->response,
        ),
    );

    $response = array('data' => $data, 'errors' => $error, 'debug' => '');

    die(wp_json_encode($response, JSON_FORCE_OBJECT));
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

    $sender_email    = $ct_temp_msg_data['email'] ?: '';
    $sender_nickname = $ct_temp_msg_data['nickname'] ?: '';
    $subject         = $ct_temp_msg_data['subject'] ?: '';
    $message         = $ct_temp_msg_data['message'] ?: array();
    if ( $subject != '' ) {
        $message = array_merge(array('subject' => $subject), $message);
    }

    $post_info['comment_type'] = 'contact_form_wordpress_seedprod_coming_soon';

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => $post_info,
        )
    );

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

        echo sanitize_text_field(Get::get('callback')) . '(' . json_encode($response) . ')';
        exit();
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
            . __('CleanTalk Anti-Spam: This message is spam.', 'cleantalk-spam-protect')
            . PHP_EOL . __('CleanTalk\'s Anti-Spam database:', 'cleantalk-spam-protect')
            . PHP_EOL . 'IP: ' . $apbct->sender_ip
            . PHP_EOL . 'Email: ' . $apbct->sender_email
            . PHP_EOL .
            __('Activate protection in your Anti-Spam Dashboard: ', 'clentalk') .
            'https://cleantalk.org/my/?cp_mode=antispam&utm_source=newsletter&utm_medium=email&utm_campaign=ninjaform_activate_antispam' . $apbct->user_token
        );
    }

    return $message;
}

/**
 * Inserts anti-spam hidden to WPForms
 *
 * @return void
 * @global State $apbct
 */
function apbct_form__WPForms__addField($_form_data, $_some, $_title, $_description, $_errors)
{
    global $apbct;

    if ( $apbct->settings['forms__contact_forms_test'] == 1 ) {
        ct_add_hidden_fields('ct_checkjs_wpforms');
        echo ct_add_honeypot_field('wp_wpforms');
    }
}

/**
 * Gather fields data from submission and store it
 *
 * @param array $entry
 * @param            $form
 *
 * @return array
 * @global State $apbct
 */
function apbct_from__WPForms__gatherData($entry, $form)
{
    global $apbct;
    $handled_result = array();

    /**
     * Filter for POST
     */
    $input_array = apply_filters('apbct__filter_post', $entry['fields']);

    $entry_fields_data = $input_array ?: array();
    $form_fields_info  = $form['fields'] ?: array();

    foreach ( $form_fields_info as $form_field ) {
        $field_id    = $form_field['id'];
        $field_type  = $form_field['type'];
        $field_label = $form_field['label'] ?: '';
        if ( ! isset($entry_fields_data[$field_id]) ) {
            continue;
        }
        $entry_field_value = $entry_fields_data[$field_id];

        # search email field
        if ( $field_type === 'email' ) {
            if ( ! isset($handled_result['email']) || empty($handled_result['email']) ) {
                $handled_result['email'] = $entry_field_value;
                continue;
            }
        }

        # search name
        if ( $field_type === 'name' ) {
            if ( is_array($entry_field_value) ) {
                $handled_result['name'][] = implode(' ', array_slice($entry_field_value, 0, 3));
            } else {
                $handled_result['name'][] = array('nick' => $entry_field_value, 'first' => '', 'last' => '');
            }
            continue;
        }

        # Add field label as key for result array
        # add unique key if key exist
        if ( $field_label ) {
            $field_label = mb_strtolower(trim($field_label));
            $field_label = str_replace(' ', '_', $field_label);
            $field_label = preg_replace('/\W/u', '', $field_label);

            if ( ! isset($handled_result[$field_label]) || empty($handled_result[$field_label]) ) {
                $handled_result[$field_label] = $entry_field_value;
            } else {
                $handled_result[$field_label . rand(0, 100)] = $entry_field_value;
            }
        }
    }

    $apbct->form_data = $handled_result;

    return $entry;
}

/**
 * Adding error to form entry if message is spam
 * Call spam test from here
 *
 * @param array $errors
 * @param array $form_data
 *
 * @return array
 */
function apbct_form__WPForms__showResponse($errors, $form_data)
{
    if (
        empty($errors) ||
        (isset($form_data['id'], $errors[$form_data['id']]) && ! count($errors[$form_data['id']]))
    ) {
        $spam_comment = apbct_form__WPForms__testSpam();

        $filed_id = $form_data && ! empty($form_data['fields']) && is_array($form_data['fields'])
            ? key($form_data['fields'])
            : 0;

        if ( $spam_comment ) {
            $errors[$form_data['id']][$filed_id] = $spam_comment;
        }
    }

    return $errors;
}

/**
 * Test WPForms message for spam
 * Doesn't hooked anywhere.
 * Called directly from apbct_form__WPForms__showResponse()
 *
 * @return string|void
 * @global State $apbct
 */
function apbct_form__WPForms__testSpam()
{
    global $apbct;

    if (
        $apbct->settings['forms__contact_forms_test'] == 0 ||
        ($apbct->settings['data__protect_logged_in'] != 1 && is_user_logged_in()) // Skip processing for logged in users.
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return;
    }

    $checkjs = apbct_js_test('ct_checkjs_wpforms', $_POST);

    $email = $apbct->form_data['email'] ?: null;

    # Fixed if the 'Enable email address confirmation' option is enabled
    if ( is_array($email) ) {
        $email = reset($email);
    }

    $nickname = $apbct->form_data['name'] && is_array($apbct->form_data['name']) ? array_shift(
        $apbct->form_data['name']
    ) : null;
    $form_data = $apbct->form_data;

    if ( $email ) {
        unset($form_data['email']);
    }
    if ( $nickname ) {
        unset($form_data['name']);
    }

    $params = ct_get_fields_any($apbct->form_data, $email, $nickname);

    if ( is_array($params['nickname']) ) {
        $params['nickname'] = implode(' ', $params['nickname']);
    }

    $sender_email    = $params['email'] ?: '';
    $sender_nickname = $params['nickname'] ?: '';
    $subject         = $params['subject'] ?: '';
    $message         = $params['message'] ?: array();
    if ( $subject !== '' ) {
        $message = array_merge(array('subject' => $subject), $message);
    }

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => array('comment_type' => 'contact_form_wordpress_wp_forms'),
            'js_on'           => $checkjs,
        )
    );
    $ct_result        = $base_call_result['ct_result'];

    // Change mail notification if license is out of date
    if ( $apbct->data['moderate'] == 0 &&
         ($ct_result->fast_submit == 1 || $ct_result->blacklisted == 1 || $ct_result->js_disabled == 1)
    ) {
        $apbct->sender_email = $sender_email;
        $apbct->sender_ip    = Helper::ipGet('real');
        add_filter('wpforms_email_message', 'apbct_form__WPForms__changeMailNotification', 100, 2);
    }

    if ( $ct_result->allow == 0 ) {
        return $ct_result->comment;
    }

    return null;
}

/**
 * Changes email notification for succes subscription for Ninja Forms
 *
 * @param string $message Body of email notification
 * @param object $wpforms_email WPForms email class object
 *
 * @return string Body for email notification
 */
function apbct_form__WPForms__changeMailNotification($message, $_wpforms_email)
{
    global $apbct;

    $message = str_replace(array('</html>', '</body>'), '', $message);
    $message .=
        wpautop(
            PHP_EOL
            . '---'
            . PHP_EOL
            . __('CleanTalk Anti-Spam: This message is spam.', 'cleantalk-spam-protect')
            . PHP_EOL . __('CleanTalk\'s Anti-Spam database:', 'cleantalk-spam-protect')
            . PHP_EOL . 'IP: ' . '<a href="https://cleantalk.org/blacklists/' . $apbct->sender_ip . '?utm_source=newsletter&utm_medium=email&utm_campaign=wpforms_spam_passed" target="_blank">' . $apbct->sender_ip . '</a>'
            . PHP_EOL . 'Email: ' . '<a href="https://cleantalk.org/blacklists/' . $apbct->sender_email . '?utm_source=newsletter&utm_medium=email&utm_campaign=wpforms_spam_passed" target="_blank">' . $apbct->sender_email . '</a>'
            . PHP_EOL
            . sprintf(
                __('Activate protection in your %sAnti-Spam Dashboard%s.', 'clentalk'),
                '<a href="https://cleantalk.org/my/?cp_mode=antispam&utm_source=newsletter&utm_medium=email&utm_campaign=wpforms_activate_antispam" target="_blank">',
                '</a>'
            )
        )
        . '</body></html>';

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
    // @ToDo If we have several emails at the form - will be used only the first detected!
    $sender_email = $ct_temp_msg_data['email'] ?: '';

    $checkjs          = apbct_js_test('ct_checkjs', $_COOKIE, true);
    $base_call_result = apbct_base_call(
        array(
            'message'      => $form->getValues(),
            'sender_email' => $sender_email,
            'post_info'    => array('comment_type' => $comment_type),
            'js_on'        => $checkjs,
        )
    );

    $ct_result = $base_call_result['ct_result'];
    if ( $ct_result->allow == 0 ) {
        die(
            json_encode(
                array('type' => 'error', 'apbct' => array('blocked' => true, 'comment' => $ct_result->comment)),
                JSON_HEX_QUOT | JSON_HEX_TAG
            )
        );
    }

    return $result;
}

/**
 * Inserts anti-spam hidden to Fast Secure contact form
 */
function ct_si_contact_display_after_fields($string = '', $_style = '', $_form_errors = array(), $_form_id_num = 0)
{
    $string .= ct_add_hidden_fields('ct_checkjs', true);

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

    $sender_email    = $ct_temp_msg_data['email'] ?: '';
    $sender_nickname = $ct_temp_msg_data['nickname'] ?: '';
    $subject         = $ct_temp_msg_data['subject'] ?: '';
    $message         = $ct_temp_msg_data['message'] ?: array();
    if ( $subject !== '' ) {
        $message['subject'] = $subject;
    }

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => array('comment_type' => 'contact_form_wordpress_fscf'),
            'js_on'           => apbct_js_test('ct_checkjs', $_POST),
        )
    );

    $ct_result = $base_call_result['ct_result'];

    $cleantalk_executed = true;

    if ( $ct_result->allow == 0 ) {
        $ct_comment = $ct_result->comment;
        ct_die(null, null);
        exit;
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
            if ( preg_match("/^\S+@\S+\.\S+$/", $v) ) {
                $sender_email = $v;
                break;
            }
        }

        $message = '';
        if ( array_key_exists('form_input_values', $_POST) ) {
            $form_input_values = json_decode(stripslashes($_POST['form_input_values']), true);
            if ( is_array($form_input_values) && array_key_exists('null', $form_input_values) ) {
                $message = $form_input_values['null'];
            }
        } elseif ( array_key_exists('null', $_POST) ) {
            $message = $_POST['null'];
        }

        $base_call_result = apbct_base_call(
            array(
                'message'      => $message,
                'sender_email' => $sender_email,
                'post_info'    => array('comment_type' => 'contact_form_wordpress_wplp'),
            )
        );

        $ct_result = $base_call_result['ct_result'];

        if ( $ct_result->allow == 0 ) {
            $cleantalk_comment = $ct_result->comment;
        } else {
            $cleantalk_comment = 'OK';
        }

        Cookie::set($ct_wplp_result_label, $cleantalk_comment, strtotime("+5 seconds"), '/');
    } else {
        // Next POST/AJAX submit(s) of same WPLP form
        $cleantalk_comment = $_COOKIE[$ct_wplp_result_label];
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
    $ct_hidden_field = 'ct_checkjs';

    // Do not add a hidden field twice.
    if ( preg_match("/$ct_hidden_field/", $form_string) ) {
        return $form_string;
    }

    $search = "</form>";

    // Adding JS code
    $js_code     = ct_add_hidden_fields($ct_hidden_field, true, false);
    $form_string = str_replace($search, $js_code . $search, $form_string);

    // Adding field for multipage form. Look for cleantalk.php -> apbct_cookie();
    $append_string = isset($form['lastPageButton']) ? "<input type='hidden' name='ct_multipage_form' value='yes'>" : '';
    $form_string   = str_replace($search, $append_string . $search, $form_string);

    return $form_string;
}

/**
 * Gravity forms anti-spam test.
 * @return boolean
 * @psalm-suppress UnusedVariable
 */
function apbct_form__gravityForms__testSpam($is_spam, $form, $entry)
{
    global $apbct, $cleantalk_executed, $ct_gform_is_spam, $ct_gform_response;

    if (
        $is_spam ||
        $apbct->settings['forms__contact_forms_test'] == 0 ||
        ($apbct->settings['data__protect_logged_in'] != 1 && apbct_is_user_logged_in()) || // Skip processing for logged in users.
        apbct_exclusions_check__url() ||
        apbct_exclusions_check__ip() ||
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

    $sender_email    = $ct_temp_msg_data['email'] ?: '';
    $sender_nickname = $ct_temp_msg_data['nickname'] ?: '';
    $subject         = $ct_temp_msg_data['subject'] ?: '';
    $message         = $ct_temp_msg_data['message'] ?: array();

    if ( $subject !== '' ) {
        $message['subject'] = $subject;
    }

    $checkjs = apbct_js_test('ct_checkjs', $_POST) ?: apbct_js_test('ct_checkjs', $_COOKIE, true);

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => array('comment_type' => 'contact_form_wordpress_gravity_forms'),
            'js_on'           => $checkjs,
        )
    );

    $ct_result = $base_call_result['ct_result'];
    if ( $ct_result->allow == 0 ) {
        $is_spam           = true;
        $ct_gform_is_spam  = true;
        $ct_gform_response = $ct_result->comment;
        add_action('gform_entry_created', 'apbct_form__gravityForms__add_entry_note');
    }

    return $is_spam;
}

function apbct_form__gravityForms__showResponse($confirmation, $form, $_entry, $_ajax)
{
    global $ct_gform_is_spam, $ct_gform_response;

    if ( ! empty($ct_gform_is_spam) ) {
        $confirmation = '<a id="gf_' . $form['id'] . '" class="gform_anchor" ></a><div id="gform_confirmation_wrapper_' . $form['id'] . '" class="gform_confirmation_wrapper "><div id="gform_confirmation_message_' . $form['id'] . '" class="gform_confirmation_message_' . $form['id'] . ' gform_confirmation_message"><font style="color: red">' . $ct_gform_response . '</font></div></div>';
    }

    return $confirmation;
}

/**
 * Adds a note to the entry once the spam status is set (GF 2.4.18+).
 *
 * @param array $entry The entry that was created.
 *
 * @psalm-suppress UndefinedClass
 * @psalm-suppress UndefinedFunction
 */
function apbct_form__gravityForms__add_entry_note($entry)
{
    if ( rgar($entry, 'status') !== 'spam' || ! method_exists('GFAPI', 'add_note') ) {
        return;
    }

    GFAPI::add_note(
        $entry['id'],
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

    $sender_email    = isset($_POST[$post_key]['email']) ? sanitize_email($_POST[$post_key]['email']) : null;
    $sender_nickname = isset($_POST[$post_key]['username']) ? sanitize_email($_POST[$post_key]['username']) : null;

    //Making a call
    $base_call_result = apbct_base_call(
        array(
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
        ),
        true
    );
    $ct_result        = $base_call_result['ct_result'];

    if ( $ct_result->allow == 0 ) {
        ct_die_extended($ct_result->comment);
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
        $post_info['comment_type'] = 'contact_form_wordpress_the7_theme_contact_form';

        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $_POST);

        $ct_temp_msg_data = ct_get_fields_any($input_array);

        $sender_email    = $ct_temp_msg_data['email'] ?: '';
        $sender_nickname = $ct_temp_msg_data['nickname'] ?: '';
        $subject         = $ct_temp_msg_data['subject'] ?: '';
        $contact_form    = ! $ct_temp_msg_data['contact'];
        $message         = $ct_temp_msg_data['message'] ?: array();
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
            )
        );

        $ct_result = $base_call_result['ct_result'];
        if ( $ct_result->allow == 0 ) {
            $response = json_encode(
                array(
                    'success' => false,
                    'errors'  => $ct_result->comment,
                    'nonce'   => wp_create_nonce('dt_contact_form')
                )
            );

            // response output
            header("Content-Type: application/json");
            echo $response;

            // IMPORTANT: don't forget to "exit"
            exit;
        }
    }

    return false;
}

function apbct_form__elementor_pro__testSpam()
{
    global $apbct;

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
    $input_array = apply_filters('apbct__filter_post', $_POST);

    $ct_temp_msg_data = ct_get_fields_any($input_array);

    $sender_email    = $ct_temp_msg_data['email'] ?: '';
    $sender_nickname = $ct_temp_msg_data['nickname'] ?: '';
    $subject         = $ct_temp_msg_data['subject'] ?: '';
    $message         = $ct_temp_msg_data['message'] ?: array();
    if ( $subject !== '' ) {
        $message = array_merge(array('subject' => $subject), $message);
    }

    $post_info['comment_type'] = 'contact_form_wordpress_elementor_pro';

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => $post_info,
        )
    );

    $ct_result = $base_call_result['ct_result'];

    if ( $ct_result->allow == 0 ) {
        wp_send_json_error(array(
            'message' => $ct_result->comment,
            'data'    => array()
        ));
    }
}

// INEVIO theme integration
function apbct_form__inevio__testSpam()
{
    global $apbct;

    $theme = wp_get_theme();
    if (
        stripos($theme->get('Name'), 'INEVIO') === false ||
        $apbct->settings['forms__contact_forms_test'] == 0 ||
        ($apbct->settings['data__protect_logged_in'] != 1 && is_user_logged_in()) || // Skip processing for logged in users.
        apbct_exclusions_check__url()
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return false;
    }
    $form_data = array();
    parse_str($_POST['data'], $form_data);

    $name    = isset($form_data['name']) ? $form_data['name'] : '';
    $email   = isset($form_data['email']) ? $form_data['email'] : '';
    $message = isset($form_data['message']) ? $form_data['message'] : '';

    $post_info['comment_type'] = 'contact_form_wordpress_inevio_theme';

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $email,
            'sender_nickname' => $name,
            'post_info'       => $post_info,
        )
    );

    $ct_result = $base_call_result['ct_result'];

    if ( $ct_result->allow == 0 ) {
        die(
            json_encode(
                array('apbct' => array('blocked' => true, 'comment' => $ct_result->comment,)),
                JSON_HEX_QUOT | JSON_HEX_TAG
            )
        );
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
    if ( $check['allow'] == 0 ) {
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
        )
    );

    $ct_result = $base_call_result['ct_result'];

    $cleantalk_executed = true;

    if ( $ct_result->allow == 0 ) {
        $obj->submit_error = $ct_result->comment;

        return null;
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
        $data = ct_get_fields_any($global_request);

        $base_call_result = apbct_base_call(
            array(
                'message'         => ! empty($data['message']) ? json_encode($data['message']) : '',
                'sender_email'    => ! empty($data['email']) ? $data['email'] : '',
                'sender_nickname' => ! empty($data['nickname']) ? $data['nickname'] : '',
                'post_info'       => array(
                    'comment_type' => 'register_profile_builder'
                ),
            ),
            true
        );

        $ct_result = $base_call_result['ct_result'];

        $cleantalk_executed = true;

        if ( $ct_result->allow == 0 ) {
            $errors['error']                         = $ct_result->comment;
            $GLOBALS['global_profile_builder_error'] = $ct_result->comment;

            add_filter('wppb_general_top_error_message', 'apbct_form_profile_builder__error_message', 1);
        }
    }

    return $errors;
}

/**
 * Profile Builder Integration - add error message in response
 */
function apbct_form_profile_builder__error_message()
{
    return '<p id="wppb_form_general_message" class="wppb-error">' . $GLOBALS['global_profile_builder_error'] . '</p>';
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
    if ( $check['allow'] == 0 ) {
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
    if ( $apbct->settings['forms__registrations_test'] && isset($_POST['action']) && $_POST['action'] === 'wpm_register' ) {
        return true;
    }

    // Registration form of eMember plugin
    if (
        $apbct->settings['forms__registrations_test'] &&
        Request::get('emember-form-builder-submit') &&
        wp_verify_nonce(Request::get('_wpnonce'), 'emember-form-builder-nonce')
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
        if ( $check['allow'] == 0 ) {
            return new WP_Error('invalid_email', $check['comment']);
        }
    }

    return $result;
}

/**
 * WS-Forms integration
 */
add_filter('wsf_submit_field_validate', function ($error_validation_action_field, $field_id, $_field_value, $section_repeatable_index, $_post_mode, $_form_submit_class) {

    global $cleantalk_executed;

    if ( $cleantalk_executed ) {
        return $error_validation_action_field;
    }

    /**
     * Filter for POST
     */
    $input_array = apply_filters('apbct__filter_post', $_POST);
    $data = ct_gfa($input_array);

    $sender_email    = ($data['email'] ?  : '');
    $sender_nickname = ($data['nickname'] ?  : '');
    $message         = ($data['message'] ?  : array());

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => array( 'comment_type' => 'WS_forms' ),
            'sender_info'     => array('sender_email' => urlencode($sender_email)),
        )
    );

    if ( $base_call_result['ct_result']->allow == 0 ) {
        return array(
            'action'                   => 'field_invalid_feedback',
            'field_id'                 => $field_id,
            'section_repeatable_index' => $section_repeatable_index,
            'message'                  => $base_call_result['ct_result']->comment
        );
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
            )
        );

        $ct_result = $base_call_result['ct_result'];

        $cleantalk_executed = true;

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

    return $is_valid;
}

function apbct_form_search__add_fields($form_html)
{
    global $apbct;
    if ( is_string($form_html) && $apbct->settings['forms__search_test'] == 1 ) {
        return str_replace('</form>', ct_add_honeypot_field('search_form') . '</form>', $form_html);
    }
    return $form_html;
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
        $data = ct_get_fields_any($_POST, Post::get('email'));

        $base_call_result = apbct_base_call(
            array(
                'message'         => ! empty($data['message']) ? json_encode($data['message']) : '',
                'sender_email'    => ! empty($data['email']) ? $data['email'] : '',
                'sender_nickname' => ! empty($data['nickname']) ? $data['nickname'] : '',
                'post_info'       => array(
                    'comment_type' => 'register_advanced_classifieds_directory_pro'
                ),
            ),
            true
        );

        $ct_result = $base_call_result['ct_result'];

        $cleantalk_executed = true;

        if ( $ct_result->allow == 0 ) {
            $ct_comment = $ct_result->comment;
            ct_die(null, null);
        }
    }

    return $response;
}
