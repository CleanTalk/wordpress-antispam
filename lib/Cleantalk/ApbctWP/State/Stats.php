<?php

namespace Cleantalk\ApbctWP\State;

use Cleantalk\Common\State\Options;

class Stats extends Options
{
    /**
     * @inheritDoc
     */
    protected function setDefaults()
    {
        return array(
            'sfw'            => array(
                'sending_logs__timestamp' => 0,
                'last_send_time'          => 0,
                'last_send_amount'        => 0,
                'last_update_time'        => 0,
                'last_update_way'         => '',
                'entries'                 => 0,
                'update_period'           => 14400,
            ),
            'last_sfw_block' => array(
                'time' => 0,
                'ip'   => '',
            ),
            'last_request'   => array(
                'time'   => 0,
                'server' => '',
            ),
            'requests'       => array(
                '0' => array(
                    'amount'       => 1,
                    'average_time' => 0,
                ),
            ),
            'plugin'         => array(
                'install__timestamp'             => 0,
                'activation__timestamp'          => 0,
                'activation_previous__timestamp' => 0,
                'activation__times'              => 0,
            ),
            'cron'           => array(
                'last_start' => 0,
            ),
        );
    }
}
