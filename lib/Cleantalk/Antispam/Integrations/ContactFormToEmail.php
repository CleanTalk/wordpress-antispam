<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class ContactFormToEmail extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $cp_cfte_plugin;

        if ( ! is_array($argument) ) {
            return null;
        }

        // The sender email field is set in the form settings
        $email = '';
        if ( is_object($cp_cfte_plugin) && method_exists($cp_cfte_plugin, 'get_option') ) {
            $email_field = $cp_cfte_plugin->get_option('cu_user_email_field', '');
            if ( is_string($email_field) && isset($argument[$email_field]) && is_string($argument[$email_field]) ) {
                $email = $argument[$email_field];
            }
        }

        $input_array = apply_filters('apbct__filter_post', $argument);
        $input_array['event_token'] = Post::getString('ct_bot_detector_event_token');

        return ct_gfa_dto($input_array, $email)->getArray();
    }

    /**
     * @param $message
     *
     * @return void
     * @psalm-suppress UnusedVariable
     */
    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }
}
