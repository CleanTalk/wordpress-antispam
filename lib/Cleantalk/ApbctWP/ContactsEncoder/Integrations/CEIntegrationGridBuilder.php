<?php

namespace Cleantalk\ApbctWP\ContactsEncoder\Integrations;

/**
 * WP Grid Builder compatibility for Contacts Encoder buffer mode (#54940).
 */
class CEIntegrationGridBuilder
{
    /**
     * @var string[]
     */
    private $region_placeholders = array();

    /**
     * @var string
     */
    private $card_inline_css = '';

    /**
     * @var string[]
     */
    private $stylesheet_links = array();

    /**
     * @var bool
     */
    private $style_capture_hooks_added = false;

    /**
     * @param string   $content
     * @param callable $encode_callback
     *
     * @return string
     */
    public function modifyContent($content, callable $encode_callback)
    {
        if ( ! $this->contentHasWpGridBuilder($content) ) {
            return $encode_callback($content);
        }

        $this->prepareAssets($content);
        $content = $this->protectRegions($content);
        $content = $encode_callback($content);
        $content = $this->restoreRegions($content);

        return $this->appendFix($content);
    }

    /**
     * Apply WPGB layout/CSS fix without running email encoding (#54940).
     *
     * @param string $content
     *
     * @return string
     */
    public function applyCompatibilityFix($content)
    {
        if ( ! is_string($content) || $content === '' || ! $this->contentHasWpGridBuilder($content) ) {
            return $content;
        }

        $this->prepareAssets($content);

        return $this->appendFix($content);
    }

    /**
     * @return void
     */
    public function registerStyleCaptureHooks()
    {
        if ( $this->style_capture_hooks_added ) {
            return;
        }

        $this->style_capture_hooks_added = true;
        add_filter('style_loader_tag', array($this, 'captureStyleLoaderTag'), 999, 4);
        add_action('wp_print_footer_scripts', array($this, 'snapshotAssetsFromQueue'), PHP_INT_MAX);
        add_action('shutdown', array($this, 'snapshotAssetsFromQueue'), PHP_INT_MAX - 1);
    }

    /**
     * WordPress style_loader_tag filter callback.
     *
     * @param string $html
     * @param string $handle
     * @param string $href
     * @param string $media
     *
     * @return string
     * @psalm-suppress PossiblyUnusedReturnValue, PossiblyUnusedParam
     */
    public function captureStyleLoaderTag($html, $handle, $href, $media)
    {
        if ( ! is_string($handle) || ! function_exists('wp_styles') ) {
            return $html;
        }

        $wp_styles = wp_styles();

        if ( ! $this->isCardStyleHandle($handle, $wp_styles) ) {
            return $html;
        }

        if ( is_string($href) && $href !== '' && ! $this->isDefaultBundleSrc($href) ) {
            $this->stylesheet_links[$handle] = $this->resolveStyleSrc($href, $wp_styles);
        }

        return $html;
    }

    /**
     * @return void
     */
    public function snapshotAssetsFromQueue()
    {
        if ( ! function_exists('wp_styles') ) {
            return;
        }

        $wp_styles = wp_styles();

        if ( ! is_object($wp_styles) || empty($wp_styles->registered) || ! is_array($wp_styles->registered) ) {
            return;
        }

        foreach ( array_keys($wp_styles->registered) as $handle ) {
            if ( ! is_string($handle) || ! $this->isCardStyleHandle($handle, $wp_styles) ) {
                continue;
            }

            $src = isset($wp_styles->registered[$handle]->src) ? $wp_styles->registered[$handle]->src : '';

            if ( is_string($src) && $src !== '' && ! $this->isDefaultBundleSrc($src) ) {
                $this->stylesheet_links[$handle] = $this->resolveStyleSrc($src, $wp_styles);
            }

            foreach ( array('before', 'after') as $position ) {
                $data = $wp_styles->get_data($handle, $position);

                if ( ! is_array($data) ) {
                    continue;
                }

                foreach ( $data as $piece ) {
                    if ( is_string($piece) && $this->isCardCssChunk($piece) ) {
                        $this->appendCardCssChunk($piece);
                    }
                }
            }
        }
    }

