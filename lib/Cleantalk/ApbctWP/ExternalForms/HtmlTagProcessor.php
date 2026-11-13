<?php

namespace Cleantalk\ApbctWP\ExternalForms;

/**
 * Expose native tag positions for replacements in the original HTML buffer.
 *
 * The WP 6.2-compatible traversal API does not expose a public tag-start accessor.
 * A temporary bookmark provides that position through the protected bookmark data,
 * without accessing private parser fields or depending on the changed end/length fields.
 * Instantiate this adapter only after checking that WP_HTML_Tag_Processor exists.
 */
class HtmlTagProcessor extends \WP_HTML_Tag_Processor
{
    /**
     * Read the current tag's byte offset without advancing the tokenizer.
     *
     * The offset is relative to this processor's input. A same-length masked copy
     * therefore shares offsets with the original page. The temporary bookmark is
     * released immediately so repeated calls do not consume the bookmark limit.
     *
     * @return int Byte offset of the current tag's opening "<".
     * @throws \UnexpectedValueException If the bookmark or its start position is unavailable.
     */
    public function getTagStart(): int
    {
        if ( ! $this->set_bookmark('apbct-form') ) {
            throw new \UnexpectedValueException('Unable to bookmark the external form tag.');
        }

        // The start offset exists in WP 6.2; the span's end/length representation changed in WP 6.5.
        $start = isset($this->bookmarks['apbct-form']->start)
            ? $this->bookmarks['apbct-form']->start
            : null;
        $this->release_bookmark('apbct-form');

        if ( ! is_int($start) ) {
            throw new \UnexpectedValueException('Unable to locate the external form tag.');
        }

        return $start;
    }
}
