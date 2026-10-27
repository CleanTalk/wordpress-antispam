<?php

namespace Cleantalk\Antispam\Integrations;

class SiteReviews extends IntegrationBase
{
    private $return_argument;

    public function getDataForChecking($argument)
    {
        $this->return_argument = $argument;

        // Skip rejected and AJAX submissions
        if (
            $argument !== true ||
            doing_action('wp_ajax_glsr_public_action') ||
            doing_action('wp_ajax_nopriv_glsr_public_action')
        ) {
            return null;
        }

        $request = isset($this->hook_arguments[1]) ? $this->hook_arguments[1] : null;
        if ( ! is_object($request) || ! method_exists($request, 'toArray') ) {
            return null;
        }

        // The review the plugin is going to save
        $review = $request->toArray();
        $email = isset($review['email']) && is_string($review['email']) ? $review['email'] : '';
        $nickname = isset($review['name']) && is_string($review['name']) ? $review['name'] : '';

        $input_array = apply_filters('apbct__filter_post', $review);

        return ct_gfa_dto($input_array, $email, $nickname)->getArray();
    }

    /**
     * The message makes the plugin reject the submission.
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
