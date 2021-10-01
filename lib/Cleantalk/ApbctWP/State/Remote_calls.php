<?php

namespace Cleantalk\ApbctWP\State;

use Cleantalk\Common\State\Options;

class Remote_calls extends Options
{
    /**
     * @inheritDoc
     */
    protected function setDefaults()
    {
        return array(
            //Common
            'close_renew_banner' => array('last_call' => 0, 'cooldown' => 0),
            'check_website'      => array('last_call' => 0, 'cooldown' => 0),
            'update_settings'    => array('last_call' => 0, 'cooldown' => 0),

            // Firewall
            'sfw_update'         => array('last_call' => 0, 'cooldown' => 0),
            'sfw_update__worker' => array('last_call' => 0, 'cooldown' => 0),
            'sfw_send_logs'      => array('last_call' => 0, 'cooldown' => 0),

            // Installation
            'update_plugin'      => array('last_call' => 0, 'cooldown' => 0),
            'install_plugin'     => array('last_call' => 0, 'cooldown' => 0),
            'activate_plugin'    => array('last_call' => 0, 'cooldown' => 0),
            'insert_auth_key'    => array('last_call' => 0, 'cooldown' => 0),
            'deactivate_plugin'  => array('last_call' => 0, 'cooldown' => 0),
            'uninstall_plugin'   => array('last_call' => 0, 'cooldown' => 0),

            // debug
            'debug'              => array('last_call' => 0, 'cooldown' => 0),
            'debug_sfw'          => array('last_call' => 0, 'cooldown' => 0),
        );
    }
}
