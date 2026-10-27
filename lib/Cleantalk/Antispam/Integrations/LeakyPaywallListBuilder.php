<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\Common\TT;

class LeakyPaywallListBuilder extends IntegrationBase
{
    private $return_argument;

    public function getDataForChecking($argument)
    {
        $this->return_argument = $argument;

        $request = isset($this->hook_arguments[1]) ? $this->hook_arguments[1] : null;
        if ( ! empty($argument) || ! ($request instanceof \WP_REST_Request) ) {
            return null;
        }

        // Take the email
        $email = sanitize_email(TT::toString($request->get_param('email')));

        $input_array = apply_filters('apbct__filter_post', array('email' => $email));

        $output = ct_gfa($input_array, $email);
        $output['register'] = true;

        return $output;
    }

    /**
     * The message makes the plugin reject the signup.
     *
     * @param $message
     *
     * @return string
     */
    public function doBlock($message)
    {
        return wp_strip_all_tags($message);
    }

    public function allow()
    {
        return $this->return_argument;
    }
}
