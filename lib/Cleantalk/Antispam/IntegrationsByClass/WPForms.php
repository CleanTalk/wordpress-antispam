<?php

namespace Cleantalk\Antispam\IntegrationsByClass;

use Cleantalk\ApbctWP\Honeypot;
use Cleantalk\ApbctWP\Escape;
use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Helper;

/**
 * @psalm-suppress UnusedClass
 */
class WPForms extends IntegrationByClassBase
{
    private $form_data = [];

    /**
     * @return void
     * @global State $apbct
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function doPublicWork()
    {
        if (!$this->isSkipIntegration()) {
            add_action('wpforms_frontend_output', [$this, 'addField'], 1000, 5);
            add_filter('wpforms_process_initial_errors', [$this, 'showResponse'], 100, 2);
            add_filter('wpforms_process_before_filter', [$this, 'gatherData'], 100, 2);
        }
    }

    public function doAjaxWork()
    {
        if (!$this->isSkipIntegration()) {
            add_filter('wpforms_process_initial_errors', [$this, 'showResponse'], 100, 2);
            add_filter('wpforms_process_before_filter', [$this, 'gatherData'], 100, 2);
        }
    }

    public function gatherData($entry, $form)
    {
        $input_array = apply_filters('apbct__filter_post', isset($entry['fields']) ? $entry['fields'] : array());

        $entry_fields_data = $input_array ?: array();
        $form_fields_info  = $form['fields'] ?: array();

        $handled_result = array();

        foreach ($form_fields_info as $form_field) {
            if (!isset($form_field['id'])) {
                continue;
            }
            $field_id = $form_field['id'];

            if (!isset($form_field['type'])) {
                continue;
            }
            $field_type = $form_field['type'];

            if (!isset($entry_fields_data[$field_id])) {
                continue;
            }
            $entry_field_value = $entry_fields_data[$field_id];

            $field_label = isset($form_field['label']) ? $form_field['label'] : '';

            // email field
            if ($field_type === 'email' && (!isset($handled_result['email']) || empty($handled_result['email']))) {
                $handled_result['email'] = $entry_field_value;
                continue;
            }

            // name field
            if ($field_type === 'name') {
                if ( is_array($entry_field_value) ) {
                    $handled_result['name'][] = implode(' ', array_slice($entry_field_value, 0, 3));
                } else {
                    $handled_result['name'][] = $entry_field_value;
                }
                continue;
            }

            // message field
            if ($field_type === 'textarea') {
                if (is_array($entry_field_value)) {
                    $handled_result["wpforms[fields][$field_id]"][] = implode(' ', array_slice($entry_field_value, 0, 3));
                } else {
                    $handled_result["wpforms[fields][$field_id]"] = $entry_field_value;
                }
                continue;
            }

            // add unique key if key exist
            if ($field_label) {
                $field_label = mb_strtolower(trim($field_label));
                $field_label = str_replace(' ', '_', $field_label);
                $field_label = preg_replace('/\W/u', '', $field_label);

                if (!isset($handled_result[$field_label]) || empty($handled_result[$field_label])) {
                    $handled_result[$field_label] = $entry_field_value;
                } else {
                    $handled_result[$field_label . rand(0, 100)] = $entry_field_value;
                }
            }
        }

        $this->form_data = $handled_result;

        return $entry;
    }

    /**
     * Inserts anti-spam hidden to WPForms
     *
     * @return void
     * @global State $apbct
     */
    public function addField($_form_data, $_some, $_title, $_description, $_errors)
    {
        global $apbct;

        ct_add_hidden_fields('ct_checkjs_wpforms');
        echo Honeypot::generateHoneypotField('wp_wpforms');
        if ( $apbct->settings['trusted_and_affiliate__under_forms'] === '1' ) {
            echo Escape::escKsesPreset(
                apbct_generate_trusted_text_html('label_left'),
                'apbct_public__trusted_text'
            );
        }
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
    public function showResponse($errors, $form_data)
    {
        if (!empty($errors)) {
            return $errors;
        }

        $spam_comment = $this->testSpam();

        error_log('showResponse');
        error_log(print_r($spam_comment, true));

        if (!$spam_comment ) {
            return $errors;
        }

        $field_id = 0;
        if ( $form_data && ! empty($form_data['fields']) && is_array($form_data['fields']) ) {
            foreach ( $form_data['fields'] as $key => $field ) {
                if ( array_search('email', $field) === 'type' ) {
                    $field_id = $key;
                    break;
                }
            }
        }

        $field_id = ! $field_id && $form_data && ! empty($form_data['fields']) && is_array($form_data['fields'])
            ? key($form_data['fields'])
            : $field_id;

        if ( isset($form_data['id']) ) {
            $errors[$form_data['id']][$field_id] = $spam_comment;
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
    public function testSpam()
    {
        global $apbct;

        $checkjs = apbct_js_test(Sanitize::cleanTextField(Post::get('ct_checkjs_wpforms')));
        $input_array = apply_filters('apbct__filter_post', $_POST);

        $email = $this->form_data['email'] ? $this->form_data['email'] : null;

        // Fixed if the 'Enable email address confirmation' option is enabled
        if (is_array($email)) {
            $email = reset($email);
        }

        $nickname = null;
        $form_data = $this->form_data instanceof \ArrayObject ? (array)$this->form_data : $this->form_data;
        if (array_key_exists('name', $form_data)) {
            $nickname = isset($form_data['name']) && is_array($form_data['name']) ? array_shift(
                $form_data['name']
            ) : null;
        }

        $params = ct_gfa_dto($input_array, is_null($email) ? '' : $email, is_null($nickname) ? '' : $nickname)->getArray();

        if ( isset($params['nickname']) && is_array($params['nickname']) ) {
            $params['nickname'] = implode(' ', $params['nickname']);
        }

        $sender_email    = isset($params['email']) ? $params['email'] : '';
        $sender_nickname = isset($params['nickname']) ? $params['nickname'] : '';
        $subject         = isset($params['subject']) ? $params['subject'] : '';
        $message         = isset($params['message']) ? $params['message'] : array();
        if ( $subject !== '' ) {
            $message = array_merge(array('subject' => $subject), $message);
        }

        $sender_info = [];
        if ( ! empty($params['emails_array']) ) {
            $sender_info['sender_emails_array'] = $params['emails_array'];
        }

        $base_call_result = apbct_base_call(
            array(
                'message'         => $message,
                'sender_email'    => $sender_email,
                'sender_nickname' => $sender_nickname,
                'post_info'       => array('comment_type' => 'contact_form_wordpress_wp_forms'),
                'js_on'           => $checkjs,
                'sender_info'     => $sender_info,
            )
        );

        if ( isset($base_call_result['ct_result']) ) {
            $ct_result = $base_call_result['ct_result'];

            // Change mail notification if license is out of date
            if ( $apbct->data['moderate'] == 0 &&
                ($ct_result->fast_submit == 1 || $ct_result->blacklisted == 1 || $ct_result->js_disabled == 1)
            ) {
                $apbct->sender_email = $sender_email;
                $apbct->sender_ip    = Helper::ipGet('real');
                add_filter('wpforms_email_message', [$this, 'changeMailNotification'], 100, 2);
            }

            if ( $ct_result->allow == 0 ) {
                return $ct_result->comment;
            }
        }

        return null;
    }

    /**
     * Changes email notification for succes subscription for WPForms
     *
     * @param string $message Body of email notification
     * @param object $wpforms_email WPForms email class object
     *
     * @return string Body for email notification
     */
    public function changeMailNotification($message, $_wpforms_email)
    {
        global $apbct;

        $message = str_replace(array('</html>', '</body>'), '', $message);
        $message .=
            wpautop(
                PHP_EOL
                . '---'
                . PHP_EOL
                . __('CleanTalk Anti-Spam: This message could be spam.', 'cleantalk-spam-protect')
                . PHP_EOL . __('CleanTalk\'s Anti-Spam database:', 'cleantalk-spam-protect')
                //HANDLE LINK
                . PHP_EOL . 'IP: ' . '<a href="https://cleantalk.org/blacklists/' . $apbct->sender_ip . '?utm_source=newsletter&utm_medium=email&utm_campaign=wpforms_spam_passed" target="_blank">' . $apbct->sender_ip . '</a>'
                //HANDLE LINK
                . PHP_EOL . 'Email: ' . '<a href="https://cleantalk.org/blacklists/' . $apbct->sender_email . '?utm_source=newsletter&utm_medium=email&utm_campaign=wpforms_spam_passed" target="_blank">' . $apbct->sender_email . '</a>'
                . PHP_EOL
                //HANDLE LINK
                . sprintf(
                    __('If you want to be sure activate protection in your %sAnti-Spam Dashboard%s.', 'clentalk'),
                    '<a href="https://cleantalk.org/my/?cp_mode=antispam&utm_source=newsletter&utm_medium=email&utm_campaign=wpforms_activate_antispam" target="_blank">',
                    '</a>'
                )
            )
            . '</body></html>';

        return $message;
    }

    public function isSkipIntegration()
    {
        global $apbct;

        if ($apbct->settings['forms__contact_forms_test'] == 0 ||
            ($apbct->settings['data__protect_logged_in'] != 1 && is_user_logged_in())
        ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);
            return true;
        }

        return false;
    }
}
