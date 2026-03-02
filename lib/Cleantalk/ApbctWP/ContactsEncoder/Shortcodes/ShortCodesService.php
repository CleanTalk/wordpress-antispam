<?php

namespace Cleantalk\ApbctWP\ContactsEncoder\Shortcodes;

use Cleantalk\Common\ContactsEncoder\Dto\Params;

/**
 * Init and register shortcodes for EmailEncoder
 */
class ShortCodesService
{
    public $encode;

    public $shortcode_to_exclude;

    public $shortcodes_registered = false;

    /**
     * @return void
     */
    public function registerAll()
    {
        if (!$this->shortcodes_registered) {
            $this->encode->register();
            $this->shortcode_to_exclude->register();
            $this->shortcodes_registered = true;
        }
    }

    public function __construct(Params $params)
    {
        $this->encode = new EncodeContentSC($params);
        $this->shortcode_to_exclude = new ExcludedEncodeContentSC();
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
