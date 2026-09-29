<?php

namespace Cleantalk\Common;

/**
 * Fetches the changelog of a pending plugin update and lets a child class print it
 * inside the native WordPress update notice on the plugins list page.
 *
 * The class is intentionally markup-free and style-free: any HTML must be produced
 * by a child implementation of getNoticeHtml().
 *
 * Usage:
 *   class MyNotice extends AbstractUpdateChangelogNotice
 *   {
 *       protected function getPluginFile() { return 'my-plugin/my-plugin.php'; }
 *       protected function getGithubRepo() { return 'Owner/repo'; }
 *       protected function getNoticeHtml($version, $changelog_html) { ... }
 *   }
 *   MyNotice::register();
 */
abstract class AbstractUpdateChangelogNotice
{
    const CACHE_PREFIX      = 'upd_changelog_';
    const CACHE_TTL_SUCCESS = 43200; // 12 hours
    const CACHE_TTL_FAIL    = 3600;  // 1 hour
    const HTTP_TIMEOUT      = 5;

    /**
     * Plugin basename the notice is attached to,
     * e.g. "security-malware-firewall/security-malware-firewall.php".
     *
     * @return string
     */
    abstract protected function getPluginFile();

    /**
     * The only place where markup may appear.
     *
     * The method is called for the pending update of the plugin. The returned
     * markup is injected into the native notice block, after the message
     * paragraph and before the end of the notice container.
     *
     * @param string $version        version offered by WordPress
     * @param string $changelog_html sanitized changelog HTML
     *
     * @return string
     */
    abstract protected function getNoticeHtml($version, $changelog_html);

    /**
     * Escapes the changelog HTML. The child provides its own escaping
     * implementation, so this class stays free of any escaping dependency.
     *
     * @param string $html
     *
     * @return string
     */
    abstract protected function escapeChangelogHtml($html);

    /**
     * WordPress.org slug. Defaults to the plugin folder name.
     *
     * @return string
     */
    protected function getSlug()
    {
        return dirname($this->getPluginFile());
    }

    /**
     * "Owner/repo" used as a fallback source. Return null to disable the fallback.
     *
     * @return string|null
     */
    protected function getGithubRepo()
    {
        return null;
    }

    /**
     * Entry point. Wraps the native update row to inject the changelog
     * into the very same notice block.
     *
     * @return void
     */
    public static function register()
    {
        $instance = new static();
        $hook     = 'after_plugin_row_' . $instance->getPluginFile();

        // wp_plugin_update_row() is hooked with priority 10, so the row output
        // is captured and post-processed around it.
        add_action($hook, array($instance, 'startRowBuffer'), 9, 3);
        add_action($hook, array($instance, 'flushRowBuffer'), 11, 3);
    }

    /**
     * Hook handler. Starts capturing the native update row output.
     *
     * @return void
     */
    public function startRowBuffer()
    {
        ob_start();
    }

    /**
     * Hook handler. Injects the changelog into the captured update row.
     *
     * @param string $plugin_file
     *
     * @return void
     */
    public function flushRowBuffer($plugin_file = '')
    {
        $row = ob_get_clean();

        if ( ! is_string($row) ) {
            return;
        }

        echo $this->injectNotice($row, $plugin_file !== '' ? $plugin_file : $this->getPluginFile());
    }

    /**
     * Places the changelog markup right before the end of the notice container.
     *
     * @param string $row         captured update row markup
     * @param string $plugin_file
     *
     * @return string
     */
    protected function injectNotice($row, $plugin_file)
    {
        if ( $row === '' || strpos($row, 'update-message') === false ) {
            return $row;
        }

        $version = $this->getOfferedVersion($plugin_file);

        if ( $version === '' ) {
            return $row;
        }

        $changelog_html = $this->getChangelogHtml($version);

        if ( $changelog_html === '' ) {
            return $row;
        }

        $notice_html = $this->getNoticeHtml($version, $changelog_html);

        if ( ! is_string($notice_html) || $notice_html === '' ) {
            return $row;
        }

        $position = strrpos($row, '</p></div>');

        if ( $position === false ) {
            return $row;
        }

        return substr_replace($row, '</p>' . $notice_html . '</div>', $position, strlen('</p></div>'));
    }

