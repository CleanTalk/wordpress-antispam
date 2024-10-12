<?php

namespace Cleantalk\ApbctWP\AdjustToEnvironmentModule\AdjustToEnv;

class AdjustToEnvW3TotalCache extends AdjustToEnvAbstract
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
        return apbct_is_plugin_active('w3-total-cache/w3-total-cache.php');
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
        $this->setW3queryCachingState(false);
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
        $this->setW3queryCachingState(true);
    }

    /**
     * Keep info what we changed
     * @return void
     */
    protected function keepEnvChangesByModule()
    {
        $comment = 'Disabled "Cache URIs with query string variables" option.'
            . 'Because it can break AJAX requests and there not possible to add exclusion to check that request contains query string or not.';

        if (!$this->changed) {
            $comment = 'Enable "Cache URIs with query string variables" option back. By site admin request.';
        }

        $this->info[static::class] = [
            'changed' => $this->changed,
            'last_intervention_date' => date('Y-m-d H:i:s'),
            'comment' => $comment,
        ];
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
            apbct_is_plugin_active('w3-total-cache/w3-total-cache.php') &&
            class_exists('\W3TC\Dispatcher')
        ) {
            try {
                $original_config = \W3TC\Dispatcher::config();
                $state = (bool)$original_config->get('pgcache.cache.query');
            } catch (\Exception $e) {
                error_log('Antispam by CleanTalk error: ' . __METHOD__ . ' ' . $e->getMessage());
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
    private function setW3queryCachingState($state)
    {
        $state = (bool)$state;
        if (
            apbct_is_plugin_active('w3-total-cache/w3-total-cache.php') &&
            class_exists('\W3TC\Dispatcher') &&
            class_exists('\W3TC\Config') &&
            class_exists('\W3TC\Util_Admin') &&
            class_exists('\W3TC\Cdnfsd_CacheFlush')
        ) {
            try {
                $original_config = \W3TC\Dispatcher::config();
                $config = new \W3TC\Config();
                $config->set('pgcache.cache.query', $state);
                \W3TC\Util_Admin::config_save($original_config, $config);

                \W3TC\Cdnfsd_CacheFlush::w3tc_flush_all();

                $this->changed = !$state;
                $this->keepEnvChangesByModule();
            } catch (\Exception $e) {
                error_log('Antispam by CleanTalk error: ' . __METHOD__ . ' ' . $e->getMessage());
            }
        }
    }
}
