<?php

namespace Cleantalk\ApbctWP\Variables;

use Cleantalk\ApbctWP\Validate;

class Post extends \Cleantalk\Variables\Post
{
    /**
     * Check key presence even when the submitted value is empty or null.
     *
     * @param string $name
     * @return bool
     */
    public static function has($name)
    {
        return array_key_exists($name, $_POST);
    }

    /**
     * Read original request bytes independently of the sanitized-value cache.
     * Callers must validate the value before using it.
     *
     * WordPress slashes are retained. Missing or null values return an empty string.
     *
     * @param string $name
     * @return mixed
     */
    public static function getRaw($name)
    {
        return isset($_POST[$name]) ? $_POST[$name] : '';
    }

    /**
     * Return the original request fields, including WordPress slashes.
     *
     * No text sanitization or cached field values are applied to this snapshot.
     *
     * @return array
     */
    public static function getRawArray()
    {
        return $_POST;
    }

    /**
     * @inheritDoc
     */
    protected function sanitizeDefault($value)
    {
        return sanitize_textarea_field($value);
    }
}
