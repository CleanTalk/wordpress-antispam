<?php

namespace Cleantalk\Common\ContactsEncoder\Exclusions;

use Cleantalk\Common\ContactsEncoder\Dto\Params;

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
     * @var Params
     */
    protected $params;

    public function __construct(Params $params)
    {
        $this->params = $params;
    }

    /**
     * @return string|false
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function doSkipBeforeAnything()
    {
        if ( $this->byAccessKeyFail() ) {
            return 'byAccessKeyFail';
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
        if ( ! $this->params->do_encode_emails && ! $this->params->do_encode_phones ) {
            return 'globallyDisabledBothEncoding';
        }

        if ( $this->byLoggedIn() ) {
            return 'byLoggedIn';
        }

        //skip empty or invalid content
        if ( $this->byEmptyContent($content) ) {
            return 'byEmptyContent';
        }

        if ( $this->byContentSigns($content) ) {
            return 'byContentSigns';
        }

        return false;
    }



    /**
     * @return bool
     */
    private function byAccessKeyFail()
    {
        return empty($this->params->api_key);
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
                        // @todo ???
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
    protected function byLoggedIn()
    {
        return $this->params->is_logged_in;
    }
}
