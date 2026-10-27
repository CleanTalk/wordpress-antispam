<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\DTO\GetFieldsAnyDTO;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Request;
use Cleantalk\Common\TT;

class GetFieldsAny
{
    /**
     * @var array
     */
    private $input_array;

    /**
     * Skip fields with these strings and known service fields
     * @var array
     */
    private $skip_fields_with_strings = array(
        // Common
        'ct_checkjs', //Do not send ct_checkjs
        'nonce', //nonce for strings such as 'rsvp_nonce_name'
        'security',
        // 'action',
        'http_referer',
        'referer-page',
        'timestamp',
        'captcha',
        // Formidable Form
        'form_key',
        'submit_entry',
        // Custom Contact Forms
        'form_id',
        'ccf_form',
        'form_page',
        // Qu Forms
        'iphorm_uid',
        'form_url',
        'post_id',
        'iphorm_ajax',
        'iphorm_id',
        // Fast SecureContact Froms
        'fs_postonce_1',
        'fscf_submitted',
        'mailto_id',
        'si_contact_action',
        // Ninja Forms
        'formData_id',
        'formData_settings',
        'formData_fields_\d+_id',
        'formData_fields_\d+_files.*',
        // E_signature
        'recipient_signature',
        'output_\d+_\w{0,2}',
        // Contact Form by Web-Settler protection
        '_formId',
        '_returnLink',
        // Social login and more
        '_save',
        '_facebook',
        '_social',
        'user_login-',
        // Contact Form 7
        '_wpcf7',
        'ebd_settings',
        'ebd_downloads_',
        'ecole_origine',
        'signature',
        // Ultimate Form Builder
        'form_data_%d_name',
    );

    /**
     * Skip request if fields exists on the PayPal IPN listener
     * @var array
     */
    private $skip_params = array(
        'ipn_track_id',    // PayPal IPN #
        'txn_type',        // PayPal transaction type
        'payment_status',    // PayPal payment status
    );

    /**
     * PayPal IPN fields with emails, PayPal own fields and the plugins fields PayPal sends back
     * @var array
     */
    private $paypal_ipn_email_fields = array(
        'payer_email',
        'receiver_email',
        'business',
        'option_selection1',   // ARMember, BookingPress: os0 is the customer email
        'custom',              // Leaky Paywall: the customer email
        'transaction_subject', // PayPal repeats custom here
    );

    /**
     * PayPal IPN listeners: the request parameter => array(its value => the constant or the class of the plugin)
     * @var array
     */
    private $paypal_ipn_listeners = array(
        // WooCommerce, WooCommerce PayPal Checkout Gateway
        'wc-api'                        => array(
            'WC_Gateway_Paypal' => 'WooCommerce',
            'WC_Gateway_PPEC'   => 'WC_GATEWAY_PPEC_VERSION',
        ),
        // Payment Gateway for PayPal on WooCommerce
        'wpg_ipn_action'                => array('ipn' => 'WPG_PLUGIN_VERSION'),
        // Easy Digital Downloads
        'edd-listener'                  => array('IPN' => 'EDD_PLUGIN_FILE'),
        // GiveWP
        'give-listener'                 => array(
            'IPN'          => 'Give',
            'give-gateway' => 'Give',
        ),
        // ARMember
        'arm-listener'                  => array(
            'arm_paypal_api'    => 'MEMBERSHIPLITE_DIR_NAME',
            'arm_paypal_notify' => 'MEMBERSHIPLITE_DIR_NAME',
        ),
        // BookingPress
        'bookingpress-listener'         => array('bpa_paypal_url' => 'BOOKINGPRESS_DIR_NAME'),
        // Charitable
        'charitable-listener'           => array('paypal' => 'Charitable'),
        // Classified Listing
        'rtcl-api'                      => array('rtcl_gateway_paypal' => 'RTCL_VERSION'),
        // Leaky Paywall
        'listener'                      => array('IPN' => 'LEAKY_PAYWALL_VERSION'),
        // Masteriyo
        'masteriyo-api'                 => array('gateway_paypal' => 'MASTERIYO_VERSION'),
        // MasterStudy LMS
        'stm_lms_check_ipn'             => array('1' => 'MS_LMS_VERSION'),
        // MotoPress Hotel Booking
        'mphb-listener'                 => array('paypal-ipn' => 'HotelBookingPlugin'),
        // myCred buyCred
        'mycred_call'                   => array('paypal-standard' => 'myCRED_buyCRED_Module'),
        // Newsletters
        'wpmlmethod'                    => array('paypal' => 'wpMail'),
        // Paid Member Subscriptions
        'pay_gate_listener'             => array('paypal_ipn' => 'Paid_Member_Subscriptions'),
        // s2Member
        's2member_paypal_notify'        => array('1' => 'WS_PLUGIN__S2MEMBER_VERSION'),
        // Simple Membership
        'swpm_process_ipn'              => array('1' => 'SIMPLE_WP_MEMBERSHIP_VER'),
        // User Registration Membership
        'ur-membership-listener'        => array('IPN' => 'UR_MEMBERSHIP_PLUGIN_FILE'),
        // WCFM Membership
        'wcfmvm_process_ipn'            => array('paypal_ipn' => 'WCFMvm'),
        // WP Hotel Booking
        'hotel-booking-paypal-standard' => array('1' => 'WP_Hotel_Booking'),
    );

