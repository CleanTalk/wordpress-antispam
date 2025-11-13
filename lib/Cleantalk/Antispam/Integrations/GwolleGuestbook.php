<?php

namespace Cleantalk\Antispam\Integrations;

class GwolleGuestbook extends IntegrationBase
{
    private $entry;

    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        $this->entry = $argument;

        $processed_post = apply_filters('apbct__filter_post', $_POST);
        $data = ct_gfa_dto($processed_post)->getArray();
        return $data;
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
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
