<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\Common\TT;

class GwolleGuestbook extends IntegrationBase
{
    private $entry;

    private $is_entry_edit = false;

    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        $this->entry = $argument;

        $processed_post = apply_filters('apbct__filter_post', $_POST);

        // Editing of the entry by the logged in author
        if ( strpos(TT::toString(current_action()), 'gwolle_gb_entry_edit') !== false ) {
            $this->is_entry_edit = true;
            $current_user = wp_get_current_user();
            return ct_gfa_dto($processed_post, $current_user->user_email, $current_user->display_name)->getArray();
        }

        $data = ct_gfa_dto($processed_post)->getArray();
        return $data;
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        if ( $this->is_entry_edit ) {
            // Error response format of the plugin
            wp_die('error, ' . esc_html($message));
        }

        $this->entry->set_isspam(true);
        if (
            get_option('gwolle_gb-refuse-spam', 'false') === 'true' &&
            function_exists('gwolle_gb_add_message')
        ) {
            gwolle_gb_add_message(
                '<p class="refuse-spam-sfs"><strong>' . $message . '</strong></p>',
                true,
                true
            );
            return false;
        }
        return $this->entry;
    }

    public function allow()
    {
        return $this->entry;
    }
}
