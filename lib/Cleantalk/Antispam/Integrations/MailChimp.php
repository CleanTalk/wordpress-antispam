<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

/**
 * This class handles the integration with the MailChimp for WordPress plugin. Both types of request AJAX and POST.
 */
class MailChimp extends IntegrationBase
{
    /**
     * @var bool
     */
    private $is_ajax = false;

    public function doPrepareActions($argument)
    {
        if (
            ! empty(Post::get('_mc4wp_form_id')) &&
            apbct_is_plugin_active('mailchimp-for-wp/mailchimp-for-wp.php')
        ) {
            $this->is_ajax = apbct_is_ajax();
            return parent::doPrepareActions($argument);
        }
        return false;
    }

    public function getDataForChecking($argument)
    {
        global $apbct;

        if ( !empty($_POST) ) {
            if ( ! $apbct->stats['no_cookie_data_taken'] ) {
                apbct_form__get_no_cookie_data();
            }

            $input_array = apply_filters('apbct__filter_post', $_POST);

            $data_to_spam_check = ct_gfa($input_array);

            if ( isset($data_to_spam_check['ct_bot_detector_event_token']) ) {
                $data_to_spam_check['event_token'] = $data_to_spam_check['ct_bot_detector_event_token'];
            }

            // It is a service field. Need to be deleted before the processing.
            if ( isset($input_array['apbct_visible_fields']) ) {
                unset($input_array['apbct_visible_fields']);
            }

            return $data_to_spam_check;
        }

        return null;
    }

    /**
     * @param $message
     * @return string|void
     */
    public function doBlock($message)
    {
        if ( $this->is_ajax ) {
            /**
             * The plugin logic waits for string of the message name that needs to be returned.
             * The message array prepared in the addFormResponse() method called in the hook mc4wp_form_messages
             * Important! The hook must be called on public validate functions
             */
            if ( !empty(Post::get('_mc4wp_form_id')) ) {
                return ('ct_mc4wp_response');
            }
        } else {
            // If not AJAX use ct_die_extended()
            ct_die_extended($message);
        }
    }

    /**
     * Adds a custom form response message for MailChimp forms.
     *
     * This method modifies the passed $messages array by adding a custom response
     * message under the key 'ct_mc4wp_response'. This message is used to indicate
     * that the submitted message looks like spam. The method returns the modified
     * array of messages.
     *
     * Important! We cant modify the block message, cause array of messages initiated before
     * the spam check and can't be modified.
     *
     * @param array $messages The array of form response messages.
     * @return array The modified array of form response messages with the custom spam message added.
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public static function addFormResponse($messages)
    {
        $messages['ct_mc4wp_response'] = array(
            'type' => 'error',
            'text' => 'Your message looks like spam.'
        );
        return $messages;
    }
}
