<?php

namespace Cleantalk\Antispam\IntegrationsByClass;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;

/**
 * @psalm-suppress UnusedClass
 */
class ArmMember extends IntegrationByClassBase
{
    private $registration_data = null;
    private static $registration_in_progress = false;

    public function doPublicWork()
    {
        add_filter('arm_before_member_register', array($this, 'gatherData'), 100);
        add_action('arm_remove_third_party_error', array($this, 'checkSpam'), 20);
    }

    public static function isRegistrationInProgress()
    {
        return self::$registration_in_progress;
    }

    public function gatherData($posted_data)
    {
        $this->registration_data = null;
        self::$registration_in_progress = false;

        if ( $this->isSkipIntegration() ) {
            return $posted_data;
        }

        if ( ! is_array($posted_data) ) {
            do_action('apbct_skipped_request', __METHOD__, array('Invalid ARMember registration data.'));
            return $posted_data;
        }

        // This filter receives registration data even when a payment callback has a different POST body.
        $this->registration_data = $this->collectBaseCallData($posted_data);
        self::$registration_in_progress = true;

        return $posted_data;
    }

    public function isSkipIntegration()
    {
        global $apbct;

        return empty($apbct->settings['forms__registrations_test']) ||
            ! apbct_is_user_enable() ||
            (is_user_logged_in() && empty($apbct->settings['data__protect_logged_in']));
    }

    public function collectBaseCallData($posted_data)
    {
        $form_data = array();
        foreach ( array('user_email', 'user_login', 'first_name', 'last_name') as $field ) {
            if ( isset($posted_data[$field]) && is_string($posted_data[$field]) ) {
                $form_data[$field] = trim($posted_data[$field], " \n\r\t\v\x00");
            }
        }
        $form_data = apply_filters('apbct__filter_post', $form_data);
        $email = TT::getArrayValueAsString($form_data, 'user_email');
        $nickname = TT::getArrayValueAsString($form_data, 'user_login');
        $fields = ct_gfa_dto($form_data, $email, $nickname ?: $email)->getArray();

        $event_token = TT::getArrayValueAsString($posted_data, 'ct_bot_detector_event_token');
        if ( $event_token === '' ) {
            $event_token = Post::getString('ct_bot_detector_event_token');
        }

        $position = strrpos(self::class, '\\');
        $integration_name = $position === false ? self::class : substr(self::class, $position + 1);

        return array(
            'sender_email' => TT::getArrayValueAsString($fields, 'email'),
            'sender_nickname' => TT::getArrayValueAsString($fields, 'nickname'),
            'event_token' => $event_token,
            'post_info' => array(
                'comment_type' => 'contact_form_wordpress_' . strtolower($integration_name),
                'post_url' => Server::get('HTTP_REFERER'),
            ),
        );
    }

    public function checkSpam($errors)
    {
        $registration_data = $this->registration_data;
        $this->registration_data = null;
        self::$registration_in_progress = false;

        // Administrative member creation calls this action without the registration filter.
        if ( $registration_data === null || $this->isSkipIntegration() ) {
            return;
        }

        if ( ! ($errors instanceof \WP_Error) ) {
            do_action('apbct_skipped_request', __METHOD__, array('Invalid ARMember registration errors.'));
            return;
        }

        if ( $errors->get_error_code() ) {
            do_action('apbct_skipped_request', __METHOD__, array('ARMember registration validation failed.'));
            return;
        }

        $result = $this->checkRegistration($registration_data);
        if ( isset($result['ct_result']) && is_object($result['ct_result']) ) {
            $ct_result = $result['ct_result'];
            if ( isset($ct_result->id) ) {
                ct_hash($ct_result->id);
            }
            if ( ! empty($ct_result->inactive) ) {
                ct_send_error_notice($ct_result->comment);
                return;
            }
            if ( $ct_result->allow == 0 ) {
                $errors->add('arm_reg_error', $ct_result->comment);
            }
        }
    }

    protected function checkRegistration($registration_data)
    {
        return apbct_base_call($registration_data, true);
    }
}
