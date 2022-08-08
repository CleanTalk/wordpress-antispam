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
        if ( Validate::isUrlencoded($value) ) {
            $value = urldecode($value);
        }
        return sanitize_textarea_field($value);
    }
}
