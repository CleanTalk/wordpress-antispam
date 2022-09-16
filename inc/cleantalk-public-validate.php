<?php

use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;

/**
 * General test for any contact form
 */
function ct_contact_form_validate()
{
    global $apbct, $ct_comment;

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

    if (skip_for_ct_contact_form_validate()) {
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
    if ( defined('LOGGED_IN_COOKIE') && isset($_COOKIE[LOGGED_IN_COOKIE]) && $apbct->settings['data__protect_logged_in'] != 1 ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return null;
    }
    //Skip WP Fusion web hooks
    if ( apbct_is_in_uri('wpf_action') && apbct_is_in_uri('access_key') && isset($_GET['access_key']) ) {
        if ( function_exists('wp_fusion') ) {
            $key = wp_fusion()->settings->get('access_key');
            if ( $key === $_GET['access_key'] ) {
                do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

                return null;
            }
        }
    }
    //Skip system fields for divi
    if ( strpos(Post::get('action'), 'et_pb_contactform_submit') === 0 ) {
        foreach ( array_keys($_POST) as $key ) {
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
        foreach ( array_keys($_POST) as $key ) {
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
    $contact_form    = $ct_temp_msg_data['contact']; // Psalm: Operand of type false is always false
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
        $tmp = Sanitize::cleanTextField(Post::get('TellAFriend_Link'));
        unset($_POST['TellAFriend_Link']);
    }

    $checkjs = apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true) ?: apbct_js_test(Sanitize::cleanTextField(Post::get('ct_checkjs')));

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => $post_info,
            'js_on'           => $checkjs,
            'sender_info'     => array('sender_email' => urlencode($sender_email)),
        )
    );

    if ( isset($_POST['TellAFriend_Link']) ) {
        $_POST['TellAFriend_Link'] = $tmp;
    }

    $ct_result = $base_call_result['ct_result'];

    // Remove visible fields from POST
    foreach ($_POST as $key => $_value) {
        if (stripos($key, 'apbct_visible_fields') === 0) {
            unset($_POST[$key]);
        }
    }

    if ( $ct_result->allow == 0 ) {
        // Recognize contact form an set it's name to $contact_form to use later
        $contact_form = null;
        foreach ( array_keys($_POST) as $param ) {
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
            echo wp_kses(
                $ct_result->comment,
                array(
                    'a' => array(
                        'href'  => true,
                        'title' => true,
                    ),
                    'br'     => array(),
                    'p'     => array()
                )
            );
        } else {
            $ct_comment = $ct_result->comment;
            if ( isset($_POST['cma-action']) && $_POST['cma-action'] == 'add' ) {
                $result = array('success' => 0, 'thread_id' => null, 'messages' => array($ct_result->comment));
                header("Content-Type: application/json");
                print json_encode($result);
                die();
            } elseif ( isset($_POST['TellAFriend_email']) ) {
                echo wp_kses(
                    $ct_result->comment,
                    array(
                        'a' => array(
                            'href'  => true,
                            'title' => true,
                        ),
                        'br'     => array(),
                        'p'     => array()
                    )
                );
                die();
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
                echo wp_kses(
                    "<div id='et_pb_contact_form{$contact_form_additional}'><h1>Your request looks like spam.</h1><div><p>{$ct_result->comment}</p></div></div>",
                    array(
                        'a' => array(
                            'href'  => true,
                            'title' => true,
                        ),
                        'br'     => array(),
                        'p'     => array(),
                        'div' => array(
                            'id' => true,
                        ),
                        'h1' => array(),
                    )
                );
                die();
                // Enfold Theme Contact Form. Using $contact_form
            } elseif ( ! empty($contact_form) && $contact_form == 'contact_form_enfold_theme' ) {
                $echo_string = "<div id='ajaxresponse_1' class='ajaxresponse ajaxresponse_1' style='display: block;'><div id='ajaxresponse_1' class='ajaxresponse ajaxresponse_1'><h3 class='avia-form-success'>Anti-Spam by CleanTalk: " . $ct_result->comment . "</h3><a href='.'><-Back</a></div></div>";
                echo wp_kses(
                    $echo_string,
                    array(
                        'a' => array(
                            'href'  => true,
                            'title' => true,
                        ),
                        'br'     => array(),
                        'p'     => array(),
                        'div' => array(
                            'id' => true,
                            'class' => true,
                            'style' => true
                        ),
                        'h3' => array(),
                    )
                );
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
    global $cleantalk_executed, $ct_comment;

    // Exclusios common function
    if ( apbct_exclusions_check(__FUNCTION__) ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return null;
    }

    if (skip_for_ct_contact_form_validate_postdata()) {
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
    foreach ( $skip_params as $value ) {
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
            $ct_comment = $ct_result->comment;
            if ( isset($_POST['cma-action']) && $_POST['cma-action'] === 'add' ) {
                $result = array('success' => 0, 'thread_id' => null, 'messages' => array($ct_result->comment));
                header("Content-Type: application/json");
                print json_encode($result);
                die();
            } else {
                ct_die(null, null);
            }
        } else {
            echo wp_kses(
                $ct_result->comment,
                array(
                    'a' => array(
                        'href'  => true,
                        'title' => true,
                    ),
                    'br'     => array(),
                    'p'     => array()
                )
            );
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
