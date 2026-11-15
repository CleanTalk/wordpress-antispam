<?php

namespace Cleantalk\ApbctWP\Variables;

class Server extends \Cleantalk\Variables\Server
{
    /**
     * Unlike get(), distinguish a missing variable from an empty or null value.
     *
     * Presence is checked separately from cached values; names follow get()'s uppercase lookup.
     *
     * @param string $name
     * @return bool
     */
    public static function has($name)
    {
        return array_key_exists(strtoupper($name), $_SERVER);
    }

    /**
     * @inheritDoc
     */
    protected function sanitizeDefault($value)
    {
        return sanitize_textarea_field($value);
    }
}