    /**
     * Version offered by the WordPress updates transient.
     *
     * @param string $plugin_file
     *
     * @return string empty string when no update is available
     */
    protected function getOfferedVersion($plugin_file)
    {
        $updates = get_site_transient('update_plugins');

        return isset($updates->response[$plugin_file]->new_version)
            ? (string)$updates->response[$plugin_file]->new_version
            : '';
    }

    /**
     * Returns the sanitized changelog of the given version. Result is cached,
     * including the negative one, to avoid hammering remote sources.
     *
     * @param string $version
     *
     * @return string empty string when the changelog can not be obtained
     */
    protected function getChangelogHtml($version)
    {
        $cache_key = static::CACHE_PREFIX . md5($this->getSlug() . '|' . $version);
        $cached    = $this->getCache($cache_key);

        if ( $cached !== false ) {
            return is_string($cached) ? $cached : '';
        }

        $html = $this->fetchFromWpOrg($version);

        if ( $html === '' ) {
            $html = $this->fetchFromGitHub($version);
        }

        $this->setCache(
            $cache_key,
            $html,
            $html !== '' ? static::CACHE_TTL_SUCCESS : static::CACHE_TTL_FAIL
        );

        return $html;
    }

    /**
     * Primary source: WordPress.org plugin information API.
     *
     * @param string $version
     *
     * @return string
     */
    protected function fetchFromWpOrg($version)
    {
        $info = $this->requestPluginInformation();

        if ( is_wp_error($info) ) {
            return '';
        }

        $info = (array) $info;
        $sections = $info['sections'] ?? [];

        if ( ! is_array($sections) ) {
            return '';
        }

        $changelog = $sections['changelog'] ?? '';

        if ( ! is_string($changelog) || $changelog === '' ) {
            return '';
        }

        return $this->extractVersionBlock($changelog, $version);
    }

    /**
     * Fallback source: GitHub release notes of the tag equal to the offered version.
     *
     * @param string $version
     *
     * @return string
     */
    protected function fetchFromGitHub($version)
    {
        $repo = $this->getGithubRepo();

        if ( ! is_string($repo) || $repo === '' ) {
            return '';
        }

        $repo_path = implode('/', array_map('rawurlencode', explode('/', $repo)));

        $urls = array(
            'https://api.github.com/repos/' . $repo_path . '/releases/tags/' . rawurlencode($version),
            'https://api.github.com/repos/' . $repo_path . '/releases?per_page=1',
        );

        foreach ( $urls as $url ) {
            $body = $this->doRequest($url);

            if ( $body === '' ) {
                continue;
            }

            $data = json_decode($body, true);

            if ( ! is_array($data) ) {
                continue;
            }

            if ( isset($data['body']) ) {
                $release_notes = $data['body'];
            } elseif ( isset($data[0]['body']) ) {
                $release_notes = $data[0]['body'];
            } else {
                continue;
            }

            if ( is_string($release_notes) && $release_notes !== '' ) {
                return $this->readmeToHtml($release_notes);
            }
        }

        return '';
    }

    /**
     * Seam: the only call to the WordPress.org API.
     *
     * @return array|object|\WP_Error
     */
    protected function requestPluginInformation()
    {
        if ( ! function_exists('plugins_api') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }

        return plugins_api('plugin_information', array(
            'slug'   => $this->getSlug(),
            'fields' => array(
                'sections'     => true,
                'banners'      => false,
                'screenshots'  => false,
                'reviews'      => false,
                'contributors' => false,
            ),
        ));
    }

