<?php

namespace Cleantalk\ApbctWP;

class DebugTab
{
    public function __construct()
    {
        add_filter('apbct_settings_action_buttons', array($this, 'addDebugTab'), 50, 1);
    }

    public function addDebugTab($links)
    {
        if ( is_array($links) ) {
            $debug_link    = '<a href="#" class="ct_support_link" onclick="apbct_show_hide_elem(\'apbct_debug_tab\')">' .
                       __('Debug', 'cleantalk-spam-protect') . '</a>';
            $links[] = $debug_link;
        }

        return $links;
    }

    public static function debugTabOutput()
    {
        return '';
    }
}