    /**
     * @param string $content
     *
     * @return string
     */
    public function appendFix($content)
    {
        if ( ! $this->contentHasWpGridBuilder($content) ) {
            return $content;
        }

        $fix = '';

        if ( stripos($content, 'apbct-wpgb-opacity-fix') === false ) {
            $fix .=
                '<style id="apbct-wpgb-opacity-fix">'
                . '.wp-grid-builder,.wpgb-layout,.wpgb-main,.wpgb-viewport,.wpgb-wrapper{position:relative}'
                . '.wp-grid-builder.wpgb-enabled{opacity:1!important;visibility:visible!important;pointer-events:auto!important}'
                . '.wpgb-card-media{position:relative;overflow:hidden}'
                . '.wpgb-card-media svg[data-ratio]{display:block;width:100%;height:auto}'
                . '.wpgb-card-media-thumbnail{bottom:0;left:0;overflow:hidden;position:absolute;right:0;top:0}'
                . '.wpgb-card-media-thumbnail a{bottom:0;left:0;position:absolute;right:0;top:0}'
                . '.wpgb-card-media-thumbnail div{background-position:50% 50%;background-repeat:no-repeat;background-size:cover;bottom:0;left:0;position:absolute;right:0;top:0}'
                . '.wpgb-card-media-content-top{left:0;position:absolute;right:0;top:0;z-index:2}'
                . '</style>';
        }

        $style_url = $this->getStyleUrl($content);
        if (
            $style_url !== ''
            && stripos($content, 'wp-grid-builder/public/css/style.css') === false
            && stripos($content, 'apbct-wpgb-styles-css') === false
        ) {
            $fix =
                '<link rel="stylesheet" id="apbct-wpgb-styles-css" href="' . esc_url($style_url) . '" media="all" />'
                . $fix;
        }

        if (
            $this->card_inline_css !== ''
            && stripos($content, 'apbct-wpgb-card-inline-css') !== false
            && ! $this->htmlContainsCardCss($content)
        ) {
            $content = $this->removeInvalidApbctCardCss($content);
        }

        foreach ( $this->stylesheet_links as $handle => $url ) {
            if ( ! is_string($handle) || ! is_string($url) || $url === '' || $this->htmlContainsUrl($content, $url) ) {
                continue;
            }

            $fix .= '<link rel="stylesheet" id="apbct-wpgb-card-css-' . esc_attr($handle) . '" href="'
                . esc_url($url) . '" media="all" />';
        }

        if (
            $this->card_inline_css !== ''
            && stripos($content, 'apbct-wpgb-card-inline-css') === false
            && ! $this->htmlContainsCardCss($content)
        ) {
            $fix .= '<style id="apbct-wpgb-card-inline-css">' . $this->card_inline_css . '</style>';
        }

        if ( $fix === '' ) {
            return $content;
        }

        if ( preg_match('/<\/head>/i', $content) ) {
            return preg_replace('/<\/head>/i', $fix . '</head>', $content, 1);
        }

        return $fix . $content;
    }

    /**
     * @param string $css
     *
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setCardInlineCssForTests($css)
    {
        $this->card_inline_css = $css;
    }

    /**
     * @param string[] $links
     *
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setStylesheetLinksForTests($links)
    {
        $this->stylesheet_links = $links;
    }

    /**
     * @param string $content
     *
     * @return void
     */
    private function prepareAssets($content)
    {
        $this->snapshotAssetsFromQueue();

        $chunks = array();
        $from_html = $this->extractCardCssFromHtml($content);

        if ( $from_html !== '' ) {
            $chunks[] = $from_html;
        }

        $from_queue = $this->collectInlineCssFromQueue();

        if ( $from_queue !== '' ) {
            $chunks[] = $from_queue;
        }

        foreach ( $this->stylesheet_links as $url ) {
            if ( ! is_string($url) || $url === '' || $this->htmlContainsUrl($content, $url) ) {
                continue;
            }

            $file_css = $this->readCssFromSrc($url);

            if ( $file_css !== '' ) {
                $chunks[] = $file_css;
            }
        }

        $this->card_inline_css = $this->mergeCardCssChunks($chunks);
    }

