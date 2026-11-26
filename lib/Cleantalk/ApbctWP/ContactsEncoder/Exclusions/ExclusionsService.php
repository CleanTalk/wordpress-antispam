<?php

namespace Cleantalk\ApbctWP\ContactsEncoder\Exclusions;

use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;

class ExclusionsService extends \Cleantalk\Common\ContactsEncoder\Exclusions\ExclusionsService
{
    /**
     * Request data a visitor sends, in bytes. A page can show any of it: over this size no sign of the content counts.
     */
    const MAX_REQUEST_ECHO_BYTES = 65536;

    /**
     * Comparisons of the occurrences of one sign with the request data. Over it the sign does not count.
     */
    const MAX_ECHO_CHECKS = 2000;

    /**
     * Keep arrays of exclusion signs in the array
     *
     * Each exclusion's array must contain two or more elements.
     * The content checking by all inner array elements existence.
     *
     * It the exclusion set will contain only one element, this will trigger false-positive exclusion verdict.
     *
     * @var array
     */
    private $content_exclusions_signs = array(
        //divi contact forms additional emails
        array('_unique_id', 'et_pb_contact_form'),
        //divi builder contact forms
        array('_builder_version', 'custom_contact_email'),
        //ninja form noscript content exclusion
        array('ninja-forms-noscript-message'),
        //enfold theme contact form content exclusion - this fired during buffer interception
        array('av_contact', 'email', 'from_email'),
        // Stylish Cost Calculator
        array('scc-form-field-item'),
        // Exclusion of maps from leaflet
        array('leaflet', 'leaflet-map', 'map-wrap'),
        // prevent broking elementor swiper gallery
        array('class', 'elementor-swiper', 'elementor-testimonial', 'swiper-pagination'),
        // ics-calendar
        array('ics_calendar'),
        // WooCommerce block order confirmation create account form
        array('class', 'wc-block-order-confirmation-create-account-form'),
    );

    public function doReturnContentBeforeModify($content)
    {
        if ( $this->byDecoderPassedCookie() ) {
            return 'byDecoderPassedCookie';
        }

        if ( $this->byUrlOnHooks() ) {
            return 'byUrlOnHooks';
        }

        if ( $this->byContentSigns($content) ) {
            return 'byContentSigns';
        }

        return parent::doReturnContentBeforeModify($content);
    }

