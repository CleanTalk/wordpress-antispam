<?php

namespace Cleantalk\ApbctWP\Variables;

use Cleantalk\ApbctWP\Validate;

class Post extends \Cleantalk\Variables\Post
{
    /**
     * @inheritDoc
     */
    protected function sanitizeDefault($value)
    {
        return sanitize_textarea_field($value);
    }
}
