<?php

namespace Cleantalk\ApbctWP;

class Escape extends \Cleantalk\Common\Escape
{
    /**
     * Simple method: escape attribute
     *
     * @param $text
     *
     * @return string|void
     */
    public static function escAttr($text)
    {
        return esc_attr($text);
    }

    /**
     * Simple method: escape html
     *
     * @param $text
     *
     * @return string
     */
    public static function escHtml($text)
    {
        return esc_html($text);
    }

    /**
     * Simple method: escape js
     *
     * @param $text
     *
     * @return string
     */
    public static function escJs($text)
    {
        return esc_js($text);
    }

    /**
     * Simple method: escape textarea
     *
     * @param $text
     *
     * @return string
     */
    public static function escTextarea($text)
    {
        return esc_textarea($text);
    }

    /**
     * Simple method: escape url
     *
     * @param $text
     *
     * @return string|null
     */
    public static function escUrl($text)
    {
        return esc_url($text);
    }

    /**
     * Simple method: escape url raw
     *
     * @param $text
     *
     * @return string|null
     */
    public static function escUrlRaw($text)
    {
        return esc_url_raw($text);
    }

    /**
     * Simple method: escape kses
     *
     * @param $string
     * @param $allowed_html
     * @param array $allowed_protocols
     *
     * @return string
     */
    public static function escKses($string, $allowed_html, $allowed_protocols = array())
    {
        add_filter('safe_style_css', function ($styles) {
            $styles[] = 'display';
            return $styles;
        });

        $result = wp_kses($string, $allowed_html, $allowed_protocols = array());
        if ( isset($allowed_html)
            && is_array($allowed_html)
            && (
                array_key_exists('script', $allowed_html)
                ||
                (
                    array_key_exists('a', $allowed_html)
                    && array_key_exists('onclick', $allowed_html['a'])
                )
            )
        ) {
            $result = str_replace(array('&gt;', '&lt;'), array('>', '<'), $result);
        }
        return $result;
    }

    public static function escKsesPreset($string, $preset = null, $_allowed_protocols = array())
    {

        $kses_presets = array(

            'apbct_settings__display__groups' => array(
                'div' => array(
                    'class' => true,
                    'style' => true,
                    'id' => true,
                ),
                'h3' => array(
                    'class' => true,
                    'style' => true,
                    'id' => true,
                ),
                'h4' => array(
                    'class' => true,
                ),
                'a' => array(
                    'target' => true,
                    'href' => true,
                    'class' => true,
                    'onclick' => true,
                    'style' => true,
                    'id' => true,
                ),
                'hr' => array(
                    'style' => true,
                ),
                'br' => array(),
                'li' => array(),
                'ul' => array(),
                'span' => array(
                    'id' => true,
                ),
                'button' => array(
                    'name' => true,
                    'class' => true,
                    'value' => true,
                ),
            ),

            'apbct_settings__display__banner_template' => array(
                'div' => array(
                    'class' => true,
                    'id' => true,
                ),
                'a' => array(
                    'target' => true,
                    'href' => true,
                ),
                'p' => array(),
            )
        );

        if ( !empty($kses_presets[$preset]) ) {
            $allowed_html = $kses_presets[$preset];
            return self::escKses($string, $allowed_html, $allowed_protocols = array());
        }

        return self::escKses($string, $allowed_html = array(), $allowed_protocols = array());
    }

    /**
     * Simple method: escape kses post
     *
     * @param $data
     *
     * @return string
     */
    public static function escKsesPost($data)
    {
        return wp_kses_post($data);
    }
}
