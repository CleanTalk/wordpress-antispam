<?php

namespace Cleantalk\ApbctWP\State;

use Cleantalk\Common\State\Options;

class Network_settings extends Options
{
    /**
     * @inheritDoc
     */
    protected function setDefaults()
    {
        return array(
            // Key
            'apikey'                                                        => '',
            'multisite__allow_custom_settings'                              => 1,
            'multisite__work_mode'                                          => 1,
            'multisite__hoster_api_key'                                     => '',

            // White label settings
            'multisite__white_label'                                        => 0,
            'multisite__white_label__plugin_name'                           => 'Anti-Spam by CleanTalk',
            'multisite__use_settings_template'                              => 0,
            'multisite__use_settings_template_apply_for_new'                => 0,
            'multisite__use_settings_template_apply_for_current'            => 0,
            'multisite__use_settings_template_apply_for_current_list_sites' => '',
        );
    }
}
