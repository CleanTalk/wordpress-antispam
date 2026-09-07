<?php

namespace Cleantalk\ApbctWP\ContactsEncoder\Shortcodes;

class ExcludedEncodeContentSC extends EmailEncoderShortCode
{
    protected $public_name = 'apbct_skip_encoding';

    /**
     * @var array<string, string> Placeholder => original shortcode inner content.
     */
    public $shortcode_replacements = array();

    /**
     * @var string Wrapper used to mark content excluded from encoding.
     */
    public $exclusion_wrapper = '%%APBCT_SHORT_CODE_SKIP_EE_0%%';

    /**
     * @var int Counter for shortcode placeholders.
     */
    private $shortcode_counter = 0;

    /**
     * @var string[] Active render_block stack.
     */
    private $render_block_stack = array();

    /**
     * @var array<int, string> Raw post titles captured before encoder mutates post objects.
     */
    private static $raw_titles_cache = array();

    /**
     * Callback for the shortcode
     *
     * @param $_atts array Not used here
     * @param $content string|null Encoded content like `<span data-original-string='abc'>***@***.***</span>`
     * @param $_tag string Not used here
     *
     * @return string Decoded content like `abc@abc.com`
     */
    public function callback($_atts, $content, $_tag)
    {
        if ( ! $content ) {
            return $content;
        }

        global $apbct;

        if ( ! $apbct->settings['data__email_decoder_buffer'] ) {
            return $this->createPlaceholder($content);
        }

        // Pattern to get data-original-string attribute
        $pattern = '/data-original-string=(["\'])(.*?)\1/';
        preg_match($pattern, $content, $matches);

        if (isset($matches[2])) {
            $encoder = apbctGetContactsEncoder();
            $decoded_data = $encoder->decodeContactData([$matches[2]]);
            if ( $decoded_data && is_array($decoded_data) ) {
                return esc_html(current($decoded_data));
            }
        }

        return wp_kses_post($content);
    }

    /**
     * Replace skip-encoding shortcodes with placeholders before ContactsEncoder::modifyContent().
     *
     * @param string|null $content Content string, or null when hooked to get_header/get_footer actions.
     *
     * @return string
     * @psalm-suppress NullableReturnStatement
     */
    public function changeContentBeforeEncoderModify($content)
    {
        // get_header / get_footer pass null as the template name via do_action().
        if ( ! is_string($content) ) {
            return $content;
        }

        if ($this->isShortcodeInsideHtmlAttribute($content)) {
            return $content;
        }

        $pattern = '/\[apbct_skip_encoding\](.*?)\[\/apbct_skip_encoding\]/s';

        return preg_replace_callback($pattern, function ($matches) {
            if (isset($matches[1])) {
                return $this->createPlaceholder($matches[1]);
            }

            return isset($matches[0]) ? $matches[0] : '';
        }, $content);
    }

    /**
     * This method runs at the end of Contacts Encoder and tries to process unprocessed shortcodes
     * The unprocessed shortcodes may be only in `the_title` hook
     *
     * @param string|null $content Content string, or null when hooked to get_header/get_footer actions.
     *
     * @return string Replaces $apbct->buffer by probably modified content or just return probably modified $content
     * @psalm-suppress NullableReturnStatement
     */
    public function changeContentAfterEncoderModify($content)
    {
        // get_header / get_footer pass null as the template name via do_action().
        if ( ! is_string($content) ) {
            return $content;
        }

        global $apbct;

        if ( ! $apbct->settings['data__email_decoder_buffer'] ) {
            if ( ! $this->shouldDeferPlaceholderRestore() ) {
                $content = $this->restorePlaceholders($content);
            }

            $content = $this->processSkipEncodingShortcodes($content);

            // widget_block_content (and similar hooks) re-run modifyContent() on already
            // rendered page-list HTML and encode skip titles again — restore after that.
            return $this->restorePageListLinkTitlesInHtml($content);
        }

        if ( $apbct->settings['data__email_decoder_buffer'] ) {
            if ( $this->getCurrentAction() !== 'shutdown' ) {
                return $content;
            }
            if ( $content === '' ) {
                $content = $apbct->buffer;
            }
        }

        // Skip processing if shortcode is inside an HTML attribute to prevent attribute injection
        if ($this->isShortcodeInsideHtmlAttribute($content)) {
            if ( $apbct->settings['data__email_decoder_buffer'] ) {
                $apbct->buffer = $content;
            }
            return $content;
        }

        $content = $this->restorePlaceholders($content);

        $result = $this->processSkipEncodingShortcodes($content);

        if ( $apbct->settings['data__email_decoder_buffer'] ) {
            $apbct->buffer = $result;
        }

        return $result;
    }

