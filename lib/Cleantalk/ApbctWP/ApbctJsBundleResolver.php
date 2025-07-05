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
            return 'apbct-public-bundle_full-protection.min.js';
        } elseif ($external) {
            return 'apbct-public-bundle_ext-protection.min.js';
        } elseif ($internal) {
            return 'apbct-public-bundle_int-protection.min.js';
        } else {
            return 'apbct-public-bundle.min.js';
        }
    }
}
