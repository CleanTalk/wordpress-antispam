<?php

namespace Cleantalk\Antispam\EmailEncoder\Shortcodes;

/**
 * Init and register shortcodes for EmailEncoder
 */
class ShortCodesService
{
    public $encode;

    public $shortcodes_registered = false;
    /**
     * @return void
     */
    public function registerAll()
    {
        if (!$this->shortcodes_registered) {
            $this->encode->register();
            $this->shortcodes_registered = true;
        }
    }

    public function __construct()
    {
        $this->encode = new EncodeContentSC();
    }

    public function addActionsBeforeModify($hook, $priority = 1)
    {
        add_filter($hook, array($this->encode, 'changeContentBeforeEncoderModify'), $priority);
    }

    public function addActionsAfterModify($hook, $priority = 999)
    {
        add_filter($hook, array($this->encode, 'changeContentAfterEncoderModify'), $priority);
    }
}