    /**
     * PayPal IPN listeners which are the rewrite endpoints too: the request parameter => true if on the site root only
     * @var array
     */
    private $paypal_ipn_endpoints = array(
        'wc-api'              => false,
        'rtcl-api'            => false,
        'masteriyo-api'       => false,
        'charitable-listener' => true,
    );

    /**
     * Fields to replace with ****
     * @var array
     */
    private $obfuscate_params = array(
        'password',
        'pass',
        'pwd',
        'pswd'
    );

    /**
     * If the form checking will be processing
     * @var bool
     */
    private $contact = true;

    /**
     * @var array
     */
    private $visible_fields_arr;

    /**
     * @var string
     */
    private $preprocessed_email;

    /**
     * @var string
     */
    private $preprocessed_nickname;

    /**
     * @var array
     */
    private $preprocessed_emails_array;

    /**
     * @var string
     */
    private $prev_name = '';

    /**
     * @var GetFieldsAnyDTO
     */
    private $dto;

    /**
     * GetFieldsAny constructor.
     *
     * @param array $input_array
     */
    public function __construct(array $input_array)
    {
        $this->setDTODefaults();
        $this->input_array = $input_array;
        $this->isProcessForm();
        $this->visible_fields_arr = $this->getVisibleFields();
    }

    private function setDTODefaults()
    {
        $dto_default_data  = array(
            'email' => '',
            'emails_array' => array(),
            'nickname' => '',
            'nickname_first' => '',
            'nickname_last' => '',
            'nickname_nick' => '',
            'subject' => '',
            'contact' => true,
            'message' => array(),
        );
        $this->dto = new GetFieldsAnyDTO($dto_default_data);
    }

    /**
     * Public interface to process fields in DTO format
     * @param string $email
     * @param string $nickname
     * @param array $emails_array
     * @return GetFieldsAnyDTO
     */
    public function getFieldsDTO($email = '', $nickname = '', $emails_array = array())
    {
        $this->prepareFields($email, $nickname, $emails_array);
        return $this->dto;
    }

    /**
     * Public interface to process fields. Collects DTO then return array of DTO attributes as array.
     *
     * @param string $email
     * @param string $nickname
     * @param array $emails_array
     * @return array of attributes of GetFieldsAnyDTO
     * @see GetFieldsAnyDTO
     */
    public function getFields($email = '', $nickname = '', $emails_array = array())
    {
        $this->prepareFields($email, $nickname, $emails_array);
        return $this->dto->getArray();
    }

