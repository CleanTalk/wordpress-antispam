<?php

namespace Cleantalk\Common;

/**
 * Class LinkConstructor
 *
 * This class is responsible for constructing links with UTM parameters.
 *
 */
class LinkConstructor
{
    /**
     * @var array[] $utm_presets
     *
     * An associative array containing UTM presets for different types of links.
     * Each preset is an array of UTM parameters.
     */
    public static $utm_presets;

    /**
     * @var string $utm_campaign
     *
     * String containing the UTM campaign name. Common for all the links.
     */
    public static $utm_campaign;

    /**
     * Builds a GET parameters chunk for a CleanTalk link.
     *
     * @param string $utm_preset The UTM preset to use from the $utm_presets array.
     * @param array $get_params Additional GET parameters to append to the link.
     *
     * @return string The constructed GET parameters chunk.
     *
     * @throws \Exception If the UTM preset is invalid.
     */
    private static function getGetParamsChunk($utm_preset, $get_params = array())
    {
        if ( empty($utm_preset) || empty(static::$utm_presets) || !isset(static::$utm_presets[$utm_preset]) ) {
            throw new \Exception('Invalid UTM preset. All the CleanTalk links should have UTM data!');
        } else {
            $utm_data = static::$utm_presets[$utm_preset];
        }

        $utm_data['utm_campaign'] = isset($utm_data['utm_campaign']) ? $utm_data['utm_campaign'] : static::$utm_campaign;
        $glued = array_merge($get_params, $utm_data);
        return http_build_query($glued);
    }

    /**
     * Builds a CleanTalk link with UTM parameters.
     *
     * @param string $utm_preset The UTM preset to use from the $utm_presets array.
     * @param string $uri The URI to append to the domain.
     * @param array $get_params Additional GET parameters to append to the link.
     * @param string $domain The domain for the link. Defaults to 'https://cleantalk.org'.
     *
     * @return string The constructed link.
     */
    public static function buildCleanTalkLink($utm_preset, $uri = '', $get_params = array(), $domain = 'https://cleantalk.org')
    {
        $get_params = self::getGetParamsChunk($utm_preset, $get_params);
        $domain = rtrim($domain, '/');
        $link = $domain . '/' . $uri . '?' . $get_params;
        return $link;
    }

    /**
     * Builds a renewal link with an HTML anchor tag.
     *
     * @param string $user_token The user's token.
     * @param string $link_inner_html The HTML to be enclosed within the anchor tag.
     * @param int|string $product_id The product ID.
     * @param string $utm_preset The UTM preset to use from the $utm_presets array.
     *
     * @return string The constructed renewal link with an HTML anchor tag.
     */
    public static function buildRenewalLinkATag($user_token, $link_inner_html, $product_id, $utm_preset)
    {
        $domain = 'https://p.cleantalk.org';
        $get_params = array(
            'product_id' => (string)$product_id,
            'featured' => '',
            'user_token' => esc_html($user_token),
        );
        $link = self::buildCleanTalkLink($utm_preset, '', $get_params, $domain);
        //prepare link
        return '<a href="' . $link . '" target="_blank">' . $link_inner_html . '</a>';
    }
}