    /**
     * @param string $content
     *
     * @return string
     */
    protected function processSkipEncodingShortcodes($content)
    {
        if ( ! is_string($content) ) {
            return '';
        }

        if ($this->isShortcodeInsideHtmlAttribute($content)) {
            return $content;
        }

        $pattern = '/\[apbct_skip_encoding\](.*?)\[\/apbct_skip_encoding\]/s';

        return preg_replace_callback($pattern, function ($matches) {
            if ( isset($matches[1]) ) {
                return $this->callback([], $matches[1], '');
            }
            /** @psalm-suppress PossiblyUndefinedIntArrayOffset */
            return $matches[0];
        }, $content);
    }

    /**
     * @param string $content
     *
     * @return string
     */
    protected function restorePlaceholders($content)
    {
        global $apbct;
        $content = $content === null ? '' : $content;

        foreach ($this->shortcode_replacements as $placeholder => $original) {
            $original = wp_kses_post($original);
            //fix for wpautop when encoder is disabled
            if ( !$apbct->settings['data__email_decoder'] ) {
                $original = wpautop($original);
            }
            $content = str_replace($placeholder, $original, $content);
        }

        $this->resetShortcodeReplacements();

        return $content;
    }

    /**
     * Track currently rendered blocks to restore placeholders after the last encoding pass.
     *
     * @param mixed $pre_render
     * @param array $block
     *
     * @return mixed
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function pushRenderBlockStack($pre_render, $block)
    {
        if ( $pre_render !== null ) {
            return $pre_render;
        }

        $this->render_block_stack[] = isset($block['blockName']) ? (string)$block['blockName'] : '';

        return $pre_render;
    }

    /**
     * Restore placeholders once the outermost render_block finished ContactsEncoder::modifyContent().
     *
     * @param string $content
     *
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function finalizePlaceholderRestoreAfterRenderBlock($content)
    {
        array_pop($this->render_block_stack);

        if ( ! empty($this->render_block_stack) ) {
            return $content;
        }

        if ( function_exists('doing_filter')
            && ( doing_filter('the_content') || doing_filter('widget_block_content') )
        ) {
            return $content;
        }

        if ( strpos($content, 'APBCT_SHORT_CODE_SKIP') !== false ) {
            $content = $this->restorePlaceholders($content);
        }

        return $content;
    }

    /**
     * Keep placeholders until the outermost render_block finishes encoding.
     *
     * @return bool
     */
    protected function shouldDeferPlaceholderRestore()
    {
        if ( $this->getCurrentAction() === 'render_block' ) {
            return true;
        }

        return ! empty($this->render_block_stack);
    }

    /**
     * @param string $content
     *
     * @return string
     */
    protected function createPlaceholder($content)
    {
        $placeholder = $this->buildPlaceholder($this->shortcode_counter++);
        $this->shortcode_replacements[$placeholder] = $content;

        return $placeholder;
    }

    /**
     * @return void
     */
    public function resetShortcodeReplacements()
    {
        $this->shortcode_replacements = array();
        $this->shortcode_counter = 0;
        $this->rotatePlaceholderNonce();
    }