    /**
     * @param string $email incoming email from outer source
     * @param string $nickname incoming nickname from outer source
     * @param array $emails_array incoming emails_array  from outer source
     *
     * @return void
     */
    public function prepareFields($email, $nickname, $emails_array)
    {
        $this->preprocessed_email    = $email;
        $this->preprocessed_nickname = is_string($nickname) ? $nickname : '';

        if (!empty($emails_array) && is_array($emails_array)) {
            $filtered_emails_array = array_map(function ($value) {
                $value = TT::toString($value);
                return Validate::isEmail($value) ? $value : 'invalid_preprocessed_email';
            }, $emails_array);
            $this->preprocessed_emails_array = $filtered_emails_array;
        }

        // main gathering logic, recursive
        if (count($this->input_array)) {
            $this->processRecursive($this->input_array);
        }

        if ( ! $this->contact) {
            $this->dto->contact = $this->contact;
        }

        // Use the first found email if no email field found
        if (empty($this->dto->email) && ! empty($this->dto->emails_array)) {
            $this->dto->email = reset($this->dto->emails_array);
        }

        if ($this->preprocessed_email) {
            $this->dto->email = $this->preprocessed_email;
        }

        if ($this->preprocessed_nickname) {
            $this->dto->nickname = $this->preprocessed_nickname;
        }

        if (!empty($this->preprocessed_emails_array)) {
            foreach ($this->preprocessed_emails_array as $value) {
                $this->dto->emails_array[$value] = $value;
            }
        }
        $this->dto->emails_array = array_values($this->dto->emails_array);

        if (empty($this->dto->nickname)) {
            $name_chunks = array(
                'first' => $this->dto->nickname_first,
                'last'  => $this->dto->nickname_last,
                'nick'  => $this->dto->nickname_nick
            );
            foreach ($name_chunks as $value) {
                $this->dto->nickname .= ($value ? $value . " " : "");
            }
            $this->dto->nickname = trim($this->dto->nickname, " \n\r\t\v\x00");
        }
    }

