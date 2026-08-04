<?php

namespace Cleantalk\Antispam\IntegrationsByClass;

use Cleantalk\Antispam\CleantalkResponse;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;
use WP_Error;

class WPRegistrationErrors
{
    /**
     * @var bool
     */
    public $is_facebook = false;
    /**
     * @var bool
     */
    public $is_buddypress = false;
    /**
     * @var null|object
     */
    public $bp_object = null;
    /**
     * @var State
     */
    public $apbct;
    /**
     * @var bool
     */
    public $reg_flag = true;

    public function __construct()
    {
        global $apbct;
        $this->apbct = $apbct;
    }

    /**
     * Test users registration
     *
     * @param      mixed|WP_Error $errors
     * @param null|mixed $sanitized_user_login
     * @param null|mixed $user_email
     *
     * @return void|WP_Error
     * @psalm-suppress UnusedVariable
     */
    public function handle($errors, $sanitized_user_login = null, $user_email = null)
    {
        $this->bp_object = $this->defineBuddyPressObject();
        //BUDDYPRESS
        $this->is_buddypress = $this->probablyBuddyPressCredentials($sanitized_user_login, $user_email);
        //FACEBOOK
        $this->is_facebook = $this->probablyFacebookCredentials($sanitized_user_login, $user_email);
        //WOOCOMMERCE
        $this->probablyWooCommerceCredentials($sanitized_user_login);
        //TUTOR LMS PLUGIN
        $this->probablyTutorLMSCredentials($sanitized_user_login, $user_email);

        //Skipping rules
        $on_skip_errors = $this->doSkipRequest($errors);
        if (false !== $on_skip_errors) {
            return $on_skip_errors;
        }

        //Gathering CheckJS
        $check_js_data = $this->getCheckJSData();
        $checkjs_post = $check_js_data['checkjs_post'] ?? null;
        $checkjs_cookie = $check_js_data['checkjs_cookie'] ?? null;
        $checkjs = $check_js_data['checkjs'] ?? null;
        $sender_info = array(
            'post_checkjs_passed'   => $checkjs_post,
            'cookie_checkjs_passed' => $checkjs_cookie,
            'form_validation'       => $errors instanceof WP_Error
                ? json_encode(
                    array(
                        'validation_notice' => $errors->get_error_message(),
                        'page_url'          => Server::getString('HTTP_HOST') . Server::getString('REQUEST_URI'),
                    )
                )
                : null,
        );

        //Gathering basecall data
        $base_call_array = array(
            'sender_email'    => $user_email,
            'sender_nickname' => $sanitized_user_login,
            'sender_info'     => $sender_info,
            'js_on'           => $checkjs,
        );

        //force reg flag states
        $this->reg_flag = $this->forceRegFlagCases($this->reg_flag);
        if ( !$this->reg_flag ) {
            $base_call_array['message'] = $this->defineMessageForNonRegistrationMethod();
        }

        //do base call
        $base_call_result = $this->doBaseCall(
            $base_call_array,
            $this->reg_flag
        );

        $ct_result_object = $base_call_result['ct_result'] ?? null;

        if (!$ct_result_object) {
            return $errors;
        }

        $this->doServiceActions($ct_result_object, $user_email);

        if ( $ct_result_object->inactive != 0 ) {
            ct_send_error_notice(TT::toString($ct_result_object->comment));
            return $errors;
        }

        if ( $ct_result_object->allow != 0 ) {
            $this->onAllowActions($ct_result_object);
        } else {
            $errors = $this->onDenyActions($ct_result_object, $errors);
        }

        return $errors;
    }

