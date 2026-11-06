<?php

namespace Cleantalk\ApbctWP\ContactsEncoder\Shortcodes;

use Cleantalk\ApbctWP\ContactsEncoder\PlaceholderNonce;
use Cleantalk\Common\ContactsEncoder\Helper\ContactsEncoderHelper;

/**
 * Email encoder shortcode base class.
 *
 * This class provides a base implementation for handling email encoder shortcodes.
 * It includes methods for processing shortcodes and modifying content before and after encoding.
 */
class EmailEncoderShortCode extends \Cleantalk\ApbctWP\ShortCode
{
    /**
     * Processing budget of one pass over a content.
     */
    const MAX_SHORTCODE_PAIRS = 200;

    /**
     * How far back the opening tag of an outer shortcode is searched.
     */
    const MAX_OUTER_TAG_LENGTH = 4096;

    /**
     * @var string The public name of the shortcode.
     */
    protected $public_name;

    /**
     * @var string Wrapper template overridden by child classes as the placeholder source.
     */
    protected $exclusion_wrapper = '';

    // Placeholder nonce for ensuring unique placeholders per render.
    protected $placeholder_nonce = '';

    /**
     * @var string[] A title and a textarea are shown as a text: a pair in them is replaced.
     */
    private static $not_rendered_tags = array('script', 'style', 'xmp', 'iframe', 'noembed', 'noframes');

    /**
     * @var string|null Content the markup tokens below were built for.
     */
    private $markup_tokens_content;

    /**
     * @var int[] Starts of the tags of the content. Only numbers are kept: a list of tokens is big on a big page.
     */
    private $tag_starts = array();

    /**
     * @var int[] Exclusive ends of the tags.
     */
    private $tag_ends = array();

    /**
     * @var int[] Starts of the inner text of the elements a browser does not show.
     */
    private $not_rendered_starts = array();

    /**
     * @var int[] Exclusive ends of that text.
     */
    private $not_rendered_ends = array();

    /**
     * Lightweight pre-check to avoid running heavy regexes on every hit.
     *
     * Any valid shortcode pair must contain an opening tag `[public_name` — a cheap
     * strpos() lookup rejects the vast majority of content before preg_* is involved.
     *
     * @param string|null $content
     *
     * @return bool True if the content may contain this shortcode.
     */
    protected function contentMayContainShortcode($content)
    {
        if ( ! is_string($content) || $content === '' || (string)$this->public_name === '' ) {
            return false;
        }

        return strpos($content, '[' . $this->public_name) !== false;
    }

    /**
     * Build a placeholder for the given counter using the child's $exclusion_wrapper as a template.
     * Lazy-initialises the per-render nonce so replacements survive isolated render passes.
     *
     * @param int $counter
     *
     * @return string
     */
    protected function buildPlaceholder($counter)
    {
        if ($this->placeholder_nonce === '') {
            $this->placeholder_nonce = $this->generatePlaceholderNonce();
        }

        $wrapper = (string)$this->exclusion_wrapper;
        $placeholder = preg_replace(
            '/EE\_\d+/',
            'EE_' . (string)$counter . '_' . $this->placeholder_nonce,
            $wrapper
        );

        return is_null($placeholder) ? $wrapper : $placeholder;
    }

    /**
     * Rotates the placeholder nonce to ensure unique placeholders for subsequent renders.
     * @return void
     */
    protected function rotatePlaceholderNonce()
    {
        $this->placeholder_nonce = $this->generatePlaceholderNonce();
    }

    /**
     * Generates a new high-entropy nonce for placeholders.
     * @return string
     */
    protected function generatePlaceholderNonce()
    {
        return PlaceholderNonce::generate();
    }

    /**
     * Process only this encoder's shortcode tags in the content.
     *
     * Must not call do_shortcode() on the full string: untrusted content (e.g. comments)
     * could trigger execution of arbitrary site shortcodes nested in or beside the tag.
     *
     * @param string $content The content to process.
     * @return string The processed content with only this shortcode executed.
     */
    protected function doCallbackAction($content)
    {
        if ( ! is_string($content) || $this->public_name === '' ) {
            return $content;
        }

        if ( ! $this->contentMayContainShortcode($content) ) {
            return $content;
        }

        return $this->replaceShortcodePairs($content, function ($pair) {
            $parsed_atts = shortcode_parse_atts($pair['attributes']);

            return $this->callback(
                is_array($parsed_atts) ? $parsed_atts : array(),
                $pair['inner'],
                $this->public_name
            );
        });
    }

