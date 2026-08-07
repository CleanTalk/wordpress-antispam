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
        global $apbct;

        if (!$this->shortcodes_registered) {
            $this->encode->register();
            $this->registerTitleFilters();
            if ( ! $apbct->settings['data__email_decoder_buffer'] ) {
                // If buffer is active, do not run WordPress shortcode replacement -
                // encoder processes apbct_skip_encoding in modifyBufferAfter().
                $this->shortcode_to_exclude->register();
                $this->registerRenderBlockNestingHooks();
            }
            $this->shortcodes_registered = true;
        }
    }

    /**
     * @return void
     */
    private function registerRenderBlockNestingHooks()
    {
        add_filter('pre_render_block', array($this->shortcode_to_exclude, 'pushRenderBlockStack'), 10, 2);
        add_filter('render_block', array($this->shortcode_to_exclude, 'finalizePlaceholderRestoreAfterRenderBlock'), 11);
    }

    /**
     * @return void
     */
    public function registerTitleFilters()
    {
        add_filter('the_title', array($this->shortcode_to_exclude, 'filterTheTitle'), 1000, 2);
        add_filter('single_post_title', array($this->shortcode_to_exclude, 'filterSinglePostTitle'), 20, 2);
        add_filter('document_title_parts', array($this->shortcode_to_exclude, 'filterDocumentTitleParts'), 20);
        add_filter('nav_menu_item_title', array($this->shortcode_to_exclude, 'filterNavMenuItemTitle'), 20, 2);
        add_filter('wp_insert_post_data', array($this->shortcode_to_exclude, 'filterPostDataForSlug'), 10, 2);
        add_action('init', array($this->shortcode_to_exclude, 'primeRawTitleCache'), 1);
        add_filter('render_block', array($this->shortcode_to_exclude, 'restoreEncodedBlockTitlesFilter'), 1000, 3);
        add_filter('render_block_core/page-list', array($this->shortcode_to_exclude, 'restoreEncodedBlockTitlesFilter'), 1000, 3);
        add_filter('render_block_core/navigation', array($this->shortcode_to_exclude, 'restoreEncodedBlockTitlesFilter'), 1000, 3);
        add_filter('render_block_core/post-title', array($this->shortcode_to_exclude, 'restoreEncodedBlockTitlesFilter'), 1000, 3);
    }

    public function __construct(Params $params)
    {
        $this->encode = new EncodeContentSC($params);
        $this->shortcode_to_exclude = new ExcludedEncodeContentSC();
    }

    public function addActionsBeforeModify($hook, $priority = 1)
    {
        add_filter($hook, array($this->encode, 'changeContentBeforeEncoderModify'), $priority);
        add_filter($hook, array($this->shortcode_to_exclude, 'changeContentBeforeEncoderModify'), $priority);
    }

    public function addActionsBeforeModifyEncodeOnly($hook, $priority = 1)
    {
        add_filter($hook, array($this->encode, 'changeContentBeforeEncoderModify'), $priority);
    }

    public function addActionsAfterModify($hook, $priority = 999)
    {
        add_filter($hook, array($this->encode, 'changeContentAfterEncoderModify'), $priority);
        add_filter($hook, array($this->shortcode_to_exclude, 'changeContentAfterEncoderModify'), $priority);
    }

    public function addActionsAfterModifyEncodeOnly($hook, $priority = 999)
    {
        add_filter($hook, array($this->encode, 'changeContentAfterEncoderModify'), $priority);
    }

    /**
     * Prepare output buffer content before ContactsEncoder::modifyContent().
     *
     * @param string $buffer
     *
     * @return string
     */
    public function modifyBufferBefore($buffer)
    {
        $this->encode->resetShortcodeReplacements();
        $this->shortcode_to_exclude->resetShortcodeReplacements();
        $buffer = $this->shortcode_to_exclude->changeContentBeforeEncoderModify($buffer);

        return $this->encode->changeContentBeforeEncoderModify($buffer);
    }

    /**
     * Finalize output buffer content after ContactsEncoder::modifyContent().
     *
     * @param string $buffer
     *
     * @return string
     */
    public function modifyBufferAfter($buffer)
    {
        global $apbct;

        $buffer = $this->encode->changeContentAfterEncoderModify($buffer);

        if ( $apbct->settings['data__email_decoder_buffer'] ) {
            $buffer = $this->shortcode_to_exclude->finalizeBufferAfterEncoding($buffer);
            $apbct->buffer = $buffer;

            return $buffer;
        }

        return $this->shortcode_to_exclude->changeContentAfterEncoderModify($buffer);
    }
}
