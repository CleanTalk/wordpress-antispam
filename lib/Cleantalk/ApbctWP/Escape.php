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
        return wp_kses($string, $allowed_html, $allowed_protocols = array());
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
