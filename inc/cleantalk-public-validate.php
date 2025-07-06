<?php

use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;

/**
 * General test for any contact form
 */
function ct_contact_form_validate()
{
    global $apbct, $ct_comment;

    $do_skip = skip_for_ct_contact_form_validate();
    if ( $do_skip ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__ . ', ON KEY ' . $do_skip, $_POST);
        return null;
    }

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

    // Skip REST API requests
    if ( Server::isPost() && Server::inUri('rest_route') ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return null;
    }

    // Skip own service REST API requests
    if ( Server::isPost() && Server::inUri('cleantalk-antispam/v1/apbct_decode_email') ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return null;
    }

    //Skip woocommerce checkout
    if (
        apbct_is_in_uri('wc-ajax=update_order_review') ||
        apbct_is_in_uri('wc-ajax=checkout') ||
        !empty($_POST['woocommerce_checkout_place_order']) ||
        apbct_is_in_uri('wc-ajax=wc_ppec_start_checkout') ||
        apbct_is_in_referer('wc-ajax=update_order_review') ||
        (
            //if WooCommerce check is disabled, skip Apple Pay service requests. Otherwise, the request will be skipped by $cleantalk_executed.
            $apbct->settings['forms__wc_checkout_test'] != 1 &&
            !empty($_POST['payment_request_type']) &&
            $_POST['payment_request_type'] === 'apple_pay'
        )
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return null;
    }

    //Skip woocommerce add_to_cart
    if ( ! empty($_POST['add-to-cart']) ||
        (isset($_GET['wc-ajax']) && $_GET['wc-ajax'] === 'add_to_cart')
    ) {
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
    if ( strpos(TT::toString(Post::get('action')), 'et_pb_contactform_submit') === 0 ) {
        foreach ( array_keys($_POST) as $key ) {
            if ( strpos((string)$key, 'et_pb_contact_email_fields') === 0 ) {
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
            if ( strpos((string)$key, 'calculatedfields') !== false ) {
                do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

                return null;
            }
        }
    }

    $post_info = [];
    $post_info['comment_type'] = 'feedback_general_contact_form';

    /**
     * Forminator special handler
     */
    if ( isset($_POST['action']) && $_POST['action'] === 'forminator_submit_form_custom-forms' ) {
        foreach ( $_POST as $key => $value ) {
            if ( is_string($key) && strpos($key, 'email') !== false && is_string($value) ) {
                $_POST[$key] = sanitize_email($value);
            }
        }
    }

    /**
     * Filter for POST
     */
    $input_array = apply_filters('apbct__filter_post', $_POST);

    $ct_tmp_email = null;
    foreach ($input_array as $key => $value) {
        if (is_string($key) &&
            strpos($key, 'et_pb_contact_email') !== false &&
            strpos($key, 'et_pb_contact_email_fields') === false &&
            strpos($key, 'et_pb_contact_email_hidden_fields') === false
        ) {
            $ct_tmp_email = preg_replace('/[^a-zA-Z0-9@\.\-_]/', '', $value);
            break;
        }
    }

    /**
     * Get fields any usage
     */

    $ct_temp_msg_data = ct_get_fields_any($input_array, $ct_tmp_email);

    /**
     * Email and emails array preparing
     */

    //prepare email
    $sender_email    = isset($ct_temp_msg_data['email']) ? TT::toString($ct_temp_msg_data['email']) : '';

    //prepare emails array
    $sender_emails_array = isset($ct_temp_msg_data['emails_array']) && is_array($ct_temp_msg_data['emails_array'])
        ? $ct_temp_msg_data['emails_array']
        : array();
    $sender_emails_array = json_encode($sender_emails_array);
    $sender_emails_array = TT::toString($sender_emails_array);

    //prepare email data to sender info
    $sender_info = array(
        'sender_email' => urlencode($sender_email),
        'sender_emails_array' => urlencode($sender_emails_array)
    );

    /**
     * Other sender data collection
     */

    $sender_nickname = (isset($ct_temp_msg_data['nickname']) ? $ct_temp_msg_data['nickname'] : '');
    $subject         = (isset($ct_temp_msg_data['subject']) ? $ct_temp_msg_data['subject'] : '');
    $contact_form    = (isset($ct_temp_msg_data['contact']) ? $ct_temp_msg_data['contact'] : '');
    $message         = (isset($ct_temp_msg_data['message']) ? $ct_temp_msg_data['message'] : array());
    if ( $subject != '' ) {
        $message = array_merge(array('subject' => $subject), $message);
    }

    // Skip submission if "get fields any" decided this is not a contact form
    if ( ! $contact_form ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return false;
    }

    //hivepress theme listing integration
    if ( empty($sender_email) &&
         function_exists('hivepress') &&
         is_callable('hivepress') &&
         apbct_is_user_logged_in() &&
         $apbct->settings['data__protect_logged_in']
    ) {
        if (! isset($_POST['_model'])) {
            $current_user = wp_get_current_user();
            if ( ! empty($current_user->data->user_email) ) {
                $sender_email = $current_user->data->user_email;
            }
        } else {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '(hivepress theme listing integration):' . __LINE__, $_POST);
            return false;
        }
    }

    //tellallfriend integration #1
    if ( isset($_POST['TellAFriend_Link']) ) {
        $tmp = Sanitize::cleanTextField(Post::get('TellAFriend_Link'));
        unset($_POST['TellAFriend_Link']);
    }

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => $post_info,
            'sender_info'     => $sender_info,
        )
    );

    //tellallfriend integration #2
    if ( isset($_POST['TellAFriend_Link'], $tmp) ) {
        $_POST['TellAFriend_Link'] = $tmp;
    }

    if (isset($base_call_result['ct_result'])) {
        $ct_result = $base_call_result['ct_result'];

        // Remove service fields from POST
        apbct_clear_post_service_data_after_base_call();

        if ( $ct_result->allow == 0 ) {
            // Recognize contact form an set it's name to $contact_form to use later
            $contact_form = null;
            foreach ( array_keys($_POST) as $param ) {
                if ( strpos((string)$param, 'et_pb_contactform_submit') === 0 ) {
                    $contact_form            = 'contact_form_divi_theme';
                    $contact_form_additional = str_replace('et_pb_contactform_submit', '', (string)$param);
                }
                if ( strpos((string)$param, 'avia_generated_form') === 0 ) {
                    $contact_form            = 'contact_form_enfold_theme';
                    $contact_form_additional = str_replace('avia_generated_form', '', (string)$param);
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
                } elseif ( ! empty($contact_form) && $contact_form == 'contact_form_divi_theme' && isset($contact_form_additional) ) {
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
                    $echo_template = "<div id='ajaxresponse_1' class='ajaxresponse ajaxresponse_1' style='display: block;'>
                                        <div id='ajaxresponse_1' class='ajaxresponse ajaxresponse_1'>
                                            <h3 class='avia-form-success'>
                                            %s: %s</h3>
                                            <a href='.'><-Back</a>
                                        </div>
                                    </div>";
                    $echo_string = sprintf($echo_template, $apbct->data['wl_brandname'], $ct_result->comment);
                    echo wp_kses(
                        $echo_string,
                        array(
                            'a'   => array(
                                'href'  => true,
                                'title' => true,
                            ),
                            'br'  => array(),
                            'p'   => array(),
                            'div' => array(
                                'id'    => true,
                                'class' => true,
                                'style' => true
                            ),
                            'h3'  => array(),
                        )
                    );
                    die();
                } elseif (
                    (int)$apbct->settings['forms__check_internal'] === 1
                    && !empty($_POST)
                    && apbct_is_ajax()
                    && Post::equal('sib_form_action', 'subscribe_form_submit')
                    && apbct_is_plugin_active('mailin/sendinblue.php')
                ) {
                    wp_send_json(
                        array(
                            'status' => 'failure',
                            'msg' => array(
                                "errorMsg" => wp_kses(
                                    $ct_result->comment,
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
                                )
                            ),
                        )
                    );
                } else if (
                    // BuddyBoss App - request from mobile app usually
                    apbct_is_plugin_active('buddyboss-app/buddyboss-app.php') &&
                    Server::getString('REQUEST_URI') === '/buddyboss-app/v1/signup'
                ) {
                    $data = [
                        'code' => 'bp_rest_register_errors',
                        'message' => [
                            'signup_email' => $ct_result->comment
                        ],
                        'data' => [
                            'status' => 403,
                        ],
                    ];
                    wp_send_json($data);
                } else {
                    ct_die(null, null);
                }
            }
            exit;
        }
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

    $ct_temp_msg_data = ct_get_fields_any($input_array);

    $sender_email    = isset($ct_temp_msg_data['email']) ? $ct_temp_msg_data['email'] : '';
    $sender_emails_array = isset($ct_temp_msg_data['emails_array']) ? $ct_temp_msg_data['emails_array'] : '';
    $sender_nickname = isset($ct_temp_msg_data['nickname']) ? $ct_temp_msg_data['nickname'] : '';
    $subject         = isset($ct_temp_msg_data['subject']) ? $ct_temp_msg_data['subject'] : '';
    $message         = isset($ct_temp_msg_data['message']) ? $ct_temp_msg_data['message'] : array();
    if ( $subject !== '' ) {
        $message = array_merge(array('subject' => $subject), $message);
    }

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
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => array('comment_type' => 'feedback_general_postdata'),
            'sender_info'     => array(
                'sender_emails_array'    => $sender_emails_array,
            ),
        )
    );

    $cleantalk_executed = true;

    if (isset($base_call_result['ct_result'])) {
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
    }

    return null;
}

