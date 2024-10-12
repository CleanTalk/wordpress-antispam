<?php

namespace Cleantalk\ApbctWP\AdjustToEnvironmentModule\AdjustToEnv;

use LiteSpeed\Purge;

class AdjustToEnvLiteSpeedCache extends AdjustToEnvAbstract
{
    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function __construct($info)
    {
        parent::__construct($info);

        if (isset($this->info[static::class]['changed'])) {
            $this->changed = $this->info[static::class]['changed'];
        }
    }

    /**
     * Check if need to adjust
     * ----------------------------------------------------
     * Description:
     * If site admin requested to reverse adjust once, not need to adjust it again each sync
     * ----------------------------------------------------
     * @return bool
     * @psalm-suppress UndefinedFunction
     */
    protected function isEnvReversedOnce()
    {
        if ($this->isEnvComplyToAdjustRequires() &&
            isset($this->info[static::class]['reversed']) &&
            $this->info[static::class]['reversed']
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check if need to adjust
     * @return bool
     * @psalm-suppress UndefinedFunction
     */
    public function isEnvComplyToAdjustRequires()
    {
        return apbct_is_plugin_active('litespeed-cache/litespeed-cache.php');
    }

    /**
     * Check if already adjusted
     * @return bool
     */
    public function hasEnvBeenAdjustedByModule()
    {
        return isset($this->info[static::class]['changed']) && $this->info[static::class]['changed'];
    }

    /**
     * Adjust
     * @return void
     * @psalm-suppress UndefinedClass
     * @psalm-suppress UnusedVariable
     */
    protected function doAdjust()
    {
        $this->setLiteSpeedCacheTTLState(false);
        $this->setLiteSpeedCacheJsExcludesState(false);

        $this->changed = true;
        $this->keepEnvChangesByModule();
    }

    /**
     * Reverse the adjustments made by the adjust() method
     * @return void
     * @psalm-suppress UndefinedClass
     * @psalm-suppress PossiblyUnusedMethod
     * @psalm-suppress UnusedVariable
     */
    public function doReverseAdjust()
    {
        $this->setLiteSpeedCacheTTLState(true);
        $this->setLiteSpeedCacheJsExcludesState(true);

        $this->changed = false;
        $this->reversed = true;
        $this->keepEnvChangesByModule();
    }

    /**
     * Keep info what we changed
     * @return void
     */
    protected function keepEnvChangesByModule()
    {
        $comment = 'Disabled "The TTL option value is set to one day" and added exclusion for js files';

        if (!$this->changed) {
            $comment = 'Enable "Return TTL to the previous value" and remove exclusion for js files';
        }

        $this->info[static::class]['reversed'] = $this->reversed;
        $this->info[static::class]['changed'] = $this->changed;
        $this->info[static::class]['last_intervention_date'] = date('Y-m-d H:i:s');
        $this->info[static::class]['comment'] = $comment;
    }

    /**
     * This method is prepared to get current state of env. It will be required to handle this in realtime.
     * @return bool|null
     * @psalm-suppress PossiblyUnusedMethod
     * @psalm-suppress UndefinedClass
     */
    public function isEnvInRequiredStateNow()
    {
        $state = null;

        if (
            apbct_is_plugin_active('litespeed-cache/litespeed-cache.php') &&
            get_option('litespeed.conf.cache-ttl_pub') &&
            get_option('litespeed.conf.optm-js_exc')
        ) {
            try {
                $current_config_ttl_pub = (bool)get_option('litespeed.conf.cache-ttl_pub');
                $state = $current_config_ttl_pub;
            } catch (\Exception $e) {
                error_log('Antispam by CleanTalk error: ' . __METHOD__ . ' ' . $e->getMessage());
            }
        }

        return is_null($state);
    }

    /**
     * @param $state
     * @return void
     * @psalm-suppress UndefinedClass
     * @psalm-suppress PossiblyUnusedMethod
     * @psalm-suppress UnusedVariable
     */
    private function setLiteSpeedCacheTTLState($state)
    {
        $state = (bool)$state;

        if (
            apbct_is_plugin_active('litespeed-cache/litespeed-cache.php') &&
            get_option('litespeed.conf.cache-ttl_pub')
        ) {
            $current_config_ttl_pub = (int)get_option('litespeed.conf.cache-ttl_pub');

            if (!isset($this->info[static::class]) || !key_exists('original_config_ttl_pub', $this->info[static::class]) ) {
                $this->info[static::class]['original_config_ttl_pub'] = $current_config_ttl_pub;
            }

            if ($current_config_ttl_pub > 86400 && $state === false) {
                update_option('litespeed.conf.cache-ttl_pub', 86400);
            } else {
                update_option('litespeed.conf.cache-ttl_pub', $this->info[static::class]['original_config_ttl_pub']);
            }

            try {
                if (class_exists('\LiteSpeed\Purge')) {
                    Purge::purge_all();
                }
            } catch (\Exception $e) {
                error_log('Antispam by CleanTalk error: ' . __METHOD__ . ' ' . $e->getMessage());
            }
        }
    }

    /**
     * Set LiteSpeed Cache JS Excludes JS
     * @param $state
     * @return void
     * @psalm-suppress UndefinedClass
     * @psalm-suppress PossiblyUnusedMethod
     * @psalm-suppress UnusedVariable
     */
    private function setLiteSpeedCacheJsExcludesState($state)
    {
        $state = (bool)$state;

        if (!(
            apbct_is_plugin_active('litespeed-cache/litespeed-cache.php') &&
            get_option('litespeed.conf.optm-js_exc') &&
            is_string(get_option('litespeed.conf.optm-js_exc'))
        )) {
            return;
        }

        $current_js_exc = get_option('litespeed.conf.optm-js_exc');

        if (!isset($this->info[static::class]) || !key_exists('original_config_js_exc', $this->info[static::class]) ) {
            $this->info[static::class]['original_config_js_exc'] = $current_js_exc;
        }

        try {
            if ($state === false) {
                $current_js_exc_array = json_decode($current_js_exc);
                $current_js_exc_array[] = 'apbct-public-bundle.min.js';
                update_option('litespeed.conf.optm-js_exc', json_encode($current_js_exc_array));
            } else {
                $current_js_exc_array = json_decode($current_js_exc);
                $current_js_exc_array = array_filter($current_js_exc_array, function ($value) {
                    return $value !== 'apbct-public-bundle.min.js';
                });
                update_option('litespeed.conf.optm-js_exc', json_encode($current_js_exc_array));
            }

            if (class_exists('\LiteSpeed\Purge')) {
                Purge::purge_all();
            }
        } catch (\Exception $e) {
            error_log('Antispam by CleanTalk error: ' . __METHOD__ . ' ' . $e->getMessage());
        }
    }
}
