<?php

namespace Cleantalk\ApbctWP;

class ApbctJsBundleResolver
{
    const PUBLIC_BUNDLE = 'apbct_public_new.min.js';
    const FIREWALL_DIE_PAGE_BUNDLE = 'apbct_firewall_die_page.min.js';

    /**
     * Get public bundle name. The only bundle is built by webpack, the rest functionality is loaded as chunks.
     *
     * @param array|\ArrayObject|null $_settings Not used, left for the backward compatibility
     * @return string
     */
    public static function getBundleName($_settings = null)
    {
        return self::PUBLIC_BUNDLE;
    }

    /**
     * Return full URI for JS script
     * @param array|\ArrayObject|null $_settings Not used, left for the backward compatibility
     *
     * @return string
     */
    public static function getFullScriptURI($_settings = null)
    {
        return APBCT_URL_PATH . '/js/' . self::PUBLIC_BUNDLE . '?' . APBCT_VERSION;
    }

    /**
     * Return full URI for the script of the firewall die pages (SFW, AntiFlood, AntiCrawler)
     *
     * @return string
     */
    public static function getFirewallDiePageScriptURI()
    {
        return APBCT_URL_PATH . '/js/' . self::FIREWALL_DIE_PAGE_BUNDLE . '?' . APBCT_VERSION;
    }
}
