<?php

namespace Cleantalk\ApbctWP;

class ApbctJsBundleResolver
{
    /**
     * Get bundle name based on settings
     *
     * @param array|\ArrayObject $settings
     * @return string
     */
    public static function getBundleName($settings)
    {
        if ($settings instanceof \ArrayObject) {
            $settings = $settings->getArrayCopy();
        }

        $external = !empty($settings['forms__check_external']) && $settings['forms__check_external'] === '1';
        $internal = !empty($settings['forms__check_internal']) && $settings['forms__check_internal'] === '1';

        if ($external && $internal) {
            $script = 'apbct-public-bundle_full-protection.min.js';
        } elseif ($external) {
            $script = 'apbct-public-bundle_ext-protection.min.js';
        } elseif ($internal) {
            $script = 'apbct-public-bundle_int-protection.min.js';
        } else {
            $script = 'apbct-public-bundle.min.js';
        }

        if (isset($settings['data__bot_detector_enabled']) && $settings['data__bot_detector_enabled'] != '1') {
            $script = str_replace('.min.js', '_gathering.min.js', $script);
        }

        return $script;
    }
}
