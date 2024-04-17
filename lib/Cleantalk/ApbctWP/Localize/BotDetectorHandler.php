<?php

namespace Cleantalk\ApbctWP\Localize;

use Cleantalk\ApbctWP\Variables\Get;

class BotDetectorHandler
{
    public static $wrapper_file_url = 'https://moderate.cleantalk.org/ct-bot-detector-wrapper.js';
    private static $wrapper_hash_url = 'https://moderate.cleantalk.org/sri.json';
    //private static $hash_refresh_period = 120;

    /**
     * Filter handler for action wp_script_attributes
     * @param $attr
     * @return mixed
     */
    public static function modifyWrapperScript($attr)
    {
        global $apbct;
        $integrity_data = $apbct->data['bot_detector_wrapper_integrity'];
        if (
            self::isIntegrityCorrect($integrity_data) &&
            isset($attr['id']) &&
            $attr['id'] == 'ct_bot_detector-js'
        ) {
            $attr['integrity'] = $integrity_data['hash'];
            $attr['crossorigin'] = 'anonymous';
        }
        return $attr;
    }

    /**
     * @param array $integrity_data
     * @return bool
     */
    public static function isIntegrityCorrect($integrity_data)
    {
        return is_array($integrity_data) &&
            isset($integrity_data['last_load_time']) &&
            !empty($integrity_data['hash']) &&
            is_string($integrity_data['hash']) &&
            str_contains($integrity_data['hash'], 'sha256') &&
            strlen($integrity_data['hash']) === 51;
    }

    /**
     * Get new integrity data from moderate server.
     * @return array
     */
    public static function getNewIntegrityData()
    {
        $hash_path = self::$wrapper_hash_url;
        $wrapper_hash = @file_get_contents($hash_path);
        $wrapper_hash = $wrapper_hash && is_string($wrapper_hash)
            ? json_decode($wrapper_hash, 1)
            : array();
        $wrapper_hash = is_array($wrapper_hash) && !empty($wrapper_hash['ct-bot-detector-wrapper.js'])
            ? trim($wrapper_hash['ct-bot-detector-wrapper.js'])
            : false;
        return array(
            'hash' => $wrapper_hash,
            'last_load_time' => time()
        );
    }

    public static function isScriptsExclusion()
    {
        if (apbct_is_plugin_active('oxygen/functions.php') && Get::get('ct_builder') === 'true') {
            return true;
        }

        return false;
    }
}
