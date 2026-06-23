<?php

namespace Cleantalk\Antispam\Integrations;

class EmailSubscribers extends IntegrationBase
{
    /**
     * Skip checking if already executed or if ES already returned an error (filter path).
     *
     * @param mixed $argument For AJAX: empty. For filter: $validate_response array.
     * @return bool|mixed true to proceed, false to skip, non-bool to return directly.
     */
    public function doPrepareActions($argument)
    {
        global $cleantalk_executed;

        // Skip if CleanTalk already checked this request (e.g. AJAX hook ran, then filter fires)
        if ( ! empty($cleantalk_executed) ) {
            return false;
        }

        // For the filter path: skip if ES validation already returned an error
        if ( is_array($argument) && ! empty($argument['status']) && 'ERROR' === $argument['status'] ) {
            return false;
        }

        return true;
    }

    /**
     * @param mixed $argument
     * @return array|null
     */
    public function getDataForChecking($argument)
    {
        $post_data = apply_filters('apbct__filter_post', $_POST);
        $email = isset($post_data['esfpx_email']) ? $post_data['esfpx_email'] : '';
        $nickname = isset($post_data['esfpx_name']) ? $post_data['esfpx_name'] : '';

        if ( empty($email) ) {
            return null;
        }

        $data = ct_gfa_dto($post_data, $email, $nickname)->getArray();

        return $data;
    }

    /**
     * @param mixed $message
     * @return array{message_text: mixed, status: string}|void
     */
    public function doBlock($message)
    {
        // AJAX path: respond with JSON and die
        if ( defined('DOING_AJAX') && DOING_AJAX ) {
            wp_send_json(
                array(
                    'status'       => 'ERROR',
                    'message_text' => $message,
                )
            );
        }

        // Non-AJAX (filter) path: return error array for ig_es_validate_subscription
        return array(
            'status'       => 'ERROR',
            'message_text' => $message,
        );
    }
}
