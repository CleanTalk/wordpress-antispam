<?php

namespace Cleantalk\ApbctWP\State;

use Cleantalk\Common\State\Options;

/** @psalm-suppress UnusedClass */
// phpcs:ignore Squiz.Classes.ValidClassName.NotCamelCaps
class Network_data extends Options
{
    /**
     * @inheritDoc
     */
    protected function setDefaults()
    {
        return array(
            'key_is_ok'   => 0,
            'moderate'    => 0,
            'valid'       => 0,
            'user_token'  => '',
            'service_id'  => 0,
            'auto_update' => 0,
        );
    }
}