    /**
     * Makes main logic recursively
     *
     * @param $arr
     */
    private function processRecursive($arr)
    {
        foreach ($arr as $key => $value) {
            // Skip the visible fields service field
            if ($key === 'apbct_visible_fields') {
                continue;
            }

            // Do not collect emails from the password fields
            $is_password = $this->prev_name === '' && $this->isObfuscateParam($key);

            /*
            * PREPARING
            */
            if (is_string($value) && ! $is_password) {
                $decoded_url_value = array();
                $decoded_json_value = null;
                $is_single_line = strpos($value, "\n") === false && strpos($value, "\r") === false;

                // Collect emails from all the query string pairs, parse_str() may not read all of them
                if (strpos($value, '=') !== false) {
                    $this->collectEmailsFromQueryString($value);
                }

                // Parse the value only if it is not a plain email
                // Take first 10 levels of the nested data.
                $value_for_email = $this->prepareEmail($value);
                $is_email = self::isEmailLike($value_for_email);
                if ( $is_email ) {
                    $this->dto->emails_array[$value_for_email] = $value_for_email;
                }
                if (
                    substr_count($this->prev_name, '[') < 10 &&
                    (
                        ! $is_email ||
                        strpos((string)strrchr('&' . $value_for_email, '&'), '=') !== false
                    )
                ) {
                    // Try parse URL from the string, only single line is applicable
                    if (
                        $is_single_line &&
                        strpos($value, '=') !== false &&
                        strpos($value, "\0") === false &&
                        substr_count($value, '&') < (int)ini_get('max_input_vars')
                    ) {
                        parse_str($value, $decoded_url_value);
                    }

                    // Try parse JSON from the string
                    $decoded_json_value = json_decode(trim($value, " \n\r\t\v\x00"), true);
                    if ($decoded_json_value === null && strpos($value, '\\') !== false) {
                        $decoded_json_value = json_decode(stripslashes(trim($value, " \n\r\t\v\x00")), true);
                    }
                }

                if ($decoded_json_value !== null) {
                    // If there is "JSON data" set is it as a value
                    if (
                        isset($arr['action']) &&
                        $arr['action'] === 'nf_ajax_submit' &&
                        isset($decoded_json_value['settings'])
                    ) {
                        unset($decoded_json_value['settings']);
                    }

                    // Collect emails from all the JSON strings, the keys and the values of the same keys too
                    foreach (explode('"', $value) as $json_string) {
                        // Decode escaped symbols of the string
                        $json_string_decoded = strpos($json_string, '\\') !== false ? json_decode('"' . $json_string . '"') : null;
                        $json_string = is_string($json_string_decoded) ? $json_string_decoded : $json_string;
                        if (strpos($json_string, '@') !== false) {
                            $json_string = $this->prepareEmail($json_string);
                            if (self::isEmailLike($json_string)) {
                                $this->dto->emails_array[$json_string] = $json_string;
                            }
                        }
                    }

                    $value = $decoded_json_value;
                } elseif (
                    ! empty($decoded_url_value) &&
                    ! (count($decoded_url_value) === 1 &&
                       reset($decoded_url_value) === '')
                ) {
                    // If there is "URL data" set is it as a value
                    $value = $decoded_url_value;
                } elseif (preg_match('/^\S+\s%%\s\S+.+$/', $value)) {
                    // Ajax Contact Forms. Get data from such strings:
                    // acfw30_name %% Blocked~acfw30_email %% s@cleantalk.org
                    // acfw30_textarea %% msg
                    $value = explode('~', $value);
                    foreach ($value as &$val) {
                        $tmp = explode(' %% ', $val);
                        $val = array($tmp[0] => TT::getArrayValueAsString($tmp, 1));
                    }
                    unset($val);
                }
            }

            /*
             * STRING CHECK START
             */

            if ( ! is_array($value) && ! is_object($value) ) {
                // If $this->prev_name is not empty it is a recursion. So wrap the key with the '[]' brackets
                $nested_array_key = $this->prev_name === '' ? $key : $this->prev_name . '[' . $key . ']';

                // The comment form is checked by the comments check
                if ( 0 === strpos($key, "ct_checkjs") && apbct_is_script_request('wp-comments-post.php') ) {
                    //todo We do need to refactor ct_checkjs check to be sure, that excluded request is not fake!
                    $this->contact = $this->skipExclusionsOnVulnerableFormsData($arr);
                }

                if ($value === '') {
                    continue;
                }

                // Collect email before the fields names checks
                $value_for_email = $this->prepareEmail(TT::toString($value));
                $is_email = ! $is_password && self::isEmailLike($value_for_email);
                if ( $is_email ) {
                    $this->dto->emails_array[$value_for_email] = $value_for_email;
                    if ( empty($this->dto->email) && ! $this->preprocessed_email ) {
                        // if found new very first email field, set it as the processed email field.
                        $this->dto->email = $value_for_email;
                    }
                }

                // Skipping fields names with strings from (array)skip_fields_with_strings
                foreach ($this->skip_fields_with_strings as $needle) {
                    if (preg_match("/" . $needle . "/", $key) === 1) {
                        continue(2);
                    }
                }

                // Obfuscating params
                if ($this->isObfuscateParam($key)) {
                    $value = $this->obfuscateParam($value);
                }

                // Removes whitespaces
                $value = urldecode(trim($this->stripShortcodes($value), " \n\r\t\v\x00")); // Fully cleaned message

                // Email
                if ( $is_email ) {
                    if (
                        ($value_for_email !== $this->dto->email && $value_for_email !== $this->preprocessed_email) ||
                        // Keep lists like "John|Doe|email" in the message too
                        strpbrk($value_for_email, '|^~') !== false
                    ) {
                        // if processed one is already exists, set it to the message field.
                        $this->dto->message[$nested_array_key] = $value;
                    }
                    // Names
                } elseif (false !== stripos($key, "name")) {
                    // Bypass name collecting if it is set by attribute or it is on invisible fields.
                    if ( $this->preprocessed_nickname ) {
                        continue;
                    }
                    preg_match("/(name.?(your|first|for)|(your|first|for).?name)/", $key, $match_forename);
                    preg_match(
                        "/(name.?(last|family|second|sur)|(last|family|second|sur).?name)/",
                        $key,
                        $match_surname
                    );
                    preg_match("/(name.?(nick|user)|(nick|user).?name)/", $key, $match_nickname);

                    if (count($match_forename) > 1) {
                        $this->dto->nickname_first = $value;
                    } elseif (count($match_surname) > 1) {
                        $this->dto->nickname_last = $value;
                    } elseif (count($match_nickname) > 1) {
                        $this->dto->nickname_nick = $value;
                    } else {
                        $this->dto->message[$nested_array_key] = $value;
                    }
                    // Subject
                } elseif ($this->dto->subject === '' && false !== stripos($key, "subject")) {
                    $this->dto->subject = $value;
                    // Message
                } else {
                    $this->dto->message[$nested_array_key] = $value;
                }
            } elseif ( ! is_object($value) ) {
                /*
                 * NOT A STRING - PROCEED RECURSIVE
                 */
                if (empty($value)) {
                    continue;
                }

                $prev_name_original = $this->prev_name;
                $this->prev_name    = ($this->prev_name === '' ? $key : $this->prev_name . '[' . $key . ']');

                $this->processRecursive($value);

                $this->prev_name = $prev_name_original;
            }
        }
    }