    /**
     * @param string $content
     *
     * @return string
     */
    private function protectRegions($content)
    {
        if (
            stripos($content, 'wpgb') === false
            && stripos($content, 'WP_Grid_Builder') === false
            && stripos($content, 'wp-grid-builder') === false
        ) {
            return $content;
        }

        $this->region_placeholders = array();

        foreach ( array('noscript', 'script', 'style') as $tag ) {
            $previous_content = $content;
            $content = preg_replace_callback(
                '/<' . $tag . '\b[^>]*>.*?<\/' . $tag . '>/is',
                function ($matches) use ($tag) {
                    if ( ! isset($matches[0]) ) {
                        return '';
                    }

                    $should_protect = $tag === 'style'
                        ? $this->isStyleRegion($matches[0])
                        : $this->isRegion($matches[0]);

                    if ( ! $should_protect ) {
                        return $matches[0];
                    }

                    $placeholder = '%%APBCT_WPGB_REGION_' . count($this->region_placeholders) . '%%';
                    $this->region_placeholders[$placeholder] = $matches[0];

                    return $placeholder;
                },
                $content
            );

            if ( ! is_string($content) ) {
                return $previous_content;
            }
        }

        $previous_content = $content;
        $content = preg_replace_callback(
            '/<link\b[^>]*\/?>/is',
            function ($matches) {
                if ( ! isset($matches[0]) || ! $this->isRegion($matches[0]) ) {
                    return isset($matches[0]) ? $matches[0] : '';
                }

                $placeholder = '%%APBCT_WPGB_REGION_' . count($this->region_placeholders) . '%%';
                $this->region_placeholders[$placeholder] = $matches[0];

                return $placeholder;
            },
            $content
        );

        if ( ! is_string($content) ) {
            return $previous_content;
        }

        return $content;
    }

    /**
     * @param string $content
     *
     * @return string
     */
    private function restoreRegions($content)
    {
        if ( $this->region_placeholders === array() ) {
            return $content;
        }

        $content = strtr($content, $this->region_placeholders);
        $this->region_placeholders = array();

        return $content;
    }

    /**
     * @param string $chunk
     *
     * @return bool
     */
    private function isRegion($chunk)
    {
        return stripos($chunk, 'wpgb') !== false
            || stripos($chunk, 'WP_Grid_Builder') !== false
            || stripos($chunk, 'wp-grid-builder') !== false
            || (is_string($chunk) && preg_match('#/wp-content/uploads/wp-grid-builder/#i', $chunk) === 1);
    }

    /**
     * @param string $chunk
     *
     * @return bool
     */
    private function isStyleRegion($chunk)
    {
        if ( $this->isRegion($chunk) ) {
            return true;
        }

        return is_string($chunk)
            && preg_match('/\.wpgb-(?:block|card)-\d+/i', $chunk) === 1;
    }

    /**
     * @param string $content
     *
     * @return bool
     */
    private function contentHasWpGridBuilder($content)
    {
        return stripos($content, 'wp-grid-builder') !== false || stripos($content, 'wpgb') !== false;
    }

    /**
     * @param string $css
     *
     * @return void
     */
    private function appendCardCssChunk($css)
    {
        if ( ! is_string($css) || ! $this->isCardCssChunk($css) ) {
            return;
        }

        $css = trim($css);

        if ( $css === '' ) {
            return;
        }

        if ( $this->card_inline_css === '' ) {
            $this->card_inline_css = $css;

            return;
        }

        if ( stripos($this->card_inline_css, $css) !== false ) {
            return;
        }

        $this->card_inline_css .= "\n" . $css;
    }