    /**
     * @param $errors
     * @return false|WP_Error
     */
    public function doSkipRequest($errors)
    {
        global $ct_signup_done, $ct_negative_comment;

        if ($this->isUserEnabled()) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '(): USER_IS_LOGGED_IN on line ' . __LINE__, $_POST);
            return $errors;
        }

        if ($this->isRegistrationCheckDisabled()) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '(): REGISTRATION_CHECK_DISABLED on line ' . __LINE__, $_POST);
            return $errors;
        }

        if ($this->isSignupHandled($errors, $ct_signup_done)) {
            if (!($errors instanceof WP_Error)) {
                $errors = new WP_Error('registration_error', 'An unexpected error occurred.');
            }
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '(): SIGNUP_ALREADY_HANDLED on line ' . __LINE__, $_POST);
            return $errors;
        }

        if ($this->isWPMembersExclusion()) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '(): WP_MEMBER_EXCLUSION_FOUND on line ' . __LINE__, $_POST);
            return $errors;
        }

        if ($this->isBuddyBossLoginForm()) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '(): BUDDY_BOSS_EXCLUSION_FOUND on line ' . __LINE__, $_POST);
            return $errors;
        }

        if ($this->isBuddyPressAlreadyHasValidationErrors($ct_signup_done, $ct_negative_comment)) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '(): BUDDY_PRESS_VALIDATION_ERRORS_FOUND on line ' . __LINE__, $_POST);
            return $errors;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isUserEnabled()
    {
        return apbct_is_user_enable() === false;
    }

    /**
     * @return bool
     */
    public function isRegistrationCheckDisabled()
    {
        return $this->apbct->settings['forms__registrations_test'] == 0;
    }

    /**
     * @return bool
     */
    public function isSignupHandled($errors, $ct_signup_done)
    {
        return $ct_signup_done && is_object($errors) && count($errors->errors) > 0;
    }

    /**
     * @return bool
     */
    public function isWPMembersExclusion()
    {
        return Post::get('wpmem_reg_page') && apbct_is_plugin_active('wp-members/wp-members.php');
    }

    /**
     * @return bool
     */
    public function isBuddyBossLoginForm()
    {
        return Post::getString('wp-submit') && Post::getString('log') && Post::getString('pwd');
    }

    /**
     * @return object|null
     */
    public function defineBuddyPressObject()
    {
        // Get BuddyPress core instance if available
        $bp = function_exists('buddypress') ? buddypress() : null;
        if ( ! is_object($bp) || ! isset($bp->signup) || ! is_object($bp->signup) ) {
            return null;
        }
        if ( ! isset($bp->signup->errors) ) {
            $bp->signup->errors = array();
        }

        return $bp;
    }

    /**
     * @param bool $ct_signup_done
     * @param string $ct_negative_comment
     * @return bool
     */
    public function isBuddyPressAlreadyHasValidationErrors($ct_signup_done, $ct_negative_comment)
    {
        if (!$this->bp_object) {
            return false;
        }

        if (! empty($this->bp_object->signup->errors)) {
            return true;
        }

        // Break tests because we already have server response
        if ( $this->is_buddypress && $ct_signup_done ) {
            if ( $ct_negative_comment ) {
                $this->bp_object->signup->errors['signup_username'] = $ct_negative_comment;
            }
            return true;
        }
        // Skip BuddyPress request already contained validation errors
        return false;
    }

    /**
     * @return array|false
     */
    public function getFacebookFlow()
    {
        // Facebook registration
        $facebook_flow = Post::getArray('FB_userdata');
        if ( !empty($facebook_flow) ) {
            return $facebook_flow;
        }
        return false;
    }

    /**
     * @param string|null $sanitized_user_login
     * @param string|null $user_email
     * @return bool
     */
    public function probablyFacebookCredentials(&$sanitized_user_login, &$user_email)
    {
        $facebook_flow = $this->getFacebookFlow();
        $is_facebook = false;
        if ($facebook_flow) {
            if ( $sanitized_user_login === null && isset($facebook_flow['name']) ) {
                $sanitized_user_login = Sanitize::cleanUser($facebook_flow['name']);
                $is_facebook = true;
            }
            if ($user_email === null && isset($facebook_flow['email'])) {
                $user_email = Sanitize::cleanEmail($facebook_flow['email']);
                $is_facebook = true;
            }
        }
        return $is_facebook;
    }

    /**
     * @param string|null $sanitized_user_login
     * @param string|null $user_email
     * @return bool
     */
    public function probablyBuddyPressCredentials(&$sanitized_user_login = null, &$user_email = null)
    {
        $is_buddypress = false;
        //BuddyPress
        if ( $sanitized_user_login === null && Post::get('signup_username') ) {
            $sanitized_user_login = Sanitize::cleanUser(Post::get('signup_username'));
            $is_buddypress = true;
        }
        if ( $user_email === null && Post::get('signup_email') ) {
            $user_email = Sanitize::cleanEmail(Post::get('signup_email'));
            $is_buddypress = true;
        }
        return $is_buddypress;
    }

    /**
     * @param string|null $sanitized_user_login
     * @return void
     */
    public function probablyWooCommerceCredentials(&$sanitized_user_login)
    {
        if (current_filter() === 'woocommerce_registration_errors') {
            if (!is_null($sanitized_user_login) && strpos($sanitized_user_login, '.') !== false) {
                $username_parts = explode('.', $sanitized_user_login);
                $sanitized_user_login = implode(' ', $username_parts);
                $sanitized_user_login = Sanitize::cleanUser($sanitized_user_login);
            }
        }
    }

    /**
     * @param string|null $sanitized_user_login
     * @param string|null $user_email
     * @return void
     */
    public function probablyTutorLMSCredentials(&$sanitized_user_login, &$user_email)
    {
        if (
            Post::getString('tutor_action') === 'tutor_register_student' ||
            Post::getString('tutor_action') === 'tutor_register_instructor'
        ) {
            $user_email = Sanitize::cleanEmail(Post::getString('email'));
            $sanitized_user_login = Sanitize::cleanUser(Post::getString('user_login'));
            $this->reg_flag = true;
        }
    }

    /**
     * @return array
     */
    public function getCheckJSData()
    {
        global $ct_checkjs_register_form;
        $stub = array(
            'checkjs' => null,
            'checkjs_post' => null,
            'checkjs_cookie' => null
        );
        if ( current_filter() === 'woocommerce_registration_errors' ) {
            $stub['checkjs'] = apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true);
            $stub['checkjs_post']   = null;
            $stub['checkjs_cookie'] = $stub['checkjs'];
        } else {
            // This hack can be helpful when plugin uses with untested themes&signups plugins.
            $stub['checkjs_post']   = apbct_js_test(Sanitize::cleanTextField(Post::getString($ct_checkjs_register_form)));
            $stub['checkjs_cookie'] = apbct_js_test(Sanitize::cleanTextField(Cookie::getString('ct_checkjs')), true);
            $stub['checkjs']        = $stub['checkjs_cookie'] ?: $stub['checkjs_post'];
        }

        // BuddyBoss Platform use rest api for registration from phone app
        if ( apbct_is_plugin_active('buddyboss-app/buddyboss-app.php') && apbct_is_in_uri('/wp-json/buddyboss-app/v1/signup') ) {
            $stub['checkjs'] = Post::getString('checkjs') === 'true' ? 1 : 0;
        }

        return $stub;
    }

    /**
     * @return bool
     */
    public function forceRegFlagCases($reg_flag)
    {
        $reg_flag_inner = $reg_flag;
        /**
         * Changing the type of check for BuddyPress
         */
        if ( Post::getString('signup_username') && Post::getString('signup_email') ) {
            // if buddy press set up custom fields
            $reg_flag_inner = ! empty(Post::getString('signup_profile_field_ids'));
        }

        /**
         * Changing the type of check for Avada Fusion
         */
        if ( Post::get('fusion_login_box') ) {
            $reg_flag_inner = true;
        }

        return $reg_flag_inner;
    }

    public function defineMessageForNonRegistrationMethod()
    {
        $field_values = '';
        $fields_numbers_to_check = explode(',', TT::toString(Post::get('signup_profile_field_ids')));
        foreach ( $fields_numbers_to_check as $field_number ) {
            $field_name = 'field_' . $field_number;
            $field_value = Post::getString($field_name) ? Sanitize::cleanTextareaField(Post::getString($field_name)) : '';
            $field_values .= $field_value . "\n";
        }
        return $field_values;
    }

    /**
     * @param $base_call_array
     * @param $reg_flag
     * @return array|CleantalkResponse[]
     */
    public function doBaseCall($base_call_array, $reg_flag)
    {
        return apbct_base_call(
            $base_call_array,
            $reg_flag
        );
    }

    /**
     * @param CleantalkResponse $ct_result_object
     * @return void
     */
    public function saveCTHash($ct_result_object)
    {
        ct_hash($ct_result_object->id);
    }

    /**
     * @param CleantalkResponse $ct_result_object
     * @return void
     */
    public function onAllowActions($ct_result_object)
    {
        global $cleantalk_executed, $apbct_cookie_register_ok_label, $apbct_cookie_request_id_label, $apbct_cookie_request_id;
        if (current_filter() === 'woocommerce_registration_errors' && $this->apbct->settings['forms__wc_register_from_order']) {
            $cleantalk_executed = false;
        }

        if ( $ct_result_object->id !== null ) {
            $apbct_cookie_request_id = $ct_result_object->id;
            Cookie::set($apbct_cookie_register_ok_label, TT::toString($ct_result_object->id), time() + 10, '/');
            Cookie::set($apbct_cookie_request_id_label, TT::toString($ct_result_object->id), time() + 10, '/');
        }
    }

    /**
     * @param CleantalkResponse $ct_result_object
     * @param $errors
     * @return mixed|void|null
     */
    public function onDenyActions($ct_result_object, $errors)
    {
        global $ct_negative_comment, $ct_registration_error_comment;
        $ct_negative_comment = TT::toString($ct_result_object->comment);
        $ct_registration_error_comment = TT::toString($ct_result_object->comment);

        $this->runMaybeDieActions($ct_result_object);

        if ( $this->is_facebook ) {
            /** @psalm-suppress InvalidArrayOffset */
            $_POST['FB_userdata']['email'] = '';
            /** @psalm-suppress InvalidArrayOffset */
            $_POST['FB_userdata']['name']  = '';
        }

        if (current_filter() === 'woocommerce_registration_errors') {
            add_action('woocommerce_store_api_checkout_order_processed', ['Cleantalk\Antispam\IntegrationsByClass\Woocommerce', 'storeApiCheckoutOrderProcessed'], 10, 2);
        }

        if ( $this->is_buddypress === true && $this->bp_object ) {
            $this->bp_object->signup->errors['signup_username'] = TT::toString($ct_result_object->comment);
        }

        if ( is_wp_error($errors) ) {
            $errors->add('ct_error', TT::toString($ct_result_object->comment));
        }

        return $errors;
    }

    /**
     * @param CleantalkResponse $ct_result_object
     * @return void
     */
    public function runMaybeDieActions($ct_result_object)
    {
        if (apbct_is_plugin_active('buddyboss-app/buddyboss-app.php') && apbct_is_in_uri('/wp-json/buddyboss-app/v1/signup')) {
            wp_send_json_error(['success' => false, 'message' => TT::toString($ct_result_object->comment)]);
        }
        if ((defined('MGM_PLUGIN_NAME') || apbct_is_plugin_active('bbpress/bbpress.php')) &&
            current_filter() !== 'woocommerce_registration_errors'
        ) {
            ct_die_extended(TT::toString($ct_result_object->comment));
        }
    }

    public function reforgeGlobals()
    {
        global $ct_signup_done, $cleantalk_executed;
        $ct_signup_done = true;
        $cleantalk_executed = true;
    }

    /**
     * @param CleantalkResponse $ct_result_object
     * @param string $user_email
     * @return void
     */
    public function prepareEmailForNonModeratedLicense($ct_result_object, $user_email)
    {
        if (
            $ct_result_object->fast_submit == 1 ||
            $ct_result_object->blacklisted == 1 ||
            $ct_result_object->js_disabled == 1
        ) {
            $this->apbct->sender_email = $user_email;
            $this->apbct->sender_ip    = Helper::ipGet('real');
            add_filter(
                'wp_new_user_notification_email_admin',
                'apbct_registration__Wordpress__changeMailNotification',
                100,
                3
            );
        }
    }

    /**
     * @param $ct_result_object
     * @param $user_email
     * @return void
     */
    public function doServiceActions($ct_result_object, $user_email)
    {
        $this->reforgeGlobals();

        $this->saveCTHash($ct_result_object);

        // Change mail notification if license is out of date
        if ( $this->apbct->data['moderate'] == 0) {
            $this->prepareEmailForNonModeratedLicense($ct_result_object, $user_email);
        }
    }
}
