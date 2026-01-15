<?php

namespace Cleantalk\ApbctWP\Promotions;

abstract class Promotion
{
    /**
     * @var string
     */
    protected static $setting_name = '';

    public function init()
    {
        if (self::settingEnabled() && $this->couldPerformActions()) {
            $this->performActions();
        }
    }

    /**
     * Return if APBCT setting is enabled.
     * @return bool
     */
    protected static function settingEnabled()
    {
        global $apbct;
        return !empty(static::$setting_name)
               && isset($apbct->settings[static::$setting_name])
               && $apbct->settings[static::$setting_name];
    }

    /**
     * Additional condition if actions should be performed.
     * @return bool
     */
    abstract protected function couldPerformActions();

    /**
     * Perform all the actions needs for promotion.
     * @return void
     */
    abstract protected function performActions();

    /**
     * Return description for APBCT settings.
     * @return String
     */
    abstract protected function getDescriptionForSettings();

    /**
     * Return title for APBCT settings.
     * @return String
     */
    abstract protected function getTitleForSettings();
}
