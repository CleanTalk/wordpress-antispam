<?php

namespace Cleantalk\Common\ContactsEncoder\Exclusions;

use Cleantalk\Common\ContactsEncoder\Dto\Params;

class ExclusionsService
{
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

        return false;
    }



    /**
     * @return bool
     */
    protected function byAccessKeyFail()
    {
        return empty($this->params->api_key);
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

    /**
     * Split a settings textarea into unique non-empty exclusion strings.
     *
     * @param string $raw
     *
     * @return string[]
     * @psalm-suppress PossiblyUnusedMethod Public API for host apps that store the list as raw text
     */
    public static function parseExcludedStrings($raw)
    {
        if ( ! is_string($raw) || $raw === '' ) {
            return array();
        }

        $parts = preg_split('/[\r\n,]+/', $raw);
        if ( ! is_array($parts) ) {
            return array();
        }

        $result = array();
        foreach ( $parts as $part ) {
            $part = trim($part, " \n\r\t\v\x00");
            if ( $part !== '' ) {
                $result[] = $part;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * Whether a matched email or phone must stay unencoded.
     *
     * @param string $match
     *
     * @return bool
     */
    public function isContactExcluded($match)
    {
        if ( ! is_string($match) || $match === '' ) {
            return false;
        }

        if ( empty($this->params->excluded_strings) || ! is_array($this->params->excluded_strings) ) {
            return false;
        }

        $normalized_match = $this->normalizeContactString($match);
        $match_digits     = $this->extractDigits($match);

        foreach ( $this->params->excluded_strings as $exclusion ) {
            if ( ! is_string($exclusion) || $exclusion === '' ) {
                continue;
            }

            $normalized_exclusion = $this->normalizeContactString($exclusion);
            if ( $normalized_exclusion === '' ) {
                continue;
            }

            if ( $normalized_match === $normalized_exclusion ) {
                return true;
            }

            if ( strpos($normalized_match, $normalized_exclusion) !== false ) {
                return true;
            }

            $exclusion_digits = $this->extractDigits($exclusion);
            if (
                strlen($exclusion_digits) >= 8
                && strlen($match_digits) >= 8
                && (
                    strpos($match_digits, $exclusion_digits) !== false
                    || strpos($exclusion_digits, $match_digits) !== false
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private function normalizeContactString($value)
    {
        $value = trim($value, " \n\r\t\v\x00");
        if ( stripos($value, 'mailto:') === 0 ) {
            $value = substr($value, 7);
        }
        if ( stripos($value, 'tel:') === 0 ) {
            $value = substr($value, 4);
        }

        return strtolower(trim($value, " \n\r\t\v\x00"));
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private function extractDigits($value)
    {
        $digits = preg_replace('/\D+/', '', $value);

        return is_string($digits) ? $digits : '';
    }
}