    /**
     * Opening tag of this shortcode. A value can not contain quotes, brackets or tag signs: otherwise a tag
     * started inside a value of a HTML attribute could be finished outside of that HTML tag.
     *
     * @return string
     */
    protected function getOpeningTagPattern()
    {
        return sprintf(
            '/\[%s((?:\s++[a-zA-Z_][\w-]*+(?:\s*+=\s*+(?:"[^"\[\]<>]*+"|\'[^\'\[\]<>]*+\'|[^\s"\'\[\]<>=]++))?+)*+)\s*+\]/',
            preg_quote($this->public_name, '/')
        );
    }

    /**
     * Find shortcode pairs in one pass: an opening tag is paired with the first closing tag after it.
     *
     * @param string $content
     *
     * @return array<int, array{opener_start: int, inner_start: int, closer_start: int, end: int, attributes: string, inner: string, opener: string, closer: string, over_limit: bool}>
     */
    protected function findShortcodePairs($content)
    {
        $pairs = array();

        if ( ! preg_match_all($this->getOpeningTagPattern(), $content, $openers, PREG_OFFSET_CAPTURE) || ! isset($openers[0], $openers[1]) ) {
            return $pairs;
        }

        $closer = '[/' . $this->public_name . ']';
        $cursor = 0;

        foreach ( $openers[0] as $index => $opener ) {
            if ( ! isset($opener[0], $opener[1]) || $opener[1] < $cursor ) {
                continue;
            }

            $inner_start = $opener[1] + strlen($opener[0]);
            $closer_start = strpos($content, $closer, $inner_start);
            if ( $closer_start === false ) {
                break;
            }

            $cursor = $closer_start + strlen($closer);
            $pairs[] = array(
                'opener_start' => $opener[1],
                'inner_start' => $inner_start,
                'closer_start' => $closer_start,
                'end' => $cursor,
                'attributes' => isset($openers[1][$index][0]) ? $openers[1][$index][0] : '',
                'inner' => substr($content, $inner_start, $closer_start - $inner_start),
                'opener' => $opener[0],
                'closer' => $closer,
                // Over the budget a pair is not processed, see replacePairOverLimit().
                'over_limit' => count($pairs) >= self::MAX_SHORTCODE_PAIRS,
            );
        }

        return $pairs;
    }

    /**
     * Replace the pairs that are a text run of the content, all the others stay byte for byte.
     *
     * @param string $content
     * @param callable $callback Receives a pair, returns the replacement.
     *
     * @return string
     */
    protected function replaceShortcodePairs($content, $callback)
    {
        $result = '';
        $position = 0;

        foreach ( $this->findShortcodePairs($content) as $pair ) {
            if (
                $this->isOffsetInsideHtmlTag($content, $pair['opener_start'])
                || $this->isOffsetInsideHtmlTag($content, $pair['closer_start'])
                || $this->isOffsetInsideNotRenderedText($pair['opener_start'])
                || $this->isInsideOtherShortcodeTag($content, $pair['opener_start'])
                || $this->shortcodeContentContainsHtmlTags($pair['inner'])
            ) {
                continue;
            }

            $replacement = $pair['over_limit'] ? $this->replacePairOverLimit($pair) : $callback($pair);
            if ( $replacement === null ) {
                continue;
            }

            $result .= substr($content, $position, $pair['opener_start'] - $position) . $replacement;
            $position = $pair['end'];
        }

        $this->markup_tokens_content = null;
        $this->tag_starts = $this->tag_ends = array();
        $this->not_rendered_starts = $this->not_rendered_ends = array();

        return $position === 0 ? $content : $result . substr($content, $position);
    }

    /**
     * A pair over the budget of a pass: null keeps it as it is.
     *
     * @param array{opener_start: int, inner_start: int, closer_start: int, end: int, attributes: string, inner: string, opener: string, closer: string, over_limit: bool} $pair
     *
     * @return string|null
     * @psalm-suppress PossiblyUnusedParam
     */
    protected function replacePairOverLimit($pair)
    {
        return null;
    }

    /**
     * A placeholder hides quotes from the parser of the outer shortcode, which builds its attributes later.
     *
     * @param string $content
     * @param int $offset Offset of the `[` of the checked shortcode.
     *
     * @return bool True for `[name attr="[apbct_...`.
     */
    protected function isInsideOtherShortcodeTag($content, $offset)
    {
        $outer = $offset > 0 ? strrpos(substr($content, max(0, $offset - self::MAX_OUTER_TAG_LENGTH), $offset - max(0, $offset - self::MAX_OUTER_TAG_LENGTH)), '[') : false;
        if ( $outer === false ) {
            return false;
        }

        $outer += max(0, $offset - self::MAX_OUTER_TAG_LENGTH);
        $between = substr($content, $outer, $offset - $outer);

        return strpos($between, ']') === false && (bool)preg_match('/^\[[a-zA-Z_][\w-]*\s[^=]*=/', $between);
    }

