<?php

namespace Cleantalk\ApbctWP\Variables;

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
