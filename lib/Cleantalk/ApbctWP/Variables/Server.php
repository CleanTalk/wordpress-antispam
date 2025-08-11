<?php

namespace Cleantalk\ApbctWP\Variables;

class Server extends \Cleantalk\Variables\Server
{
    /**
     * @inheritDoc
     */
    protected function sanitizeDefault($value)
    {
        return sanitize_textarea_field($value);
    }
}
