<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;

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
     * Skip request if fields exists
     * @var array
     */
    private $skip_params = array(
        'ipn_track_id',    // PayPal IPN #
        'txn_type',        // PayPal transaction type
        'payment_status',    // PayPal payment status
        'ccbill_ipn',        // CCBill IPN
        'ct_checkjs',        // skip ct_checkjs field
        'api_mode',         // DigiStore-API
        'loadLastCommentId' // Plugin: WP Discuz. ticket_id=5571
    );

    /**
     * Reset $message if we have a sign-up data
     * @var array
     */
    private $skip_message_post = array(
        'edd_action', // Easy Digital Downloads
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
     * Processed data array to output
     * @var array
     */
    private $processed_data = array(
        'email'    => '',
        'nickname' => array(),
        'subject'  => '',
        'contact'  => true,
        'message'  => array()
    );

    /**
     * @var string
     */
    private $preprocessed_email;

    /**
     * @var string
     */
    private $preprocessed_nickname;

    /**
     * @var string
     */
    private $prev_name = '';

    /**
     * GetFieldsAny constructor.
     *
     * @param array $input_array
     */
    public function __construct(array $input_array)
    {
        $this->input_array = $input_array;
        $this->isProcessForm();
        $this->visible_fields_arr = $this->getVisibleFields();
    }

    /**
     * Public interface to process fields
     *
     * @param string $email
     * @param string $nickname
     *
     * @return array
     */
    public function getFields($email = '', $nickname = '')
    {
        $this->preprocessed_email    = $email;
        $this->preprocessed_nickname = is_string($nickname) ? $nickname : '';

        if (count($this->input_array)) {
            $this->process($this->input_array);
        }

        foreach ($this->skip_message_post as $v) {
            if (isset($_POST[$v])) {
                $this->processed_data['message'] = null;
                break;
            }
        }

        if ( ! $this->contact) {
            $this->processed_data['contact'] = $this->contact;
        }

        if ($this->preprocessed_email) {
            $this->processed_data['email'] = $this->preprocessed_email;
        }

        if ($this->preprocessed_nickname) {
            $this->processed_data['nickname'] = $this->preprocessed_nickname;
        }

        if (is_array($this->processed_data['nickname'])) {
            $nickname_str = '';
            foreach ($this->processed_data['nickname'] as $value) {
                $nickname_str .= ($value ? $value . " " : "");
            }
            $this->processed_data['nickname'] = trim($nickname_str);
        }

        return $this->processed_data;
    }

    /**
     * Makes main logic recursively
     *
     * @param $arr
     */
    private function process($arr)
    {
        foreach ($arr as $key => $value) {
            /*
            * PREPARING
            */
            if (is_string($value)) {
                $tmp = strpos($value, '\\') !== false ? stripslashes($value) : $value;

                // Remove html tags from $value
                $tmp = preg_replace('@<.*?>@', '', $tmp);

                // Try parse URL from the string, only single line is applicable
                if (strpos($value, "\n") === false && strpos($value, "\r") === false) {
                    parse_str(urldecode($tmp), $decoded_url_value);
                }

                // Try parse JSON from the string
                $decoded_json_value = json_decode($tmp, true);

                if ($decoded_json_value !== null) {
                    // If there is "JSON data" set is it as a value
                    if (
                        isset($arr['action']) &&
                        $arr['action'] === 'nf_ajax_submit' &&
                        isset($decoded_json_value['settings'])
                    ) {
                            unset($decoded_json_value['settings']);
                    }

                    $value = $decoded_json_value;
                } elseif (
                    isset($decoded_url_value) &&
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
                        $val = array($tmp[0] => $tmp[1]);
                    }
                    unset($val);
                }
            }

            /*
             * STRING CHECK START
             */

            if ( ! is_array($value) && ! is_object($value) ) {
                if (
                    (in_array($key, $this->skip_params, true) && $key !== 0 && $key !== '') ||
                    0 === strpos($key, "ct_checkjs")
                ) {
                    $this->contact = false;
                }

                if ($value === '') {
                    continue;
                }

                // Skipping fields names with strings from (array)skip_fields_with_strings
                foreach ($this->skip_fields_with_strings as $needle) {
                    if (preg_match("/" . $needle . "/", $key) === 1) {
                        continue(2);
                    }
                }

                // Obfuscating params
                foreach ($this->obfuscate_params as $needle) {
                    if (strpos($key, $needle) !== false) {
                        $value = $this->obfuscateParam($value);
                    }
                }

                $value_for_email = trim(
                    $this->stripShortcodes($value)
                );    // Removes shortcodes to do better spam filtration on server side.
                // Removes whitespaces
                $value = urldecode(trim($this->stripShortcodes($value))); // Fully cleaned message

                // Email
                $value_for_email = Validate::isUrlencoded($value_for_email) ? urldecode($value_for_email) : $value_for_email;

                if (
                    preg_match("/^\S+@\S+\.\S+$/", $value_for_email) &&
                    (
                        empty($this->visible_fields_arr) ||
                        in_array($key, $this->visible_fields_arr, true)
                    )
                ) {
                    // Bypass email collecting if it is set by attribute.
                    if ($this->preprocessed_email) {
                        continue;
                    }
                    if (empty($this->processed_data['email'])) {
                        // if found new very first email field, set it as the processed email field.
                        $this->processed_data['email'] = $value_for_email;
                    } else {
                        // if processed one is already exists, set it to the message field.
                        $this->processed_data['message'][$this->prev_name . $key] = $value_for_email;
                    }
                    // Names
                } elseif (false !== stripos($key, "name")) {
                    // Bypass name collecting if it is set by attribute or it is on invisible fields.
                    if (
                        $this->preprocessed_nickname &&
                        (empty($this->visible_fields_arr) ||
                         in_array($key, $this->visible_fields_arr, true))
                    ) {
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
                        $this->processed_data['nickname']['first'] = $value;
                    } elseif (count($match_surname) > 1) {
                        $this->processed_data['nickname']['last'] = $value;
                    } elseif (count($match_nickname) > 1) {
                        $this->processed_data['nickname']['nick'] = $value;
                    } else {
                        $this->processed_data['message'][$this->prev_name . $key] = $value;
                    }
                    // Subject
                } elseif ($this->processed_data['subject'] === '' && false !== stripos($key, "subject")) {
                    $this->processed_data['subject'] = $value;
                    // Message
                } else {
                    $this->processed_data['message'][$this->prev_name . $key] = $value;
                }
            } elseif ( ! is_object($value)) {
                /*
                 * NOT A STRING - PROCEED RECURSIVE
                 */
                if (empty($value)) {
                    continue;
                }

                $prev_name_original = $this->prev_name;
                $this->prev_name    = ($this->prev_name === '' ? $key . '_' : $this->prev_name . $key . '_');

                $this->process($value);

                $this->prev_name = $prev_name_original;
            }
        }
    }

    /**
     * Checking if the form will be skipped checking using global variables
     *
     * @ToDO we have the strong dependence apbct_array() Need to be refactored.
     */
    private function isProcessForm()
    {
        if (apbct_array(array($_POST, $_GET))->getKeys($this->skip_params)->result()) {
            $this->contact = false;
        }
    }

    /**
     * Get visible fields to skip them during processing
     *
     * @return array
     */
    private function getVisibleFields()
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
     * @return array|string[]
     */
    public static function getVisibleFieldsData()
    {
        // get from Cookies::
        $from_cookies = Cookie::getVisibleFields();
        // get from Post:: and base64 decode the value
        $from_post = @base64_decode(Post::get('apbct_visible_fields'));

        $current_fields_collection = self::getFieldsDataForCurrentRequest($from_cookies, $from_post);

        if ( ! empty($current_fields_collection)) {
            // get all available fields to compare with $current_fields_collection
            $post_fields_to_check = self::getAvailablePOSTFieldsForCurrentRequest();
            // comparing
            foreach ($current_fields_collection as $current_fields) {
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
                    //fluent forms chunk
                    if (
                        isset($post_fields_to_check['data'], $post_fields_to_check['action']) &&
                        $post_fields_to_check['action'] === 'fluentform_submit'
                    ) {
                        $fluent_forms_out = array();
                        $fluent_forms_fields = urldecode($post_fields_to_check['data']);
                        parse_str($fluent_forms_fields, $fluent_forms_fields_array);
                        $fields_array = explode(' ', $fields_string);
                        foreach ( $fields_array as $visible_field_slug ) {
                            if ( strpos($visible_field_slug, '[') ) {
                                $vfs_array_like_string = str_replace(array('[', ']'), ' ', $visible_field_slug);
                                $vfs_array = explode(' ', trim($vfs_array_like_string));
                                if (
                                    isset(
                                        $vfs_array[0],
                                        $vfs_array[1]
                                    ) &&
                                    isset(
                                        $fluent_forms_fields_array[$vfs_array[0]],
                                        $fluent_forms_fields_array[$vfs_array[0]][$vfs_array[1]]
                                    )
                                ) {
                                    $fluent_forms_out['visible_fields'][] = $visible_field_slug;
                                }
                            } else {
                                if ( isset($fluent_forms_fields_array[$visible_field_slug]) ) {
                                    $fluent_forms_out['visible_fields'][] = $visible_field_slug;
                                }
                            }
                        }
                        return $fluent_forms_out;
                    }

                    // asset to parse wp forms fields from post
                    if ( isset($post_fields_to_check['wpforms']['fields']) ) {
                        if ( is_array($post_fields_to_check['wpforms']['fields']) ) {
                            foreach ($post_fields_to_check['wpforms']['fields'] as $type => $value) {
                                if ( is_array($value) ) {
                                    foreach ($value as $subtype => $subvalue) {
                                        if ( is_string($subvalue) ) {
                                            $wp_forms_field                        = 'wpforms[fields][' . $type . '][' . $subtype . ']';
                                            $post_fields_to_check[$wp_forms_field] = null;
                                        }
                                    }
                                } else {
                                    $wp_forms_field                        = 'wpforms[fields][' . $type . ']';
                                    $post_fields_to_check[$wp_forms_field] = null;
                                }
                            }
                        }
                        unset($post_fields_to_check['wpforms']);
                    }
                    // parse string to get fields array
                    $fields_array = explode(' ', $fields_string);
                    // if is intersected with current post fields - that`s it
                    if (count(array_intersect(array_keys($post_fields_to_check), $fields_array)) > 0) {
                        // additional WP Forms visible fields formatting
                        return ! empty($current_fields) && is_array($current_fields) ? $current_fields : array();
                    }
                }
            }
        }

        return array();
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
            return strip_shortcodes($value);
        }

        return $value;
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

        // @ToDo we have to implement a logic to find form fields (fields names, fields count) in serialized/nested/encoded items. not only $_POST.
        return $_POST;
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
}