    /**
     * Seam: the only outgoing HTTP request.
     *
     * @param string $url
     *
     * @return string response body or empty string on any failure
     */
    protected function doRequest($url)
    {
        $response = wp_remote_get($url, array(
            'timeout' => static::HTTP_TIMEOUT,
            'headers' => array(
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'WordPress/' . $this->getSlug(),
            ),
        ));

        if ( is_wp_error($response) || (int)wp_remote_retrieve_response_code($response) !== 200 ) {
            return '';
        }

        return (string)wp_remote_retrieve_body($response);
    }

    /**
     * @param string $key
     *
     * @return mixed false when the cache is empty
     */
    protected function getCache($key)
    {
        return get_transient($key);
    }

    /**
     * @param string $key
     * @param string $value
     * @param int    $ttl
     *
     * @return void
     */
    protected function setCache($key, $value, $ttl)
    {
        set_transient($key, $value, (int)$ttl);
    }

    /**
     * Picks a single version block from the WordPress.org changelog HTML.
     * Falls back to the first (newest) block when the exact version is not found.
     *
     * @param string $changelog_html
     * @param string $version
     *
     * @return string sanitized HTML
     */
    protected function extractVersionBlock($changelog_html, $version)
    {
        $parts = preg_split(
            '/(<h[1-6][^>]*>.*?<\/h[1-6]>)/is',
            $changelog_html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        if ( ! is_array($parts) ) {
            return '';
        }

        $blocks      = array();
        $parts_count = count($parts);

        for ( $i = 1; $i < $parts_count; $i += 2 ) {
            $blocks[] = array(
                'title' => wp_strip_all_tags($parts[$i]),
                'body'  => isset($parts[$i + 1]) ? $parts[$i + 1] : '',
            );
        }

        if ( empty($blocks) ) {
            return '';
        }

        $chosen = $blocks[0];

        foreach ( $blocks as $block ) {
            if ( preg_match('/(?<![\d.])' . preg_quote($version, '/') . '(?![\d.])/', $block['title']) ) {
                $chosen = $block;
                break;
            }
        }

        return $this->sanitize($this->normalizeChangelogBody($chosen['body']));
    }

    /**
     * WordPress.org returns the changelog body in two shapes:
     *  - a ready-made list: "<ul><li>...</li>...</ul>";
     *  - a flat block of lines separated by "<br />" (or plain newlines).
     *
     * The flat shape is normalized into a list so both variants render
     * identically and never leak bare "<p>" tags (which would duplicate
     * the native update icon rendered via ".update-message p:before").
     *
     * @param string $body
     *
     * @return string
     */
    protected function normalizeChangelogBody($body)
    {
        if ( ! is_string($body) || trim($body, " \f\n\r\t\v\x00") === '' ) {
            return '';
        }

        // Already a list, nothing to do.
        if ( stripos($body, '<li') !== false ) {
            return $body;
        }

        $lines = preg_split('/<br\s*\/?>|\R/i', $body);

        if ( ! is_array($lines) ) {
            return $body;
        }

        $items = array();

        foreach ( $lines as $line ) {
            $line = trim($line, " \f\n\r\t\v\x00");

            if ( $line === '' ) {
                continue;
            }

            $items[] = '<li>' . $line . '</li>';
        }

        return $items ? '<ul>' . implode('', $items) . '</ul>' : $body;
    }

    /**
     * Converts a readme-style changelog block ("= 2.188 ... =" and "* item" lines)
     * to a plain list. The source text is escaped before links are generated.
     *
     * @param string $text
     *
     * @return string sanitized HTML
     */
    protected function readmeToHtml($text)
    {
        $lines = preg_split('/\R/', $text);

        if ( ! is_array($lines) ) {
            return '';
        }

        $items = array();

        foreach ( $lines as $line ) {
            $line = trim($line, " \f\n\r\t\v\x00");

            if ( $line === '' || strpos($line, '=') === 0 || strpos($line, '#') === 0 ) {
                continue;
            }

            $line = ltrim($line, "*-+ \t");

            if ( $line === '' ) {
                continue;
            }

            $items[] = '<li>' . esc_html($line) . '</li>';
        }

        return $items
            ? $this->sanitize('<ul>' . implode('', $items) . '</ul>')
            : '';
    }

    /**
     * Strict whitelist for the third-party changelog HTML. Dangerous elements are
     * dropped together with their content, the rest is escaped by the child.
     *
     * @param string $html
     *
     * @return string
     */
    protected function sanitize($html)
    {
        if ( ! is_string($html) || $html === '' ) {
            return '';
        }

        // Drop dangerous elements together with their content:
        // kses strips the tags but keeps the inner text.
        $stripped = preg_replace(
            '#<(script|style|iframe|object|embed|svg|math|template|noscript|frameset|frame|applet)\b[^>]*>.*?</\1\s*>#is',
            '',
            $html
        );
        $html = is_string($stripped) ? $stripped : '';

        $stripped = preg_replace(
            '#<(script|style|iframe|object|embed|svg|math|template|noscript|frameset|frame|applet)\b[^>]*/?>#is',
            '',
            $html
        );
        $html = is_string($stripped) ? $stripped : '';

        return $this->escapeChangelogHtml($this->forceDiscListStyle($this->removeLinks($html)));
    }

    /**
     * Forces visible round bullets on every "<ul>" of the changelog, regardless
     * of its source (wp.org markup, our own normalized list or the GitHub
     * markdown conversion). Admin themes commonly reset "ul { list-style: none }",
     * so the marker is set inline to guarantee it survives everywhere.
     *
     * @param string $html
     *
     * @return string
     */
    protected function forceDiscListStyle($html)
    {
        if ( ! is_string($html) || $html === '' ) {
            return $html;
        }

        $result = preg_replace_callback(
            '#<ul\b([^>]*)>#i',
            static function ($matches) {
                $attrs = isset($matches[1]) ? $matches[1] : '';

                if (
                    preg_match('/\bstyle\s*=\s*(["\'])(.*?)\1/i', $attrs, $style_match)
                    && isset($style_match[2])
                ) {
                    $style     = rtrim(trim($style_match[2], " \f\n\r\t\v\x00"), ';') . '; list-style: disc; padding-left: 20px;';
                    $new_attrs = preg_replace(
                        '/\bstyle\s*=\s*(["\']).*?\1/i',
                        'style="' . $style . '"',
                        $attrs,
                        1
                    );
                } else {
                    $new_attrs = $attrs . ' style="list-style: disc; padding-left: 20px;"';
                }

                return '<ul' . $new_attrs . '>';
            },
            $html
        );

        return is_string($result) ? $result : $html;
    }

    /**
     * Removes every link from the changelog: anchors are unwrapped and bare URLs
     * are cut out, so the notice body never contains links.
     *
     * @param string $html
     *
     * @return string
     */
    protected function removeLinks($html)
    {
        // <a ...>text</a> -> text
        $result = preg_replace('#<a\b[^>]*>(.*?)</a\s*>#is', '$1', $html);
        $html   = is_string($result) ? $result : $html;

        // Leftover unclosed anchors.
        $result = preg_replace('#</?a\b[^>]*>#i', '', $html);
        $html   = is_string($result) ? $result : $html;

        // Bare URLs, including the www-style ones.
        $result = preg_replace('#\b(?:https?://|www\.)[^\s<"\']+#i', '', $html);
        $html   = is_string($result) ? $result : $html;

        // Clean up separators left after the removal.
        $result = preg_replace('#[ \t]+([.,;:])#', '$1', $html);
        $html   = is_string($result) ? $result : $html;

        $result = preg_replace('#[ \t]{2,}#', ' ', $html);
        $html   = is_string($result) ? $result : $html;

        $result = preg_replace('#[ \t]+(</)#', '$1', $html);

        return is_string($result) ? $result : $html;
    }
}
