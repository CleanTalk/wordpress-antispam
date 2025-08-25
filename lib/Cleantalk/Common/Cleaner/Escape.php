<?php

namespace Cleantalk\Common\Cleaner;

class Escape
{
    /**
     * Simple method: escape html attribute
     *
     * @param $text
     */
    public static function escAttr($text)
    {
        // TODO
    }

    /**
     * Simple method: escape html
     *
     * @param $text
     */
    public static function escHtml($text)
    {
        return htmlspecialchars($text);
    }

    /**
     * Simple method: escape js
     *
     * @param $text
     */
    public static function escJs($text)
    {
        // TODO
    }

    /**
     * Simple method: escape textarea
     *
     * @param $text
     */
    public static function escTextarea($text)
    {
        // TODO
    }

    /**
     * Simple method: escape url
     *
     * @param $text
     */
    public static function escUrl($text)
    {
        // TODO
    }

    /**
     * Simple method: escape url raw
     *
     * @param $text
     */
    public static function escUrlRaw($text)
    {
        // TODO
    }

    /**
     * Simple method: escape kses
     *
     * @param $string
     * @param $allowed_html
     * @param array $allowed_protocols
     */
    public static function escKses($string, $allowed_html, $allowed_protocols = array())
    {
        // TODO
    }

    /**
     * Simple method: escape kses post
     *
     * @param $data
     */
    public static function escKsesPost($data)
    {
        // TODO
    }
}