    /**
     * Exclusions for shortcode placeholder pipeline.
     * Unlike doReturnContentBeforeModify(), not blocked when global email/phone encoding is off.
     *
     * @param string $content
     *
     * @return string|false
     */
    public function doReturnShortcodeContentBeforeModify($content)
    {
        if ( $this->byUrlOnHooks() ) {
            return 'byUrlOnHooks';
        }

        if ( $this->byContentSigns($content) ) {
            return 'byContentSigns';
        }

        if ( $this->byLoggedIn() ) {
            return 'byLoggedIn';
        }

        if ( empty($content) || ! is_string($content) ) {
            return 'byEmptyContent';
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function byLoggedIn()
    {
        return $this->params->is_logged_in;
    }

    /**
     * @inerhitDoc
     */
    protected function byAccessKeyFail()
    {
        global $apbct;
        return ! $apbct->key_is_ok || ! apbct_api_key__is_correct();
    }

    /**
     * @return string|false
     * @psalm-suppress PossiblyUnusedReturnValue
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function doSkipBeforeModifyingHooksAdded()
    {
        global $apbct;

        if ( $this->byPluginSetting($apbct) ) {
            return 'byPluginSetting';
        }

        // Excluded request
        if ( $this->byServerVars() ) {
            return 'byServerVars';
        }

        return false;
    }

    /**
     * Excluded requests
     * @return bool
     */
    private function byServerVars()
    {
        if (
            apbct_is_plugin_active('ultimate-member/ultimate-member.php') &&
            isset($_POST['um_request']) &&
            array_key_exists('REQUEST_METHOD', $_SERVER) &&
            strtoupper($_SERVER['REQUEST_METHOD']) === 'POST' &&
            empty(Post::get('encodedEmail'))
        ) {
            return true;
        }

        return false;
    }

    /**
     * User passed email encoder check — skip encoding but keep shortcode layout hooks.
     *
     * @return bool
     */
    private function byDecoderPassedCookie()
    {
        return apbct_is_email_encoder_passed();
    }

    /**
     * @param State $apbct
     *
     * @return bool
     */
    private function byPluginSetting($apbct)
    {
        return ! $apbct->settings['data__email_decoder'];
    }

    /**
     * Skip encoder run on hooks.
     *
     * Applies filter "apbct_skip_email_encoder_on_uri_chunk_list" to get list of URI patterns to skip.
     * Each pattern can be:
     * - A simple string (e.g., 'details') - matched as substring
     * - A regex pattern (e.g., '^/$' for homepage) - matched as regex if contains special chars
     *
     * @return bool
     */
    private function byUrlOnHooks()
    {
        $url_patterns = apply_filters('apbct_skip_email_encoder_on_uri_chunk_list', array());

        if (empty($url_patterns) || !is_array($url_patterns)) {
            return false;
        }

        $request_uri = TT::toString(Server::get('REQUEST_URI'));

        foreach ($url_patterns as $pattern) {
            if (is_string($pattern) && $this->isUriMatchPattern($pattern, $request_uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if URI matches the given pattern.
     *
     * @param string $pattern - simple string, special keyword or regex pattern
     * @param string $uri - REQUEST_URI to check
     * @return bool
     */
    private function isUriMatchPattern($pattern, $uri)
    {
        // Special keyword for homepage
        if ($pattern === '__HOME__') {
            return $uri === '/' || $uri === '';
        }

        // Check if pattern contains regex special characters
        $is_regex = (bool) preg_match('/[\^$.|?*+()\[\]{}]/', $pattern);

        if (!$is_regex) {
            // Simple substring match (faster)
            return strpos($uri, $pattern) !== false;
        }

        // Regex match: escape delimiter
        $safe_pattern = str_replace('/', '\/', $pattern);
        return (bool) @preg_match('/' . $safe_pattern . '/u', $uri);
    }

    /**
     * Check content if it contains exclusions from exclusion list
     *
     * Applies filter "apbct_email_encoder_content_exclusions_signs" to extend the built-in signs.
     * Each item must be an array of strings; encoding is skipped when all strings of an item are found.
     * A sign counts only outside the echo of the request: a visitor can not switch the encoding off by sending
     * a sign that the page shows back, e.g. a search query in buffer mode.
     *
     * @see https://cleantalk.org/help/using-shortcodes-hooks-to-encode-contact-data#exclude
     *
     * @param string $content - content to check
     * @return bool - true if exclusions found, else - false
     */
    private function byContentSigns($content)
    {
        if (!is_string($content) || $content === '') {
            return false;
        }

        $filtered_signs = apply_filters(
            'apbct_email_encoder_content_exclusions_signs',
            $this->content_exclusions_signs
        );
        if ( ! is_array($filtered_signs) ) {
            $filtered_signs = $this->content_exclusions_signs;
        }

        $lowercase_content = null;
        $request_fragments = null;
        foreach ( array_values($filtered_signs) as $_signs_array => $signs ) {
            //process each of subarrays of signs
            $signs_found_count = 0;
            if ( isset($signs) && is_array($signs) ) {
                //check all the signs in the sub-array
                foreach ( $signs as $sign ) {
                    if ( is_string($sign) && $this->isSignOfSite($content, $sign, $lowercase_content, $request_fragments) ) {
                        $signs_found_count++;
                    }
                }
                //if each of signs in the sub-array are found return true
                if ( $signs_found_count === count($signs) && $signs_found_count > 0 ) {
                    if (in_array('et_pb_contact_form', $signs) && !is_admin()) {
                        return false;
                    }
                    return true;
                }
            }
        }
        //no signs found
        return false;
    }

    /**
     * The sign is in the content, and at least one of its occurrences is not inside an echo of the request.
     *
     * @param string $content
     * @param string $sign
     * @param string|null $lowercase_content The content in lower case, made once for all the signs.
     * @param string[]|false|null $request_fragments See getRequestFragments(), made once for all the signs.
     *
     * @return bool
     */
    private function isSignOfSite($content, $sign, &$lowercase_content, &$request_fragments)
    {
        $position = $sign === '' ? false : strpos($content, $sign);
        if ( $position === false ) {
            return false;
        }

        if ( $request_fragments === null ) {
            $request_fragments = $this->getRequestFragments();
        }
        if ( $request_fragments === false ) {
            return false;
        }

        $needle = strtolower($sign);
        $echo_fragments = array();
        foreach ( $request_fragments as $fragment ) {
            if ( strpos($fragment, $needle) !== false ) {
                $echo_fragments[] = $fragment;
            }
        }
        if ( $echo_fragments === array() ) {
            return true;
        }

        if ( $lowercase_content === null ) {
            $lowercase_content = strtolower($content);
        }

        $checks = self::MAX_ECHO_CHECKS;
        while ( $position !== false ) {
            if ( ! $this->isInsideRequestEcho($lowercase_content, $position, $needle, $echo_fragments, $checks) ) {
                return true;
            }
            if ( $checks <= 0 ) {
                return false;
            }
            $position = strpos($content, $sign, $position + 1);
        }

        return false;
    }

    /**
     * The echo of a value can be cut by the page, e.g. a long search query in a title: the part of the value from
     * its start through the sign, or from the sign to its end, counts too. Such a part and a plain word of the
     * request, e.g. "email" of utm_medium=email, are an echo only where they are not inside a longer word:
     * a sign of the site like "from_email" contains them.
     *
     * @param string $lowercase_content
     * @param int $position Offset of the sign in the content.
     * @param string $needle The sign in lower case.
     * @param string[] $fragments Values of the request that contain the sign.
     * @param int $checks Comparisons left, see MAX_ECHO_CHECKS. When they are over, the sign is taken as an echo.
     *
     * @return bool
     */
    private function isInsideRequestEcho($lowercase_content, $position, $needle, $fragments, &$checks)
    {
        $content_length = strlen($lowercase_content);
        $sign_end = $position + strlen($needle);

        foreach ( $fragments as $fragment ) {
            $length = strlen($fragment);
            $is_word = ctype_alnum($fragment);
            for ( $at = strpos($fragment, $needle); $at !== false; $at = strpos($fragment, $needle, $at + 1) ) {
                if ( --$checks < 0 ) {
                    return true;
                }

                $start = $position - $at;
                $end = $start + $length;

                if (
                    $start >= 0
                    && $end <= $content_length
                    && substr_compare($lowercase_content, $fragment, $start, $length) === 0
                    && ( ! $is_word || ( $this->isEchoEdge($lowercase_content, $start - 1, $fragment, -1) && $this->isEchoEdge($lowercase_content, $end, $fragment, $length) ) )
                ) {
                    return true;
                }

                if (
                    $start >= 0
                    && substr_compare($lowercase_content, $fragment, $start, $sign_end - $start) === 0
                    && $this->isEchoEdge($lowercase_content, $start - 1, $fragment, -1)
                    && $this->isEchoEdge($lowercase_content, $sign_end, $fragment, $at + strlen($needle))
                ) {
                    return true;
                }

                $tail_end = $position + $length - $at;
                if (
                    $tail_end <= $content_length
                    && substr_compare($lowercase_content, (string)substr($fragment, $at), $position, $length - $at) === 0
                    && $this->isEchoEdge($lowercase_content, $position - 1, $fragment, $at - 1)
                    && $this->isEchoEdge($lowercase_content, $tail_end, $fragment, $length)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * A char next to a part of an echo: the end of the content, not a char of a word, or the next char of the value.
     *
     * @param string $lowercase_content
     * @param int $offset Offset of the char in the content.
     * @param string $fragment
     * @param int $fragment_offset Offset of the same char in the value, out of it at the ends of the value.
     *
     * @return bool
     */
    private function isEchoEdge($lowercase_content, $offset, $fragment, $fragment_offset)
    {
        if ( $offset < 0 || $offset >= strlen($lowercase_content) ) {
            return true;
        }

        $char = $lowercase_content[$offset];
        if ( strspn($char, 'abcdefghijklmnopqrstuvwxyz0123456789_-') === 0 ) {
            return true;
        }

        return $fragment_offset >= 0 && $fragment_offset < strlen($fragment) && $fragment[$fragment_offset] === $char;
    }

    /**
     * Values a visitor sends and a page can show back: the URI, the query, the form data, cookies and headers.
     * Each value is taken as a whole and by its words, in lower case, also decoded as a page could decode it:
     * WordPress decodes a search query of the path twice.
     *
     * @return string[]|false False when the data is too big to check.
     */
    private function getRequestFragments()
    {
        $values = array(TT::toString(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''));
        foreach ( $_SERVER as $name => $value ) {
            if ( strpos($name, 'HTTP_') === 0 && is_string($value) ) {
                $values[] = $value;
            }
        }
        $this->collectRequestValues($_GET, $values);
        $this->collectRequestValues($_POST, $values);
        $this->collectRequestValues($_COOKIE, $values);

        $fragments = array();
        $size = 0;
        foreach ( $values as $value ) {
            $size += strlen($value);
            if ( $size > self::MAX_REQUEST_ECHO_BYTES ) {
                return false;
            }

            $value = stripslashes($value);
            $variants = array($value, rawurldecode($value));
            $decoded = $value;
            for ( $i = 0; $i < 3; $i++ ) {
                $next = urldecode($decoded);
                if ( $next === $decoded ) {
                    break;
                }
                $variants[] = $decoded = $next;
            }
            foreach ( $variants as $variant ) {
                $variant = strtolower($variant);
                foreach ( array($variant, html_entity_decode($variant, ENT_QUOTES)) as $fragment ) {
                    $fragments[$fragment] = true;
                    $words = preg_split('/[^a-z0-9_-]+/', $fragment, -1, PREG_SPLIT_NO_EMPTY);
                    foreach ( is_array($words) ? $words : array() as $word ) {
                        $fragments[$word] = true;
                    }
                }
            }
        }
        unset($fragments['']);

        return array_map('strval', array_keys($fragments));
    }

    /**
     * @param mixed $data
     * @param string[] $values
     *
     * @return void
     */
    private function collectRequestValues($data, &$values)
    {
        if ( ! is_array($data) ) {
            return;
        }

        foreach ( $data as $key => $value ) {
            $values[] = (string)$key;
            if ( is_array($value) ) {
                $this->collectRequestValues($value, $values);
            } elseif ( is_scalar($value) ) {
                $values[] = (string)$value;
            }
        }
    }
}