add_filter('apbct__filter_post', 'apbct__filter_form_data', 10);
add_action('plugins_loaded', 'apbct_clear_bypassed_requests_post_data', 11);

function apbct__filter_form_data($form_data)
{
    global $apbct;

    if (isset($form_data['data']) && is_array($form_data['data'])) {
        $form_data['data'] = apbct__filter_form_data($form_data['data']);
    }

    // It is a service field. Need to be deleted before the processing.
    if ( isset($form_data['ct_bot_detector_event_token']) ) {
        unset($form_data['ct_bot_detector_event_token']);
    }

    //clear no_cookie hidden field if still persists in message
    //todo needs to adapt apbct_check_post_for_no_cookie_data and apbct_filter_post_no_cookie_data to handle this
    if ( isset($form_data['ct_no_cookie_hidden_field']) ) {
        unset($form_data['ct_no_cookie_hidden_field']);
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
 * Checks if the current request should bypass certain processing.
 *
 * This function checks if the current request is related to a specific plugin
 * (in this case, 'uni-woo-custom-product-options/uni-cpo.php') and if a specific
 * POST parameter 'action' is set and comply to an expected by rules. If these conditions are met, the
 * function returns true, indicating that the request should bypass certain processing.
 *
 * @return false|string Returns string of the data container mapping if the request is related to the specific plugin
 * and the POST parameter is set. Otherwise, returns false.
 */
function apbct_get_bypassed_request_data_container()
{
    $result = false;
    $ruleset = [];

    // Define the ruleset
    $ruleset[] = array(
        'plugin_slug' => 'uni-woo-custom-product-options/uni-cpo.php',
        'post_action' => 'uni_cpo_add_to_cart',
        'data_container_name' => 'data'
    );
    $ruleset[] = array(
        'plugin_slug' => 'uni-woo-custom-product-options-premium/uni-cpo.php',
        'post_action' => 'uni_cpo_add_to_cart',
        'data_container_name' => 'data'
    );
    $ruleset[] = array(
        'plugin_slug' => 'uni-woo-custom-product-options-premium/uni-cpo.php',
        'post_action' => 'uni_cpo_order_item_update',
        'data_container_name' => 'post'
    );
    $ruleset[] = array(
        'plugin_slug' => 'uni-woo-custom-product-options-premium/uni-cpo.php',
        'post_signs_obligatory' => array(
            'add-to-cart',
            'cpo_product_id'
        ),
        'data_container_name' => 'post'
    );
    //cpo_product_id

    // Iterate over the ruleset
    foreach ($ruleset as $rule) {
        // If the plugin is active and the POST parameter is set, return true
        if ( apbct_is_plugin_active($rule['plugin_slug']) ) {
            $container_exists = !empty(Post::get($rule['data_container_name'])) || $rule['data_container_name'] === 'post';
            if (isset($rule['post_action']) && Post::get('action') === $rule['post_action'] && $container_exists) {
                $result = $rule['data_container_name'];
            } else {
                if ($container_exists && !empty($rule['post_signs_obligatory']) ) {
                    foreach ($rule['post_signs_obligatory'] as $sign) {
                        if (!Post::get($sign)) {
                            continue(2);
                        }
                    }
                    $result = $rule['data_container_name'];
                }
            }
        }
    }

    // If none of the conditions are met, return false
    return $result;
}

/**
 * This function is responsible for clearing bypassed requests post data.
 * It checks if the $_POST global variable is not empty and if the request is bypassed.
 * If these conditions are met, it removes certain service fields from the $_POST array.
 *
 * @return void
 */
function apbct_clear_bypassed_requests_post_data()
{
    if (empty($_POST)) {
        return;
    }

    // Assigning $_POST global variable to a local variable $post
    $post = $_POST;

    // Get the data container mapping if the request is bypassed
    $data_container = apbct_get_bypassed_request_data_container();

    $container_is_post = $data_container === 'post';
    if ( !empty($data_container) && is_string($data_container) ) {
        $data = $container_is_post ? $post : $post[$data_container];
    }

    // If $post is empty or the request is not bypassed, return without executing the rest of the function
    if ( empty($data) ) {
        return;
    }

    // If the 'ct_bot_detector_event_token' field is set in $post, it is removed.
    if ( isset($data['ct_bot_detector_event_token']) ) {
        unset($data['ct_bot_detector_event_token']);
    }

    // If the 'ct_no_cookie_hidden_field' field is set in $post, it is removed.
    if ( isset($data['ct_no_cookie_hidden_field']) ) {
        unset($data['ct_no_cookie_hidden_field']);
    }

    // If the 'apbct_visible_fields' field is set in $post, it is removed.
    if ( isset($data['apbct_visible_fields']) ) {
        unset($data['apbct_visible_fields']);
    }

    if ($container_is_post) {
        $post = $data;
    } else {
        $post[$data_container] = $data;
    }
    // Assign the cleaned $post back to the $_POST global variable
    $_POST = $post;
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

/**
 * Remove apbct_visible_fields, ct_bot_detector_event_token, ct_no_cookie_hidden_field fields from POST array.
 * @return void
 */
function apbct_clear_post_service_data_after_base_call()
{
    // Remove visible fields from POST
    foreach ($_POST as $key => $_value) {
        if (stripos((string)$key, 'apbct_visible_fields') === 0) {
            unset($_POST[$key]);
        }
        if (stripos((string)$key, 'ct_bot_detector_event_token') === 0) {
            unset($_POST[$key]);
        }
        if (stripos((string)$key, 'ct_no_cookie_hidden_field') === 0) {
            unset($_POST[$key]);
        }
    }
}
