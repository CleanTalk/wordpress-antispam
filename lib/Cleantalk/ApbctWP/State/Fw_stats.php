<?php

namespace Cleantalk\ApbctWP\State;

use Cleantalk\Common\State\Options;

/** @psalm-suppress UnusedClass */
// phpcs:ignore Squiz.Classes.ValidClassName.NotCamelCaps
class Fw_stats extends Options
{
    /**
     * @inheritDoc
     */
    protected function setDefaults()
    {
        return array(
            'firewall_updating'            => false,
            'updating_folder'              => '',
            'firewall_updating_id'         => null,
            'firewall_update_percent'      => 0,
            'firewall_updating_last_start' => 0,
            'last_firewall_updated'        => 0,
            'expected_networks_count'      => 0,
            'expected_ua_count'            => 0,
            'update_mode'                  => 0,
        );
    }
}
