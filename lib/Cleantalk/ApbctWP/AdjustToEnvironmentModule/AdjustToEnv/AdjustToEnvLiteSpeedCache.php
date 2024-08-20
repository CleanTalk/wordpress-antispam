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
    }

    /**
     * Check if need to adjust
     * @return bool
     * @psalm-suppress UndefinedFunction
     */
    protected function isEnvReversedOnce()
    {
        // if site admin request to reverse adjust once, not need to adjust it again
        return $this->isEnvComplyToAdjustRequires() && $this->hasEnvBeenAdjustedByModule();
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
    }

    /**
     * Keep info what we changed
     * @return void
     */
    protected function keepEnvChangesByModule()
    {
        $comment = 'Disabled "The TTL option value is set to one day';

        if (!$this->changed) {
            $comment = 'Enable "Return TTL to the previous value"';
        }

        $last_info = [
            'changed' => $this->changed,
            'last_intervention_date' => date('Y-m-d H:i:s'),
            'comment' => $comment,
        ];

        $this->info[static::class] = array_merge(
            $this->info,
            $last_info
        );
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
            (int)get_option('litespeed.conf.cache-ttl_pub')
        ) {
            try {
                $current_config_ttl_pub = (bool)get_option('litespeed.conf.cache-ttl_pub');
                $state = $current_config_ttl_pub;
            } catch (\Exception $e) {
                error_log('Security by CleanTalk error: ' . __METHOD__ . ' ' . $e->getMessage());
            }
        }

        return $state;
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

            if ( !key_exists('original_config_ttl_pub', $this->info) ) {
                $this->info = [
                    'original_config_ttl_pub' => $current_config_ttl_pub,
                ];
            }

            if ($current_config_ttl_pub > 86400 && $state == false) {
                update_option('litespeed.conf.cache-ttl_pub', 86400);
            } else if (key_exists('original_config_ttl_pub', $this->info) && $state == true) {
                update_option('litespeed.conf.cache-ttl_pub', $this->info['original_config_ttl_pub']);
            }

            try {
                if (class_exists('\LiteSpeed\Purge')) {
                    Purge::purge_all();
                }
            } catch (\Exception $e) {
                error_log('Security by CleanTalk error: ' . __METHOD__ . ' ' . $e->getMessage());
            }

            $this->changed = !$state;
            $this->keepEnvChangesByModule();
        }
    }
}
