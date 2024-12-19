<?php

namespace Cleantalk\ApbctWP;

class CleantalkUpgraderSkin extends \WP_Upgrader_Skin
{
    public $upgrader;
    public $done_header;
    public $done_footer;

    /**
     * Holds the result of an upgrade.
     *
     * @since 2.8.0
     * @var string|bool|\WP_Error
     */
    public $result;
    public $options;

    public function header()
    {
    }

    public function footer()
    {
    }

    /**
     * @param string $feedback
     * @param mixed  ...$args  Optional text replacements.
     * @psalm-suppress ParamNameMismatch
     */
    public function feedback($feedback, ...$args)
    {
    }

    /**
     *
     * @param string|\WP_Error $errors
     */
    public function error($errors)
    {
        if ( is_wp_error($errors) ) {
            /** @psalm-suppress PossiblyInvalidMethodCall */
            $this->upgrader->apbct_result = $errors->get_error_code();
        } else {
            $this->upgrader->apbct_result = $this->upgrader->strings[$errors];
        }
    }
}