    /**
     * @param string $content
     * @param array $block
     *
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function restorePageListLinkTitles($content, $block)
    {
        $block_name = isset($block['blockName']) ? $block['blockName'] : '';
        if ( ! in_array($block_name, array('core/page-list', 'core/navigation'), true) ) {
            return $content;
        }

        return $this->restorePageListLinkTitlesInHtml($content);
    }

    /**
     * @param string $content
     * @param array $_parsed_block
     * @param object|null $block
     *
     * @return string
     */
    public function restoreEncodedBlockTitlesFilter($content, $_parsed_block, $block = null)
    {
        if ( $this->isBufferMode() ) {
            return $content;
        }

        $content = $this->restorePageListLinkTitlesInHtml($content);

        return $this->restorePostTitleInHtml($content, $_parsed_block, $block);
    }

    /**
     * @param string $content
     * @param array $_parsed_block
     *
     * @return string
     * @deprecated Use restoreEncodedBlockTitlesFilter().
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function restorePageListLinkTitlesFilter($content, $_parsed_block)
    {
        return $this->restoreEncodedBlockTitlesFilter($content, $_parsed_block);
    }

    /**
     * @param string $content
     *
     * @return string
     */
    protected function restorePageListLinkTitlesInHtml($content)
    {
        if ( strpos($content, '<a') === false ) {
            return $content;
        }

        if (
            strpos($content, 'apbct-email-encoder') === false
            && strpos($content, '[apbct_skip_encoding]') === false
            && strpos($content, '%%APBCT_SHORT_CODE_SKIP') === false
        ) {
            return $content;
        }

        return preg_replace_callback(
            '/(<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>)(.*?)(<\/a>)/is',
            function ($matches) {
                if ( ! isset($matches[0], $matches[1], $matches[2], $matches[3], $matches[4]) ) {
                    return isset($matches[0]) ? $matches[0] : '';
                }

                if (
                    strpos($matches[3], 'apbct-email-encoder') === false
                    && strpos($matches[3], '[apbct_skip_encoding]') === false
                ) {
                    return $matches[0];
                }

                $post_id = $this->resolvePostIdFromHref($matches[2]);
                if ( ! $post_id ) {
                    return $matches[0];
                }

                $title = $this->getPrimedRawPostTitle($post_id);
                if ( $title === '' ) {
                    $title = $this->getRawPostTitle($post_id);
                }

                if ( ! is_string($title) || $title === '' || strpos($title, '[apbct_skip_encoding]') === false ) {
                    return $matches[0];
                }

                return $matches[1] . esc_html($this->processTitleString($title)) . $matches[4];
            },
            $content
        );
    }

    /**
     * Resolve post ID from an href used in page-list / navigation links.
     *
     * @param string $href
     *
     * @return int
     */
    protected function resolvePostIdFromHref($href)
    {
        if ( ! is_string($href) || $href === '' ) {
            return 0;
        }

        $post_id = url_to_postid($href);
        if ( $post_id ) {
            return (int)$post_id;
        }

        $path = wp_parse_url($href, PHP_URL_PATH);
        if ( ! is_string($path) || $path === '' ) {
            return 0;
        }

        $path = trim($path, '/');
        if ( $path === '' ) {
            return 0;
        }

        $page = get_page_by_path($path);
        if ( $page instanceof \WP_Post ) {
            return (int)$page->ID;
        }

        foreach ( self::$raw_titles_cache as $cached_id => $_title ) {
            $permalink = get_permalink((int)$cached_id);
            if ( ! is_string($permalink) || $permalink === '' ) {
                continue;
            }

            if ( untrailingslashit($permalink) === untrailingslashit($href) ) {
                return (int)$cached_id;
            }

            $cached_path = wp_parse_url($permalink, PHP_URL_PATH);
            if ( is_string($cached_path) && trim($cached_path, '/') === $path ) {
                return (int)$cached_id;
            }
        }

        return 0;
    }

