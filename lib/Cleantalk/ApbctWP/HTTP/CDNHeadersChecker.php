<?php

namespace Cleantalk\ApbctWP\HTTP;

use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\State;
use Cleantalk\Common\TT;

/**
 * Detects which IP headers really come to the site through its CDN/proxy and sets the "misc__get_ip"
 * setting accordingly. The site sends a remote call to itself, the call handler collects the known
 * IP headers of this request. If there are no such headers, REMOTE_ADDR becomes the only IP source,
 * so visitors can not spoof their IP with a forged header.
 */
class CDNHeadersChecker
{
    /**
     * Remote call action name.
     */
    const RC_ACTION = 'cdn_check';

    /**
     * Cron task name.
     */
    const CRON_TASK = 'cdn_check';

    /**
     * Cron task handler.
     */
    const CRON_HANDLER = 'apbct_cdn_checker__send_request';

    /**
     * Cron task period.
     */
    const CRON_PERIOD = 86400;

    /**
     * How many check results to keep.
     */
    const RECORDS_LIMIT = 10;

    /**
     * Main CDN headers self check logic. Runs in the self remote call.
     *
     * @return array{found_headers: string[]|null, setting_changed_to: int|null, error: string|null}
     */
    public static function check()
    {
        global $apbct;

        $result = array(
            'found_headers'      => null,
            'setting_changed_to' => null,
            'error'              => null,
        );

        try {
            $found_headers_slugs = self::findKnownCDNHeaderSlugs(
                self::getHeadersWithIp(Helper::httpGetHeaders()),
                Helper::getKnownCDNHeadersNames()
            );
            $result['found_headers'] = $found_headers_slugs;

            $new_header_id = self::getNewHeaderIdForSettings(
                isset($apbct->settings['misc__get_ip']) ? $apbct->settings['misc__get_ip'] : null,
                $found_headers_slugs
            );
            if ( $new_header_id ) {
                $result['setting_changed_to'] = $new_header_id;
                self::applyIPGetHeadersSettings($apbct, $new_header_id);
            }

            self::updateStoredCDNHeadersData($found_headers_slugs, $new_header_id);
        } catch ( \Exception $e ) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Send the self remote call. The handler parses the request headers.
     *
     * @return array|bool|string
     */
    public static function sendCDNCheckerRequest()
    {
        return Helper::httpRequestRcToHost(
            self::RC_ACTION,
            array(),
            array('async', 'get')
        );
    }

    /**
     * Names of the headers which contain a valid IP (the first one for the comma separated lists).
     *
     * @param array $headers
     *
     * @return string[]
     */
    public static function getHeadersWithIp($headers)
    {
        $provided_headers = array();
        foreach ( $headers as $header => $value ) {
            if ( ! is_string($value) || $value === '' ) {
                continue;
            }
            $first_value = trim(explode(',', $value)[0], " \n\r\t\v\x00");
            if ( Helper::ipValidate($first_value) ) {
                $provided_headers[] = (string)$header;
            }
        }

        return $provided_headers;
    }

    /**
     * Filter provided headers strings array to find known CDN headers slugs.
     *
     * @param string[] $provided_headers provided headers strings array
     * @param string[] $known_cdn_headers known CDN headers
     *
     * @return string[] matches
     */
    public static function findKnownCDNHeaderSlugs($provided_headers, $known_cdn_headers)
    {
        $slugs = array();
        foreach ( $provided_headers as $provided_header ) {
            $provided_header = strtolower($provided_header);
            foreach ( $known_cdn_headers as $known_header ) {
                $known_header = strtolower($known_header);
                if ( $provided_header === $known_header ) {
                    $slugs[] = Helper::getIpHeaderSlugByName($known_header);
                    break;
                }
            }
        }

        return $slugs;
    }

    /**
     * Returns stored check results.
     *
     * @return array
     */
    public static function loadStoredCDNHeadersData()
    {
        global $apbct;

        return isset($apbct->data['cdn_checker_results']) && is_array($apbct->data['cdn_checker_results'])
            ? $apbct->data['cdn_checker_results']
            : array();
    }

    /**
     * Add the check result to the stored ones.
     *
     * @param string[] $found_headers newly found headers
     * @param int|null $new_header_id if checker changed the setting, write it to the data
     *
     * @return void
     */
    public static function updateStoredCDNHeadersData($found_headers, $new_header_id = null)
    {
        global $apbct;

        $stored = self::loadStoredCDNHeadersData();
        while ( count($stored) >= self::RECORDS_LIMIT ) {
            array_shift($stored);
        }

        $stored[] = array(
            'time'               => time(),
            'found_cdn_headers'  => array_values($found_headers),
            'setting_changed_to' => ! empty($new_header_id) ? (int)$new_header_id : null,
        );

        $apbct->data['cdn_checker_results'] = $stored;
        $apbct->saveData();
    }

    /**
     * Returns the new IP source ID. If nothing found and the current setting is not REMOTE_ADDR, returns REMOTE_ADDR.
     * Returns null if the setting does not need to be changed.
     *
     * @param int|string|null $current_get_ip_state
     * @param string[] $found_headers_array
     *
     * @return int|null
     */
    public static function getNewHeaderIdForSettings($current_get_ip_state, $found_headers_array)
    {
        $work_header   = self::getWorkHeaderSlug($found_headers_array);
        $new_header_id = $work_header
            ? Helper::getIpHeaderIDBySlug($work_header)
            : Helper::IP_SOURCE_REMOTE_ADDR;

        if ( $new_header_id === null || $new_header_id === TT::toInt($current_get_ip_state) ) {
            return null;
        }

        return $new_header_id;
    }

    /**
     * Set the new "misc__get_ip" setting.
     *
     * @param State $apbct
     * @param int $new_header_id
     *
     * @return void
     */
    public static function applyIPGetHeadersSettings($apbct, $new_header_id)
    {
        if ( ! isset(Helper::$known_ip_headers_collection[$new_header_id]) ) {
            return;
        }

        $apbct->settings['misc__get_ip'] = (int)$new_header_id;
        $apbct->saveSettings();
    }

    /**
     * Get work header slug from slugs array. CDN-specific headers first, then the first of others.
     *
     * @param string[] $found_headers_slugs
     *
     * @return string|null
     */
    public static function getWorkHeaderSlug($found_headers_slugs)
    {
        $cdn_slugs_ruleset = array(
            'incapsula',
            'ico_x_forwarded_for',
            'stackpath',
            'sucury',
            'ezoic',
            'gtranslate',
            'cloud_flare',
            'ovh',
        );

        $match = array_intersect($cdn_slugs_ruleset, $found_headers_slugs);
        if ( ! empty($match) ) {
            return (string)array_values($match)[0];
        }

        return ! empty($found_headers_slugs[0]) ? (string)$found_headers_slugs[0] : null;
    }

    /**
     * Human-readable result of the last check for the settings page. Escaped.
     *
     * @return string
     */
    public static function getLastCheckDescriptionHTML()
    {
        $stored = self::loadStoredCDNHeadersData();
        $last   = end($stored);

        if ( ! is_array($last) || empty($last['time']) ) {
            return esc_html__('Automatic detection has not run yet.', 'cleantalk-spam-protect');
        }

        $headers_text = ! empty($last['found_cdn_headers']) && is_array($last['found_cdn_headers'])
            ? __('found headers:', 'cleantalk-spam-protect') . ' ' . implode(', ', $last['found_cdn_headers'])
            : __('no known CDN headers found', 'cleantalk-spam-protect');

        $changed_id   = isset($last['setting_changed_to']) ? TT::toInt($last['setting_changed_to']) : 0;
        $changed_text = isset(Helper::$known_ip_headers_collection[$changed_id])
            ? sprintf(
                __('IP source changed to %s', 'cleantalk-spam-protect'),
                Helper::$known_ip_headers_collection[$changed_id]['name']
            )
            : __('no changes', 'cleantalk-spam-protect');

        return esc_html(
            sprintf(
                __('Last automatic detection: %s, %s, %s.', 'cleantalk-spam-protect'),
                date('Y-m-d H:i:s', TT::toInt($last['time'])),
                $headers_text,
                $changed_text
            )
        );
    }
}