    /**
     * Modifies the content before the encoder processes it.
     *
     * @param string $content The content to modify.
     * @return string The modified content.
     * @psalm-suppress PossiblyUnusedMethod
     */
    protected function changeContentBeforeEncoderModify($content = '')
    {
        return $content;
    }

    /**
     * Modifies the content after the encoder processes it.
     *
     * @param string $content The content to modify.
     * @return string The modified content.
     * @psalm-suppress PossiblyUnusedMethod
     */
    protected function changeContentAfterEncoderModify($content = '')
    {
        return $content;
    }

    /**
     * Checks whether any shortcode occurrence is located inside an HTML tag.
     *
     * This validation is used to prevent shortcode extraction from HTML
     * attribute contexts such as:
     *
     * <a title="[apbct_encode_data]...[/apbct_encode_data]">
     *
     * Processing shortcodes inside HTML tags may lead to malformed markup
     * after WordPress content filters (e.g. wptexturize()) mutate surrounding
     * content. Such mutations may potentially lead to attribute injection or
     * mutation-XSS issues.
     *
     * The method scans all opening and closing shortcode tags and verifies
     * whether their offsets are located between an unclosed "<" and ">" pair.
     *
     * @param string $content The content to validate.
     *
     * @return bool True if any shortcode boundary is detected inside an HTML tag,
     *              false otherwise.
     */
    protected function isShortcodeInsideHtmlTag($content)
    {
        // is_string() is repeated here for Psalm: it cannot infer the type narrowing
        // that happens inside contentMayContainShortcode().
        if ( ! is_string($content) || ! $this->contentMayContainShortcode($content) ) {
            return false;
        }

        preg_match_all(
            sprintf(
                '/\[\/?%s(?:\s[^\]]*)?\]/', //supports sc attributes(!)
                preg_quote($this->public_name, '/')
            ),
            $content,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        if (isset($matches[0])) {
            foreach ($matches[0] as $match) {
                $offset = $match[1] ?? null;

                if ($offset === null) {
                    continue;
                }

                if ($this->isOffsetInsideHtmlTag($content, $offset)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Skip shortcode pairs whose inner content contains HTML tags.
     *
     * In buffer mode the lazy [tag]...[/tag] match can pair an opener in one
     * fragment with a closer in another and swallow everything between them
     * (comments, articles, any markup). A legitimate shortcode wraps text only.
     *
     * @param string $content Inner shortcode content.
     *
     * @return bool
     */
    protected function shortcodeContentContainsHtmlTags($content)
    {
        if ( ! is_string($content) || $content === '' ) {
            return false;
        }

        // Any "less than" sign counts: a comment or a tag without its end is not matched by a tag pattern.
        return strpos($content, '<') !== false || strpos($content, '-->') !== false;
    }

    /**
     * Determines whether a given character offset is located inside an HTML tag.
     *
     * Tags and comments are found once per content, each offset costs a binary search only.
     *
     * @param string $content The full content string.
     * @param int    $offset  Character offset to validate.
     *
     * @return bool True if the offset is located inside an HTML tag,
     *              false otherwise.
     */
    public function isOffsetInsideHtmlTag($content, $offset)
    {
        if ( $this->markup_tokens_content !== $content ) {
            $this->markup_tokens_content = $content;
            $this->indexTags($content);
        }

        return ContactsEncoderHelper::findRange($this->tag_starts, $this->tag_ends, $offset) !== false;
    }

    /**
     * Text of a script is a string of a program, not a text of the page. Runs after isOffsetInsideHtmlTag() for this content.
     *
     * @param int $offset
     *
     * @return bool
     */
    private function isOffsetInsideNotRenderedText($offset)
    {
        return ContactsEncoderHelper::findRange($this->not_rendered_starts, $this->not_rendered_ends, $offset) !== false;
    }

    /**
     * The scanner goes from a raw text tag right to its end: the next token is the closing one.
     *
     * @param string $content
     *
     * @return void
     */
    private function indexTags($content)
    {
        $this->tag_starts = $this->tag_ends = array();
        $this->not_rendered_starts = $this->not_rendered_ends = array();
        $open = false;

        ContactsEncoderHelper::walkMarkup($content, /** @param array{start: int, end: int, name: string, closing: bool, attributes: array<int, array{0: string, 1: int, 2: int, 3: int, 4: int}>} $token */ function ($token) use (&$open) {
            if ( $open !== false ) {
                $this->not_rendered_starts[] = $open;
                $this->not_rendered_ends[] = $token['start'];
                $open = false;
            }

            $this->tag_starts[] = $token['start'];
            $this->tag_ends[] = $token['end'];

            if ( ! $token['closing'] && in_array($token['name'], self::$not_rendered_tags, true) ) {
                $open = $token['end'];
            }
        });

        if ( $open !== false ) {
            $this->not_rendered_starts[] = $open;
            $this->not_rendered_ends[] = strlen($content);
        }
    }
}