    /**
     * Restore post-title block output after nested render_block encoding passes.
     *
     * @param string $content
     * @param array $_parsed_block
     * @param object|null $block
     *
     * @return string
     */
    protected function restorePostTitleInHtml($content, $_parsed_block = array(), $block = null)
    {
        if ( strpos($content, 'wp-block-post-title') === false ) {
            return $content;
        }

        $post_id = $this->resolvePostIdFromBlockContext($_parsed_block, $block);
        if ( ! $post_id ) {
            return $content;
        }

        if (
            strpos($content, '[apbct_skip_encoding]') === false
            && strpos($content, 'apbct-email-encoder') === false
            && $this->getPrimedRawPostTitle($post_id) === ''
        ) {
            return $content;
        }

        $title = $this->getPrimedRawPostTitle($post_id);
        if ( $title === '' ) {
            $title = $this->getRawPostTitle($post_id);
        }

        if ( $title === '' || strpos($title, '[apbct_skip_encoding]') === false ) {
            return $content;
        }

        $processed = $this->processTitleString($title, false);

        return preg_replace_callback(
            '/(<h[1-6]\b[^>]*\bwp-block-post-title\b[^>]*>)(.*?)(<\/h[1-6]>)/is',
            function ($matches) use ($processed) {
                if ( ! isset($matches[0], $matches[1], $matches[2], $matches[3]) ) {
                    return isset($matches[0]) ? $matches[0] : '';
                }

                if (
                    strpos($matches[2], '[apbct_skip_encoding]') === false
                    && strpos($matches[2], 'apbct-email-encoder') === false
                    && strpos($matches[2], '%%APBCT_SHORT_CODE_SKIP') === false
                ) {
                    return $matches[0];
                }

                return $matches[1] . $processed . $matches[3];
            },
            $content
        );
    }

    /**
     * @param array $_parsed_block
     * @param object|null $block
     *
     * @return int
     */
    protected function resolvePostIdFromBlockContext($_parsed_block, $block)
    {
        if ( is_object($block) && isset($block->context['postId']) ) {
            return (int)$block->context['postId'];
        }

        if ( is_singular() ) {
            return get_queried_object_id();
        }

        return 0;
    }

