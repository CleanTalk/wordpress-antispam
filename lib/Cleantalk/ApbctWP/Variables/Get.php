<?php

namespace Cleantalk\ApbctWP\Variables;

use Cleantalk\ApbctWP\Validate;

class Get extends \Cleantalk\Variables\Get
{
    /**
     * @inheritDoc
     */
    protected function sanitizeDefault($value)
    {
        return sanitize_textarea_field($value);
    }
}