    /**
     * @param string[] $chunks
     *
     * @return string
     */
    private function mergeCardCssChunks($chunks)
    {
        $merged = array();

        foreach ( $chunks as $chunk ) {
            if ( ! is_string($chunk) || ! $this->isCardCssChunk($chunk) ) {
                continue;
            }

            $chunk = trim($chunk);

            if ( $chunk !== '' ) {
                $merged[] = $chunk;
            }
        }

        if ( $merged === array() ) {
            return $this->isCardCssChunk($this->card_inline_css)
                ? trim($this->card_inline_css)
                : '';
        }

        if (
            $this->card_inline_css !== ''
            && $this->isCardCssChunk($this->card_inline_css)
        ) {
            $merged[] = trim($this->card_inline_css);
        }

        return implode("\n", array_unique($merged));
    }

    /**
     * @param string $css
     *
     * @return bool
     */
    private function isCardCssChunk($css)
    {
        return is_string($css)
            && preg_match('/(?:^|[\s{,>+~])\.wpgb-(?:block|card)-\d+/i', $css) === 1;
    }

    /**
     * @param string $src
     *
     * @return bool
     */
    private function isDefaultBundleSrc($src)
    {
        return stripos($src, '/public/css/style.css') !== false
            || stripos($src, '/public/css/vendors/') !== false
            || stripos($src, 'wpgb-head') !== false;
    }

    /**
     * @param string $handle
     * @param object $wp_styles
     *
     * @return bool
     */
    private function isCardStyleHandle($handle, $wp_styles)
    {
        if ( ! $this->isStyleHandle($handle, $wp_styles) ) {
            return false;
        }

        if ( stripos($handle, 'wpgb-head') !== false ) {
            return false;
        }

        $src = isset($wp_styles->registered[$handle]->src) ? $wp_styles->registered[$handle]->src : '';

        if ( is_string($src) && $src !== '' ) {
            if ( $this->isDefaultBundleSrc($src) ) {
                return false;
            }

            if ( stripos($src, 'wp-grid-builder') !== false ) {
                return true;
            }
        }

        foreach ( array('before', 'after') as $position ) {
            $data = $wp_styles->get_data($handle, $position);

            if ( ! is_array($data) ) {
                continue;
            }

            foreach ( $data as $piece ) {
                if ( is_string($piece) && $this->isCardCssChunk($piece) ) {
                    return true;
                }
            }
        }

        return preg_match('/wpgb-(?:card|grid|style|content)/i', $handle) === 1;
    }

    /**
     * @param string $src
     * @param object $wp_styles
     *
     * @return string
     */
    private function resolveStyleSrc($src, $wp_styles)
    {
        if ( ! is_string($src) || $src === '' ) {
            return '';
        }

        if ( preg_match('#^https?://#i', $src) ) {
            return $src;
        }

        if ( strpos($src, '//') === 0 ) {
            return (is_ssl() ? 'https:' : 'http:') . $src;
        }

        if ( isset($wp_styles->base_url) && is_string($wp_styles->base_url) && $wp_styles->base_url !== '' ) {
            return $wp_styles->base_url . ltrim($src, '/');
        }

        if ( function_exists('site_url') ) {
            return site_url($src);
        }

        return $src;
    }

    /**
     * @param string $url
     *
     * @return string
     */
    private function readCssFromSrc($url)
    {
        if ( ! is_string($url) || $url === '' || ! function_exists('wp_parse_url') ) {
            return '';
        }

        $path = wp_parse_url($url, PHP_URL_PATH);

        if (
            ! is_string($path)
            || $path === ''
            || strpos($path, '..') !== false
            || preg_match('/\.css$/i', $path) !== 1
            || ! defined('ABSPATH')
        ) {
            return '';
        }

        $local = ABSPATH . ltrim($path, '/');
        $resolved = realpath($local);
        $abspath = realpath(ABSPATH);
        $abspath_prefix = is_string($abspath) ? trailingslashit($abspath) : '';

        if (
            $resolved === false
            || $abspath === false
            || $abspath_prefix === ''
            || strpos($resolved, $abspath_prefix) !== 0
            || ! is_readable($resolved)
        ) {
            return '';
        }

        $css = file_get_contents($resolved);

        if ( ! is_string($css) || ! $this->isCardCssChunk($css) ) {
            return '';
        }

        return trim($css);
    }