    /**
     * Checking if the form will be skipped checking using global variables
     */
    private function isProcessForm()
    {
        foreach ($this->skip_params as $param) {
            if (Post::getString($param) === '') {
                continue;
            }
            // PayPal sends a flat POST with emails in the PayPal fields only
            if (
                count($_POST) === count($_POST, COUNT_RECURSIVE) &&
                ! apbct_is_email_in_post($this->paypal_ipn_email_fields) &&
                $this->isPaypalIpnListener()
            ) {
                $this->contact = false;
            }
            break;
        }

        // Plugin: WP Discuz. ticket_id=5571
        if (
            Post::getString('loadLastCommentId') !== '' &&
            wp_doing_ajax() &&
            Request::getString('action') === 'wpdUpdateAutomatically'
        ) {
            $this->contact = false;
        }
    }

    /**
     * Check if the request is sent to the PayPal IPN listener
     *
     * @return bool
     */
    private function isPaypalIpnListener()
    {
        // Plugin: Paid Memberships Pro
        if (defined('PMPRO_VERSION') && wp_doing_ajax() && Request::getString('action') === 'ipnhandler') {
            return true;
        }

        $path = apbct_get_request_path() . '/';

        foreach ($this->paypal_ipn_listeners as $param => $listeners) {
            foreach ($listeners as $value => $plugin) {
                $value = TT::toString($value);

                // The plugin is active
                if ( ! defined($plugin) && ! class_exists($plugin, false) ) {
                    continue;
                }

                if ( Get::getString($param) === $value ) {
                    return true;
                }

                // Pretty permalinks, the listener is the whole path segments
                if ( isset($this->paypal_ipn_endpoints[$param]) ) {
                    $position = strpos($path, '/' . $param . '/' . $value . '/');
                    if ( $position === false ) {
                        continue;
                    }
                    if ( ! $this->paypal_ipn_endpoints[$param] ) {
                        return true;
                    }
                    // The endpoint on the site root
                    $home_path = rtrim(TT::toString(wp_parse_url(home_url(), PHP_URL_PATH)), '/');
                    if ( in_array(substr($path, 0, $position), array($home_path, $home_path . '/index.php'), true) ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if the field value is masked
     *
     * @param string|int $key
     *
     * @return bool
     */
    private function isObfuscateParam($key)
    {
        foreach ($this->obfuscate_params as $needle) {
            if (strpos((string)$key, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the form is possible vulnerable for direct calls.
     *
     * @param array $form_data
     *
     * @return bool If do skip exclusions on vulnerable forms data.
     */
    private function skipExclusionsOnVulnerableFormsData($form_data)
    {
        // modification to correctly check gravity forms attacks
        if ( isset($form_data) && is_array($form_data)) {
            if (! empty($form_data['action']) &&
                (
                    ! empty($form_data['gform_ajax']) ||
                    ! empty($form_data['gform_submit'])
                )
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get visible fields to skip them during processing
     *
     * @return array
     */
    private function getVisibleFields(): array
    {
        // Visible fields processing
        $visible_fields = self::getVisibleFieldsData();

        return isset($visible_fields['visible_fields']) &&
            is_string($visible_fields['visible_fields']) ? explode(' ', $visible_fields['visible_fields']) : array();
    }

    /**
     * Returns array of visible fields data in format:
     * <ul>
     * <li>'visible_fields' => (string)'user_login user_email'</li>
     * <li>'visible_fields_count' => (int)2</li>
     * <li>'invisible_fields' => (string)'inv_field1 inv_field2 inv_field3'</li>
     * <li>'invisible_fields_count' => (int)3</li>
     * </ul>
     * Empty array if nothing found.
     *
     * @param array $custom_input_array array of custom form data
     * @param bool $skip_comparing Do skip comparing provided request fields with present in apbct_visible_fields collection. Useful for integrations.
     * @return array|string[]
     */
    public static function getVisibleFieldsData(array $custom_input_array = [], bool $skip_comparing = false): array
    {
        $skip_comparing = !empty($custom_input_array) && $skip_comparing;
        // get from Cookies::
        $from_cookies = Cookie::getVisibleFields();
        // get from Post:: and base64 decode the value
        $global_post_source = Post::getString('apbct_visible_fields');
        // get from custom input array
        $input_array_source = TT::getArrayValueAsString($custom_input_array, 'apbct_visible_fields');

        $from_post = !empty($input_array_source) ? $input_array_source : $global_post_source;
        $from_post = @base64_decode($from_post);

        $current_fields_collection = self::getFieldsDataForCurrentRequest($from_cookies, $from_post);

        $result = array();

        if ( ! empty($current_fields_collection)) {
            // get all available fields to compare with $current_fields_collection
            $post_fields_to_check = $custom_input_array ?: self::getAvailablePOSTFieldsForCurrentRequest();
            // comparing
            foreach ($current_fields_collection as $current_fields) {
                // todo gull: can not find any logic for invisible fields or inputs, so far as for visible inputs
                // prepare data
                $count = isset($current_fields['visible_fields_count']) && is_scalar(
                    $current_fields['visible_fields_count']
                )
                    ? (int)($current_fields['visible_fields_count'])
                    : null;
                $fields_string = isset($current_fields['visible_fields']) && is_scalar(
                    $current_fields['visible_fields']
                )
                    ? (string)($current_fields['visible_fields'])
                    : null;

                // if necessary data is available
                if ( isset($fields_string, $count) ) {
                    // parse string to get fields array
                    $fields_array = explode(' ', $fields_string);
                    // if is intersected with current post fields - that's it
                    if ($skip_comparing || count(array_intersect(array_keys($post_fields_to_check), $fields_array)) > 0) {
                        $result = ! empty($current_fields) && is_array($current_fields) ? $current_fields : array();
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Masks a value with asterisks (*)
     *
     * @param null|string $value
     *
     * @return string|null
     */
    private function obfuscateParam($value = null)
    {
        if ($value) {
            $length = strlen($value);
            $value  = str_repeat('*', $length);
        }

        return $value;
    }

    /**
     * WP function placeholder
     *
     * @param $value
     *
     * @return string
     */
    private function stripShortcodes($value)
    {
        if (function_exists('strip_shortcodes')) {
            return strip_shortcodes(TT::toString($value));
        }

        return $value;
    }

    /**
     * Prepares the value to check it as an email
     *
     * @param string $value
     *
     * @return string
     */
    private function prepareEmail($value)
    {
        $value = trim($value, " \n\r\t\v\x00");

        // Emails have no slashes
        if (strpos($value, '\\') !== false) {
            $value = stripslashes($value);
        }

        // Trim unicode whitespaces of short values
        if (strlen($value) < 255) {
            $value = (string)preg_replace('/^[\s\p{Z}\x{FEFF}]+|(?<![\s\p{Z}\x{FEFF}])[\s\p{Z}\x{FEFF}]+$/u', '', $value) ?: $value;
        }

        return Validate::isUrlencoded($value) ? urldecode($value) : $value;
    }

    /**
     * Collects emails from all the pairs of the query string
     *
     * @param string $query_string
     *
     * @return void
     */
    private function collectEmailsFromQueryString($query_string)
    {
        foreach (explode('&', $query_string) as $pair) {
            $decoded_pair = urldecode($pair);
            if (strpos($decoded_pair, '@') === false && stripos($decoded_pair, '%40') === false) {
                continue;
            }

            // Decode the value after or before splitting the pair
            $pair_value = explode('=', $pair, 2);
            $decoded_pair_value = explode('=', $decoded_pair, 2);
            $values = array(
                isset($pair_value[1]) ? urldecode($pair_value[1]) : '',
                isset($decoded_pair_value[1]) ? $decoded_pair_value[1] : '',
            );

            foreach ($values as $value) {
                $value = $this->prepareEmail($value);
                if (self::isEmailLike($value)) {
                    $this->dto->emails_array[$value] = $value;
                }
            }
        }
    }

    /**
     * Get fields from POST to checking on visible fields.
     *
     * @return array
     */
    private static function getAvailablePOSTFieldsForCurrentRequest()
    {
        //Formidable fields
        if ( isset($_POST['item_meta']) && is_array($_POST['item_meta']) ) {
            $fields = array();
            foreach ( $_POST['item_meta'] as $key => $item ) {
                $fields[ 'item_meta[' . $key . ']' ] = $item;
            }

            return $fields;
        }

        // Foreach by $_POST and convert nested array to the inline variable like variable[nested_variable][nested_nested_variable]
        $fields = static::convertNestedArrayToString($_POST);

        // @ToDo we have to implement a logic to find form fields (fields names, fields count) in serialized/nested/encoded items. not only $_POST.
        return $fields;
    }

    /**
     * Converts a nested array to a string representation with keys wrapped in brackets.
     *
     * This function recursively processes a nested array and converts it into a flat array
     * where each key is a string representing the path to the value in the original nested array.
     *
     * @param array $array The nested array to be converted.
     * @param string $prefix The prefix to be used for the keys in the resulting array.
     * @return array The resulting flat array with string keys.
     */
    public static function convertNestedArrayToString($array, $prefix = '')
    {
        $result = [];
        foreach ($array as $key => $value) {
            $new_key = $prefix === '' ? $key : $prefix . '[' . $key . ']';
            if (is_array($value)) {
                $result = array_merge($result, static::convertNestedArrayToString($value, $new_key));
            } else {
                $result[$new_key] = $value;
            }
        }
        return $result;
    }

    /**
     * Filter data to find current request visible fields.
     * @param array $from_cookies array of visible fields JSONs from cookies of any types
     * @param string $from_post raw current POST string of apbct_visible_fields
     *
     * @return array
     */
    private static function getFieldsDataForCurrentRequest($from_cookies = array(), $from_post = '')
    {
        $all_collections           = array();
        $current_fields_collection = array();

        if ( empty($from_cookies) || (empty($from_cookies[0])) ) {
            //if no available cookie data, parse Post::
            if ( ! empty($from_post) ) {
                //post is JSONified array, try to decode it
                $from_post_array = @json_decode($from_post, true);
                // awaited data in first element for Post::
                if ( ! empty($from_post_array) && ! empty($from_post_array[0]) ) {
                    // encode first element and add to collections
                    $all_collections[] = json_encode($from_post_array[0]);
                }
            }
        } else {
            $all_collections = $from_cookies;
        }

        //Native cookies send whole array of available vsf, so we need to handle all of them
        if ( ! empty($all_collections) ) {
            foreach ($all_collections as $_collection => $fields_string) {
                // check if collection is string of JSON
                if ( is_string($fields_string) ) {
                    $fields_array_from_json = @json_decode($fields_string);
                    // try to url decode if JSON can't parse
                    if ( empty($fields_array_from_json) || ! is_array($fields_array_from_json) ) {
                        // if JSON failed, try to url decode initial value before json_decode
                        $fields_array_from_json = @json_decode(urldecode($fields_string), true);
                        if ( empty($fields_array_from_json) || ! is_array($fields_array_from_json) ) {
                            // if still fails, return empty array
                            $fields_array_from_json = array();
                        }
                        // fill current fields collection for this request
                        $current_fields_collection[] = $fields_array_from_json;
                    }
                }
            }
        }

        return $current_fields_collection;
    }

    /**
     * Validates if the given string is in a valid CleanTalk email format.
     *
     * This not a full RFC email validation!
     *
     * @param string $value_for_email The string to be tested as an email address.
     * @return bool Returns true if the string matches the email format, otherwise false.
     * @see testGFAEmailRegex
     */
    public static function isEmailLike($value_for_email)
    {
        //todo This regex should be reinspected with moderate and management team. https://app.doboard.com/1/task/47742
        // Local part symbols are the same as is_email() allows
        return (bool)preg_match("/^[a-zA-Z0-9!#$%&'*+\/=?^_`{|}~.-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/", $value_for_email);
    }
}
