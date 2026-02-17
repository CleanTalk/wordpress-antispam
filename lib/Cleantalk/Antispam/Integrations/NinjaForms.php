<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\DTO\GetFieldsAnyDTO;
use Cleantalk\ApbctWP\Escape;
use Cleantalk\ApbctWP\GetFieldsAny;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\TT;

class NinjaForms extends IntegrationBase
{
    private $sender_email = ''; // needs to provide to final actions
    public function getDataForChecking($argument)
    {
        global $apbct, $cleantalk_executed;

        if ( current_action() === 'ninja_forms_display_after_form' ) {
            return null;
        }

        if ( $cleantalk_executed ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);
            return null;
        }

        if (
            ($apbct->settings['data__protect_logged_in'] != 1 && is_user_logged_in()) || // Skip processing for logged in users.
            apbct_exclusions_check__url()
        ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);
            return null;
        }

        //skip ninja PRO service requests
        if ( !empty(Post::getString('nonce_ts')) ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);
            return null;
        }

        try {
            $gfa_dto = $this->getGFANew();
        } catch (\Exception $_e) {
            // It is possible here check the reason if the new way collecting fields is not available.
            $gfa_dto = $this->getGFAOld();
        }

        $form_data = json_decode(Post::getString('formData'), true);
        if ( empty($form_data) ) {
            $form_data = json_decode(stripslashes(Post::getString('formData')), true);
        }

        if (empty($form_data)) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);
            return null;
        }

        if ( $gfa_dto->nickname === '' || $gfa_dto->email === '' ) {
            $form_data = json_decode(Post::getString('formData'), true);
            if ( empty($form_data) ) {
                $form_data = json_decode(stripslashes(Post::getString('formData')), true);
            }
            $form_fields = $form_data['fields'] ?? array();
            $gfa_dto = $this->updateEmailNicknameFromNFService($gfa_dto, $form_fields);
        }

        if ( $gfa_dto->subject != '' ) {
            $gfa_dto->message = array_merge(array('subject' => $gfa_dto->subject), $gfa_dto->message);
        }

        //Ninja Forms xml fix
        foreach ( $gfa_dto->message as $key => $value ) {
            if ( strpos($value, '<xml>') !== false ) {
                unset($gfa_dto->message[$key]);
            }
        }

        $this->sender_email = $gfa_dto->email;

        $fields_visibility_data = GetFieldsAny::getVisibleFieldsData($form_data, true);
        $this->setVisibleFieldsData($fields_visibility_data);

        return $gfa_dto->getArray();
    }

    public function doBlock($message)
    {
        global $apbct;
        // We have to use GLOBAL variable to transfer the comment to apbct_form__ninjaForms__changeResponse() function :(
        $apbct->response = $message;
        add_action(
            'ninja_forms_before_response',
            [$this, 'hookChangeResponse'],
            10,
            1
        );
        add_action(
            'ninja_forms_action_email_send',
            [$this, 'hookStopEmail'],
            1,
            5
        ); // Prevent mail notification
        add_action(
            'ninja_forms_save_submission',
            [$this, 'hookPreventSubmission'],
            1,
            2
        ); // Prevent mail notification
        add_filter(
            'ninja_forms_run_action_type_add_to_hubspot',
            /** @psalm-suppress UnusedClosureParam */
            function ($result) {
                return false;
            },
            1
        );
        add_filter(
            'ninja_forms_run_action_type_nfacds',
            /** @psalm-suppress UnusedClosureParam */
            function ($result) {
                return false;
            },
            1
        );
        add_filter(
            'ninja_forms_run_action_type_save',
            /** @psalm-suppress UnusedClosureParam */
            function ($result) {
                return false;
            },
            1
        );
        add_filter(
            'ninja_forms_run_action_type_successmessage',
            /** @psalm-suppress UnusedClosureParam */
            function ($result) {
                return false;
            },
            1
        );
        add_filter(
            'ninja_forms_run_action_type_email',
            /** @psalm-suppress UnusedClosureParam */
            function ($result) {
                return false;
            },
            1
        );
    }

    public function doFinalActions($argument)
    {
        global $apbct;

        if (current_action() === 'ninja_forms_display_after_form') {
            self::hookAddTrustedFieldToForm();
            return true;
        }

        if (!empty($this->base_call_result)) {
            // Change mail notification if license is out of date
            if ( $apbct->data['moderate'] == 0 &&
                ($this->base_call_result->fast_submit == 1 || $this->base_call_result->blacklisted == 1 || $this->base_call_result->js_disabled == 1)
            ) {
                $apbct->sender_email = $this->sender_email;
                $apbct->sender_ip    = Helper::ipGet();
                add_filter('ninja_forms_action_email_message', [$this, 'hookChangeMailNotification'], 1, 3);
            }
        }

        return true;
    }

    /**
     * @return GetFieldsAnyDTO
     * @throws \Exception
     * @psalm-suppress UndefinedClass
     */
    public function getGFANew(): GetFieldsAnyDTO
    {
        $form_data = json_decode(Post::getString('formData'), true);
        if ( ! $form_data ) {
            $form_data = json_decode(stripslashes(TT::toString(Post::get('formData'))), true);
        }

        if ( ! isset($form_data['fields'])) {
            throw new \Exception('No form data is provided');
        }

        $form_id = $form_data['id'] ?? null;
        if ( ! $form_id ) {
            throw new \Exception('No form id provided');
        }

        if ( ! function_exists('Ninja_Forms') ) {
            throw new \Exception('No `Ninja_Forms` class exists');
        }

        $nf_form_info = Ninja_Forms()->form($form_id);

        if (!class_exists('\NF_Abstracts_ModelFactory') || ! ($nf_form_info instanceof \NF_Abstracts_ModelFactory) ) {
            throw new \Exception('Getting NF form failed');
        }
        $nf_form_fields_info = $nf_form_info->get_fields();
        if ( ! is_array($nf_form_fields_info) || count($nf_form_fields_info) === 0 ) {
            throw new \Exception('No fields are provided');
        }
        $nf_form_fields_info_array = [];
        foreach ($nf_form_fields_info as $field) {
            if ( $field instanceof \NF_Database_Models_Field) {
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
     * @return GetFieldsAnyDTO
     */
    public function getGFAOld(): GetFieldsAnyDTO
    {
        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $_POST);

        // Choosing between sanitized GET and POST
        $input_data = Get::getString('ninja_forms_ajax_submit') || Get::getString('nf_ajax_submit')
            ? array_map(function ($value) {
                return is_string($value) ? htmlspecialchars($value) : $value;
            }, $_GET)
            : $input_array;

        // Return the collected fields data
        return ct_gfa_dto($input_data);
    }


    /**
     * @param $_some
     * @param $_form_id
     * @return false
     */
    public static function hookPreventSubmission($_some, $_form_id): bool
    {
        return false;
    }

    /**
     * @param GetFieldsAnyDTO $gfa_dto
     * @param array $nf_form_fields
     * @return GetFieldsAnyDTO
     */
    public function updateEmailNicknameFromNFService(GetFieldsAnyDTO $gfa_dto, array $nf_form_fields): GetFieldsAnyDTO
    {
        if ( function_exists('Ninja_Forms') && !empty($nf_form_fields) ) {
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

            $nickname = '';
            $email = '';
            foreach ($nf_form_fields as $field) {
                $field_info = $nf_form_fields_info_array[$field['id']];
                // handle nickname-like fields, add matches to existing nickname string
                if ( stripos($field_info['field_key'], 'name') !== false ) {
                    $nickname = empty($nickname) ? $field['value'] : $nickname . ' ' . $field['value'];
                }
                // handle email-like fields, if no GFA result, set it once
                if (empty($gfa_dto->email) && stripos($field_info['field_key'], 'email') !== false ) {
                    $email = empty($email) ? $field['value'] : $email;
                }
            }
            // if gfa is empty, fill it with data from Ninja Forms, if not empty, append data from Ninja Forms
            $gfa_dto->nickname = empty($gfa_dto->nickname) ? $nickname : $gfa_dto->nickname . ' ' . $nickname;
            // if email is empty, fill it with data from Ninja Forms, if not empty, keep DTO
            $gfa_dto->email = empty($gfa_dto->email) ? $email : $gfa_dto->email;
        }
        return $gfa_dto;
    }


    /**
     * @param $_some
     * @param $_action_settings
     * @param $_message
     * @param $_headers
     * @param $_attachments
     *
     * @throws \Exception
     */
    public static function hookStopEmail($_some, $_action_settings, $_message, $_headers, $_attachments)
    {
        global $apbct;
        throw new \Exception($apbct->response);
    }

    /**
     * @param $data
     *
     * @psalm-suppress InvalidArrayOffset
     */
    public static function hookChangeResponse($data)
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
     * Inserts anti-spam hidden to ninja forms
     *
     * @return void
     * @psalm-suppress UnusedParam
     */
    public static function hookAddTrustedFieldToForm()
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

    /**
     * Changes email notification for success subscription for Ninja Forms
     *
     * @param string $message Body of email notification
     *
     * @return string Body for email notification
     */
    public static function hookChangeMailNotification($message, $_data, $action_settings)
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
}
