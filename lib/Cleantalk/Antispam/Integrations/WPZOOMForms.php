<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;

/**
 * WPZOOM Froms integration.
 * Note: the integration hooks are of admin-post.php - "admin_post_nopriv_wpzf_submit" and "admin_post_wpzf_submit".
 */
class WPZOOMForms extends IntegrationBase
{
    /**
     * Legacy old way to collect data.
     *
     * @param $argument
     *
     * @return array
     */
    public function getDataForChecking($argument)
    {
        global $apbct;

        // Skip processing for logged in users.
        if ( $apbct->settings['data__protect_logged_in'] != 1 && is_user_logged_in() ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);
            return null;
        }

        $input_array = apply_filters('apbct__filter_post', $_POST);
        $input_array['event_token'] = Post::getString('ct_bot_detector_event_token');
        // Take the email field of the form
        $email = '';
        if ( function_exists('parse_blocks') ) {
            $blocks = parse_blocks((string)get_post_field('post_content', Post::getInt('form_id'), 'raw'));
            foreach ($this->getEmailFieldsIds($blocks) as $field_id) {
                $email = Post::getString('wpzf_' . $field_id);
                if ( $email !== '' ) {
                    break;
                }
            }
        }
        $name = isset($input_array['wpzf_input_name']) ? $input_array['wpzf_input_name'] : '';
        return ct_gfa_dto($input_array, $email, $name)->getArray();
    }

    /**
     * Get IDs of the email fields of the form blocks
     *
     * @param array $blocks
     *
     * @return array
     */
    private function getEmailFieldsIds($blocks)
    {
        $ids = array();

        foreach ($blocks as $block) {
            if (
                isset($block['blockName'], $block['attrs']['id']) &&
                $block['blockName'] === 'wpzoom-forms/text-email-field'
            ) {
                $ids[] = (string)$block['attrs']['id'];
            }
            if ( ! empty($block['innerBlocks']) && is_array($block['innerBlocks']) ) {
                $ids = array_merge($ids, $this->getEmailFieldsIds($block['innerBlocks']));
            }
        }

        return $ids;
    }

    /**
     * How to handle CleanTalk forbidden result.
     * @param $message
     * @return void
     */
    public function doBlock($message)
    {
        ct_die_extended($message);
    }
}
