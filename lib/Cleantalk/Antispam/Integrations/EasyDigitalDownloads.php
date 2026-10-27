<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class EasyDigitalDownloads extends IntegrationBase
{
    private $user_data;

    public function getDataForChecking($argument)
    {
        $this->user_data = $argument;

        // The purchase of a new or an existing customer
        if ( current_filter() === 'edd_checkout_user_error_checks' ) {
            return $this->getPurchaseDataForChecking($argument);
        }

        // The customer of the purchase is already checked
        if ( current_filter() === 'edd_customer_pre_create' && did_action('edd_checkout_user_error_checks') ) {
            return null;
        }

        if (
            Post::getString('edd_action') === "user_register" ||
            !empty($argument['user_email']) ||
            // EDD creates a customer
            !empty($argument['email'])
        ) {
            // Take the email from the user or the customer data
            $email = '';
            if ( ! empty($argument['user_email']) && is_string($argument['user_email']) ) {
                $email = $argument['user_email'];
            } elseif ( ! empty($argument['email']) && is_string($argument['email']) ) {
                $email = $argument['email'];
            }

            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $_POST);
            $data = ct_get_fields_any($input_array, $email);
            $data['register'] = true;
            return $data;
        }
        return null;
    }

    /**
     * @param $message
     *
     * @psalm-suppress UnusedVariable
     */
    public function doBlock($message)
    {
        global $ct_comment;

        // EDD stops the purchase on the checkout error
        if ( current_filter() === 'edd_checkout_user_error_checks' && function_exists('edd_set_error') ) {
            edd_set_error('apbct_spam', $message);
            return;
        }

        $ct_comment = $message;
        ct_die(null, null);
    }

    public function allow()
    {
        return $this->user_data;
    }

    /**
     * @param array|false $user The purchase user data
     *
     * @return array|null
     */
    private function getPurchaseDataForChecking($user)
    {
        global $apbct;

        // The ajax validation of the checkout form, the order is placed by the next request
        if (
            ! empty($_POST['edd_ajax']) &&
            (doing_action('wp_ajax_edd_process_checkout') || doing_action('wp_ajax_nopriv_edd_process_checkout'))
        ) {
            return null;
        }

        // Skip processing for logged in users.
        if ( $apbct->settings['data__protect_logged_in'] != 1 && apbct_is_user_logged_in() ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);
            return null;
        }

        if ( empty($user) || ! is_array($user) ) {
            return null;
        }

        $email = isset($user['user_email']) && is_string($user['user_email']) ? $user['user_email'] : '';

        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $_POST);

        return ct_get_fields_any($input_array, $email);
    }
}
