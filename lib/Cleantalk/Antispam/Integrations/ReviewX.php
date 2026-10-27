<?php

namespace Cleantalk\Antispam\Integrations;

class ReviewX extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        // Skip likes and dislikes of the reviews
        if (
            ! apbct_is_plugin_active('reviewx/reviewx.php') ||
            ! is_array($argument) ||
            (apbct_is_rest_route('reviewx/api/v1/storefront/reviews/') && apbct_is_path_end('/preference'))
        ) {
            return null;
        }

        $email = isset($argument['reviewer_email']) && is_string($argument['reviewer_email']) ? $argument['reviewer_email'] : '';
        $nickname = isset($argument['reviewer_name']) && is_string($argument['reviewer_name']) ? $argument['reviewer_name'] : '';

        return ct_gfa_dto(apply_filters('apbct__filter_post', $argument), $email, $nickname)->getArray();
    }

    public function allow()
    {
        return null;
    }

    public function doBlock($message)
    {
        wp_send_json_error(
            array(
                'message' => $message
            ),
            403
        );
    }
}
