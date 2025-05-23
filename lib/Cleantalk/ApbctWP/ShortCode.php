<?php

namespace Cleantalk\ApbctWP;

/**
 * Base shortcode class.
 *
 * This class provides a foundation for creating WordPress shortcodes.
 * It includes methods for registering and handling shortcode callbacks.
 */
class ShortCode
{
    /**
     * @var string The public name of the shortcode.
     */
    protected $public_name;

    /**
     * Callback function for the shortcode.
     *
     * This method is executed when the shortcode is used in content.
     *
     * @param array $_atts Attributes passed to the shortcode.
     * @param string|null $content The content enclosed by the shortcode, if any.
     * @param string $_tag The name of the shortcode tag.
     * @return string The processed content.
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    protected function callback($_atts, $content, $_tag)
    {
        return (string)$content;
    }

    /**
     * Registers the shortcode with WordPress.
     *
     * This method binds the shortcode tag to the callback function.
     *
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function register()
    {
        add_shortcode($this->public_name, [$this, 'callback']);
    }
}