    /**
     * @param string $content
     * @param string $url
     *
     * @return bool
     */
    private function htmlContainsUrl($content, $url)
    {
        if ( ! is_string($content) || ! is_string($url) || $url === '' ) {
            return false;
        }

        if ( stripos($content, $url) !== false ) {
            return true;
        }

        $path = function_exists('wp_parse_url') ? wp_parse_url($url, PHP_URL_PATH) : parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' && stripos($content, $path) !== false;
    }

    /**
     * @param string $content
     *
     * @return string
     */
    private function extractCardCssFromHtml($content)
    {
        if ( ! preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $content, $matches) || ! isset($matches[1]) ) {
            return '';
        }

        $chunks = array();

        foreach ( $matches[1] as $css ) {
            if ( ! $this->isCardCssChunk($css) ) {
                continue;
            }

            $css = trim($css);

            if ( $css !== '' ) {
                $chunks[] = $css;
            }
        }

        if ( $chunks === array() ) {
            return '';
        }

        return implode("\n", array_unique($chunks));
    }

    /**
     * @return string
     */
    private function collectInlineCssFromQueue()
    {
        if ( ! function_exists('wp_styles') ) {
            return '';
        }

        $wp_styles = wp_styles();

        if ( ! is_object($wp_styles) || empty($wp_styles->registered) || ! is_array($wp_styles->registered) ) {
            return '';
        }

        $chunks = array();

        foreach ( array_keys($wp_styles->registered) as $handle ) {
            if ( ! is_string($handle) || ! $this->isStyleHandle($handle, $wp_styles) ) {
                continue;
            }

            foreach ( array('before', 'after') as $position ) {
                $data = $wp_styles->get_data($handle, $position);

                if ( ! is_array($data) ) {
                    continue;
                }

                foreach ( $data as $piece ) {
                    if ( is_string($piece) && $this->isCardCssChunk($piece) ) {
                        $chunks[] = trim($piece);
                    }
                }
            }
        }

        if ( $chunks === array() ) {
            return '';
        }

        return implode("\n", array_unique($chunks));
    }

    /**
     * @param string $handle
     * @param object $wp_styles
     *
     * @return bool
     */
    private function isStyleHandle($handle, $wp_styles)
    {
        if ( stripos($handle, 'wpgb') !== false || stripos($handle, 'wp-grid-builder') !== false ) {
            return true;
        }

        if (
            isset($wp_styles->registered[$handle]->src)
            && is_string($wp_styles->registered[$handle]->src)
            && stripos($wp_styles->registered[$handle]->src, 'wp-grid-builder') !== false
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param string $content
     *
     * @return bool
     */
    private function htmlContainsCardCss($content)
    {
        if ( ! preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $content, $matches) || ! isset($matches[1]) ) {
            return false;
        }

        foreach ( $matches[1] as $css ) {
            if ( $this->isCardCssChunk($css) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $content
     *
     * @return string
     */
    private function removeInvalidApbctCardCss($content)
    {
        if ( ! preg_match('/<style\b[^>]*id=["\']apbct-wpgb-card-inline-css["\'][^>]*>.*?<\/style>/is', $content) ) {
            return $content;
        }

        return preg_replace(
            '/<style\b[^>]*id=["\']apbct-wpgb-card-inline-css["\'][^>]*>.*?<\/style>/is',
            '',
            $content,
            1
        );
    }

    /**
     * @param string $content
     *
     * @return string
     */
    private function getStyleUrl($content)
    {
        $patterns = array(
            '#((?:https?:)?//[^"\'\\s]+/wp-content/plugins/wp-grid-builder)/public/js/[^"\'\\s]+\\?ver=([^"\'\\s&]+)#i',
            '#(/wp-content/plugins/wp-grid-builder)/public/js/[^"\'\\s]+\\?ver=([^"\'\\s&]+)#i',
        );

        foreach ( $patterns as $pattern ) {
            if (
                preg_match($pattern, $content, $matches)
                && isset($matches[1], $matches[2])
            ) {
                return $matches[1] . '/public/css/style.css?ver=' . $matches[2];
            }
        }

        return '';
    }
}
