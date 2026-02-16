<?php

namespace Cleantalk\ApbctWP\ContactsEncoder\Exclusions;

use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;

class ExclusionsService extends \Cleantalk\Common\ContactsEncoder\Exclusions\ExclusionsService
{
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
        if ( $this->byUrlOnHooks() ) {
            return 'byUrlOnHooks';
        }

        if ( $this->byContentSigns($content) ) {
            return 'byContentSigns';
        }

        return parent::doReturnContentBeforeModify($content);
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
        // Excluded request by alt cookie
        $apbct_email_encoder_passed = Cookie::get('apbct_email_encoder_passed');
        if ( $apbct_email_encoder_passed === apbct_get_email_encoder_pass_key() ) {
            return true;
        }

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
     * @param string $content - content to check
     * @return bool - true if exclusions found, else - false
     */
    private function byContentSigns($content)
    {
        if (!is_string($content) || $content === '') {
            return false;
        }
        if ( is_array($this->content_exclusions_signs) ) {
            foreach ( array_values($this->content_exclusions_signs) as $_signs_array => $signs ) {
                //process each of subarrays of signs
                $signs_found_count = 0;
                if ( isset($signs) && is_array($signs) ) {
                    //chek all the signs in the sub-array
                    foreach ( $signs as $sign ) {
                        if ( is_string($sign) ) {
                            if ( strpos($content, $sign) === false ) {
                                continue;
                            } else {
                                $signs_found_count++;
                            }
                        }
                    }
                    //if each of signs in the sub-array are found return true
                    if ( $signs_found_count === count($signs) ) {
                        if (in_array('et_pb_contact_form', $signs) && !is_admin()) {
                            return false;
                        }
                        return true;
                    }
                }
            }
        }
        //no signs found
        return false;
    }
}