    /**
     * Check whether shortcode is inside an HTML attribute rather than element text.
     *
     * @param string|null $content
     *
     * @return bool
     */
    protected function isShortcodeInsideHtmlAttribute($content)
    {
        if ( ! is_string($content) ) {
            return false;
        }

        preg_match_all(
            '/\[\/?apbct_skip_encoding(?:\s[^\]]*)?\]/',
            $content,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        if ( ! isset($matches[0]) ) {
            return false;
        }

        foreach ($matches[0] as $match) {
            $offset = $match[1] ?? null;

            if ( $offset === null ) {
                continue;
            }

            if ( $this->isOffsetInsideHtmlTag($content, $offset) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove contacts that appear both inside skip-encoding shortcodes and as plain text outside them.
     * Preserves original text/shortcode order (does not move shortcodes to the end).
     *
     * @param string $title
     *
     * @return string
     */
    protected function removeDuplicateContactsOutsideShortcodes($title)
    {
        if ( strpos($title, '[apbct_skip_encoding]') === false ) {
            return $title;
        }

        preg_match_all(
            '/\[apbct_skip_encoding\](.*?)\[\/apbct_skip_encoding\]/s',
            $title,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        if ( empty($matches[0]) || ! isset($matches[1]) ) {
            return $title;
        }

        $protected_contacts = array();
        foreach ( $matches[1] as $inner_match ) {
            $inner = trim($inner_match[0]);
            if ( $inner !== '' ) {
                $protected_contacts[$inner] = true;
            }
        }

        if ( empty($protected_contacts) ) {
            return $title;
        }

        $result = '';
        $offset = 0;

        foreach ( $matches[0] as $shortcode_match ) {
            $shortcode = $shortcode_match[0];
            $position = $shortcode_match[1];
            $text_before = substr($title, $offset, $position - $offset);
            $result .= $this->stripProtectedContactsFromPlainText($text_before, array_keys($protected_contacts));
            $result .= $shortcode;
            $offset = $position + strlen($shortcode);
        }

        $result .= $this->stripProtectedContactsFromPlainText(substr($title, $offset), array_keys($protected_contacts));

        return trim(preg_replace('/\s+/', ' ', $result));
    }

    /**
     * @param string $text
     * @param string[] $contacts
     *
     * @return string
     */
    protected function stripProtectedContactsFromPlainText($text, array $contacts)
    {
        if ( $text === '' || $text === false ) {
            return '';
        }

        foreach ( $contacts as $contact ) {
            $quoted = preg_quote($contact, '/');
            $text = preg_replace('/' . $quoted . '/', '', $text);
        }

        return $text;
    }

    /**
     * Cache titles with skip-encoding shortcodes before frontend encoding mutates post objects.
     *
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function primeRawTitleCache()
    {
        if ( is_admin() ) {
            return;
        }

        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_title LIKE '%[apbct_skip_encoding]%'",
            OBJECT_K
        );

        if ( ! is_array($rows) ) {
            return;
        }

        foreach ( $rows as $post_id => $row ) {
            if (
                isset($row->post_title)
                && is_string($row->post_title)
                && strpos($row->post_title, 'apbct-email-encoder') === false
            ) {
                self::$raw_titles_cache[(int)$post_id] = $row->post_title;
            }
        }
    }

    /**
     * Return raw title from init-time prime cache when this post is known to use skip-encoding.
     *
     * @param int $post_id
     *
     * @return string
     */
    protected function getPrimedRawPostTitle($post_id)
    {
        $post_id = (int) $post_id;

        if (
            $post_id
            && isset(self::$raw_titles_cache[$post_id])
            && strpos(self::$raw_titles_cache[$post_id], 'apbct-email-encoder') === false
        ) {
            return self::$raw_titles_cache[$post_id];
        }

        return '';
    }

    /**
     * Read post title from DB bypassing in-memory mutations during encoding.
     *
     * @param int $post_id
     *
     * @return string
     */
    protected function getRawPostTitle($post_id)
    {
        $post_id = (int) $post_id;

        if ( ! $post_id ) {
            return '';
        }

        $primed_title = $this->getPrimedRawPostTitle($post_id);
        if ( $primed_title !== '' ) {
            return $primed_title;
        }

        global $wpdb;

        $title = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_title FROM {$wpdb->posts} WHERE ID = %d LIMIT 1",
                $post_id
            )
        );

        if ( ! is_string($title) ) {
            return '';
        }

        if ( strpos($title, 'apbct-email-encoder') === false ) {
            self::$raw_titles_cache[$post_id] = $title;
        }

        return $title;
    }

    /**
     * @return bool
     */
    protected function isBufferMode()
    {
        global $apbct;

        return ! empty($apbct->settings['data__email_decoder_buffer']);
    }

    /**
     * Keep shortcode tags in title during render; buffer mode encodes on shutdown.
     *
     * @param string $title
     *
     * @return string
     */
    protected function prepareTitleForBufferMode($title)
    {
        if ( strpos($title, '[apbct_skip_encoding]') === false ) {
            return $title;
        }

        return $this->removeDuplicateContactsOutsideShortcodes($title);
    }

    /**
     * Rebuild encoded titles in the full output buffer after buffer-mode encoding.
     *
     * @param string $buffer
     *
     * @return string
     */
    public function restoreEncodedTitlesInBuffer($buffer)
    {
        if ( ! is_string($buffer) || $buffer === '' ) {
            return $buffer;
        }

        $buffer = $this->restoreDocumentTitleInHtml($buffer);
        $buffer = $this->restorePageListLinkTitlesInHtml($buffer);

        return $this->restorePostTitleInHtml($buffer);
    }

    /**
     * Finalize output buffer after ContactsEncoder::modifyContent() in buffer mode.
     *
     * @param string $buffer
     *
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function finalizeBufferAfterEncoding($buffer)
    {
        if ( ! is_string($buffer) || $buffer === '' ) {
            return $buffer;
        }

        if ( ! $this->isShortcodeInsideHtmlAttribute($buffer) ) {
            $buffer = $this->restorePlaceholders($buffer);
            $buffer = $this->processSkipEncodingShortcodes($buffer);
        }

        return $this->restoreEncodedTitlesInBuffer($buffer);
    }

    /**
     * Restore document <title> after buffer-mode encoding.
     *
     * @param string $content
     *
     * @return string
     */
    protected function restoreDocumentTitleInHtml($content)
    {
        if ( strpos($content, '<title') === false ) {
            return $content;
        }

        $post_id = is_singular() ? get_queried_object_id() : 0;
        if ( ! $post_id ) {
            return $content;
        }

        if (
            strpos($content, '[apbct_skip_encoding]') === false
            && strpos($content, 'apbct-email-encoder') === false
            && strpos($content, '%%APBCT_SHORT_CODE_SKIP') === false
            && $this->getPrimedRawPostTitle($post_id) === ''
        ) {
            return $content;
        }

        $raw_title = $this->getPrimedRawPostTitle($post_id);
        if ( $raw_title === '' ) {
            $raw_title = $this->getRawPostTitle($post_id);
        }

        if ( $raw_title === '' || strpos($raw_title, '[apbct_skip_encoding]') === false ) {
            return $content;
        }

        $processed = esc_html($this->processTitleString($raw_title));

        return preg_replace_callback(
            '/(<title[^>]*>)(.*?)(<\/title>)/is',
            function ($matches) use ($processed) {
                if ( ! isset($matches[0], $matches[1], $matches[2], $matches[3]) ) {
                    return isset($matches[0]) ? $matches[0] : '';
                }

                if (
                    strpos($matches[2], '[apbct_skip_encoding]') === false
                    && strpos($matches[2], 'apbct-email-encoder') === false
                    && strpos($matches[2], '%%APBCT_SHORT_CODE_SKIP') === false
                ) {
                    return $matches[0];
                }

                $suffix = '';
                if ( preg_match('/^(.+?)(\s(?:&#8211;|&ndash;|–|-)\s.+)$/u', $matches[2], $parts) && isset($parts[2]) ) {
                    $suffix = $parts[2];
                }

                return $matches[1] . $processed . $suffix . $matches[3];
            },
            $content,
            1
        );
    }

    /**
     * Run callback without mutating shared placeholder state used by content hooks.
     *
     * @param callable $callback
     *
     * @return mixed
     */
    protected function withIsolatedShortcodeState($callback)
    {
        $saved_replacements = $this->shortcode_replacements;
        $saved_counter = $this->shortcode_counter;

        $this->resetShortcodeReplacements();

        try {
            return $callback();
        } finally {
            $this->shortcode_replacements = $saved_replacements;
            $this->shortcode_counter = $saved_counter;
        }
    }

    /**
     * Process title strings where WordPress does not run the the_title filter.
     *
     * @param string $title
     * @param bool $strip_html
     *
     * @return string
     */
    public function processTitleString($title, $strip_html = true)
    {
        if ( $title === '' || $title === null ) {
            return (string)$title;
        }

        $result = $this->withIsolatedShortcodeState(function () use ($title) {
            $title = $this->removeDuplicateContactsOutsideShortcodes($title);
            $processed = $this->changeContentBeforeEncoderModify($title);
            $processed = apbctGetContactsEncoder()->modifyContent($processed);
            $processed = $this->restorePlaceholders($processed);

            return $this->processSkipEncodingShortcodes($processed);
        });

        if ( $strip_html ) {
            $result = wp_strip_all_tags($result);
            $result = trim(preg_replace('/\s+/', ' ', $result));
        }

        return $result;
    }

    /**
     * Remove skip-encoding shortcodes before slug generation.
     *
     * @param string $title
     *
     * @return string
     */
    public static function stripShortcodesForSlug($title)
    {
        $title = preg_replace('/\[apbct_skip_encoding\](.*?)\[\/apbct_skip_encoding\]/s', '$1', $title);
        $title = preg_replace('/\[\/?apbct_skip_encoding\]/', '', $title);

        return trim(preg_replace('/\s+/', ' ', $title));
    }

    /**
     * @param string $title
     * @param int    $post_id Optional. Not always passed: themes may call apply_filters('the_title', $title) with 1 arg.
     *
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function filterTheTitle($title, $post_id = 0)
    {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return $title;
        }

        $raw_title = $this->getPrimedRawPostTitle($post_id);
        if ( $raw_title === '' || strpos($raw_title, '[apbct_skip_encoding]') === false ) {
            return $title;
        }

        if ( $this->isBufferMode() ) {
            return $this->prepareTitleForBufferMode($raw_title);
        }

        return $this->processTitleString($raw_title, false);
    }

    /**
     * @param string $title
     * @param \WP_Post $_post
     *
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function filterSinglePostTitle($title, $_post)
    {
        $post_id = is_object($_post) && isset($_post->ID) ? (int)$_post->ID : 0;
        $raw_title = $post_id ? $this->getPrimedRawPostTitle($post_id) : $title;

        if ( $raw_title === '' || strpos($raw_title, '[apbct_skip_encoding]') === false ) {
            return $title;
        }

        if ( $this->isBufferMode() ) {
            return $this->prepareTitleForBufferMode($raw_title);
        }

        return $this->processTitleString($raw_title);
    }

    /**
     * @param array $parts
     *
     * @return array
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function filterDocumentTitleParts($parts)
    {
        if ( empty($parts['title']) ) {
            return $parts;
        }

        $post_id = is_singular() ? get_queried_object_id() : 0;
        $title = $parts['title'];

        if ( $post_id ) {
            $raw_title = $this->getPrimedRawPostTitle($post_id);
            if ( $raw_title !== '' && strpos($raw_title, '[apbct_skip_encoding]') !== false ) {
                $title = $raw_title;
            }
        }

        if ( strpos($title, '[apbct_skip_encoding]') === false ) {
            return $parts;
        }

        if ( $this->isBufferMode() ) {
            $parts['title'] = $this->prepareTitleForBufferMode($title);

            return $parts;
        }

        $parts['title'] = $this->processTitleString($title);

        return $parts;
    }

    /**
     * @param string $title
     * @param \WP_Post $menu_item
     *
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function filterNavMenuItemTitle($title, $menu_item)
    {
        $post_id = 0;
        if ( is_object($menu_item) && isset($menu_item->object, $menu_item->object_id) && $menu_item->object === 'page' ) {
            $post_id = (int)$menu_item->object_id;
        }

        if ( $post_id ) {
            $raw_title = $this->getPrimedRawPostTitle($post_id);
            if ( $raw_title !== '' && strpos($raw_title, '[apbct_skip_encoding]') !== false ) {
                if ( $this->isBufferMode() ) {
                    return $this->prepareTitleForBufferMode($raw_title);
                }

                return $this->processTitleString($raw_title);
            }
        }

        if ( strpos($title, '[apbct_skip_encoding]') === false ) {
            return $title;
        }

        if ( $this->isBufferMode() ) {
            return $this->prepareTitleForBufferMode($title);
        }

        return $this->processTitleString($title);
    }

    /**
     * @param array $data
     * @param array $postarr
     *
     * @return array
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function filterPostDataForSlug($data, $postarr)
    {
        $post_id = isset($postarr['ID']) ? (int)$postarr['ID'] : 0;

        if (
            ! empty($data['post_title'])
            && strpos($data['post_title'], 'apbct-email-encoder') !== false
            && $post_id
            && isset(self::$raw_titles_cache[$post_id])
        ) {
            $data['post_title'] = self::$raw_titles_cache[$post_id];
        }

        if ( empty($data['post_title']) || strpos($data['post_title'], '[apbct_skip_encoding]') === false ) {
            return $data;
        }

        $status = isset($data['post_status']) ? $data['post_status'] : '';
        if ( in_array($status, array('draft', 'pending', 'auto-draft'), true) ) {
            return $data;
        }

        $post_name = isset($data['post_name']) ? $data['post_name'] : '';
        $explicit_slug = isset($postarr['post_name']) && $postarr['post_name'] !== '';
        $clean_slug = sanitize_title(self::stripShortcodesForSlug($data['post_title']));

        if ( $post_name === '' && ! $explicit_slug ) {
            $data['post_name'] = $clean_slug;
        } elseif ( strpos($post_name, 'apbct_skip_encoding') !== false ) {
            $data['post_name'] = $clean_slug;
        }

        return $data;
    }

    protected function getCurrentAction()
    {
        return function_exists('current_action') ? current_action() : null;
    }
}
