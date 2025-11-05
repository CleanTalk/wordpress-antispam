<?php

namespace Cleantalk\Antispam\EmailEncoder;

use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;

class ExclusionsService
{
    /**
     * Keep arrays of exclusion signs in the array
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
        array('leaflet'),
        // prevent broking elementor swiper gallery
        array('class', 'elementor-swiper', 'elementor-testimonial', 'swiper-pagination'),
        // ics-calendar
        array('ics_calendar'),
    );

    /**
     * @return string|false
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function doSkipBeforeAnything()
    {
        global $apbct;
        if ( $this->byAccessKeyFail($apbct) ) {
            return 'byAccessKeyFail';
        }

        return false;
    }

    /**
     * @return string|false
     * @psalm-suppress PossiblyUnusedReturnValue
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
     * @param $content
     *
     * @return string|false
     * @psalm-suppress PossiblyUnusedReturnValue
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function doReturnContentBeforeModify($content)
    {
        global $apbct;

        if ( !(bool)$apbct->settings['data__email_decoder_encode_email_addresses'] && !(bool)$apbct->settings['data__email_decoder_encode_phone_numbers'] ) {
            return 'globallyDisabledBothEncoding';
        }

        if ( $this->byLoggedIn() ) {
            return 'byLoggedIn';
        }

        //skip empty or invalid content
        if ( $this->byEmptyContent($content) ) {
            return 'byEmptyContent';
        }

        if ( $this->byUrlOnHooks() ) {
            return 'byUrlOnHooks';
        }

        if ( $this->byContentSigns($content) ) {
            return 'byContentSigns';
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
     * @param State $apbct
     *
     * @return bool
     */
    private function byAccessKeyFail($apbct)
    {
        return !$apbct->key_is_ok || ! apbct_api_key__is_correct();
    }

    /**
     * Check content if it contains exclusions from exclusion list
     * @param $content - content to check
     * @return bool - true if exclusions found, else - false
     */
    private function byContentSigns($content)
    {
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

    /**
     * @param string $content
     *
     * @return bool
     */
    private function byEmptyContent($content)
    {
        //skip empty or invalid content
        return empty($content) || !is_string($content);
    }

    /**
     * @return bool
     */
    private function byLoggedIn()
    {
        return apbct_is_user_logged_in() && !apbct_is_in_uri('options-general.php?page=cleantalk');
    }

    /**
     * Skip encoder run on hooks.
     *
     * 1. Applies filter "apbct_hook_skip_email_encoder_on_url_list" to get modified list of URI chunks that needs to skip.
     * @return bool
     */
    private function byUrlOnHooks()
    {
        $skip_encode = false;
        $url_chunk_list = array();

        // Apply filter "apbct_hook_skip_email_encoder_on_url_list" to get the URI chunk list.
        $url_chunk_list = apply_filters('apbct_skip_email_encoder_on_uri_chunk_list', $url_chunk_list);

        if ( !empty($url_chunk_list) && is_array($url_chunk_list) ) {
            foreach ($url_chunk_list as $chunk) {
                if (is_string($chunk) && strpos(TT::toString(Server::get('REQUEST_URI')), $chunk) !== false) {
                    $skip_encode = true;
                    break;
                }
            }
        }

        return $skip_encode;
    }
}
