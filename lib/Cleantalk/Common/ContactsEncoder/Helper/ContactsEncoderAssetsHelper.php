<?php

namespace Cleantalk\Common\ContactsEncoder\Helper;

/**
 * @psalm-suppress UnusedClass
 */
class ContactsEncoderAssetsHelper
{
    public static function getCssPath()
    {
        return dirname(__DIR__) . '/assets/contacts_encoder.css';
    }

    public static function getJsPath()
    {
        return dirname(__DIR__) . '/assets/contacts_encoder.js';
    }

    public static function renderCssTag()
    {
        $cssContent = file_get_contents(self::getCssPath());
        return "<style>{$cssContent}</style>";
    }

    public static function renderJsTag()
    {
        $jsContent = file_get_contents(self::getJsPath());
        return "<script>{$jsContent}</script>";
    }
}
