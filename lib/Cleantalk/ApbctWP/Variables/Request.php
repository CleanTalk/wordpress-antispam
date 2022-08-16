<?php

namespace Cleantalk\ApbctWP\Variables;

use Cleantalk\ApbctWP\Validate;

class Request extends \Cleantalk\Variables\Request
{
    /**
     * @inheritDoc
     */
    protected function sanitizeDefault($value)
    {
        return sanitize_textarea_field($value);
    }
}
