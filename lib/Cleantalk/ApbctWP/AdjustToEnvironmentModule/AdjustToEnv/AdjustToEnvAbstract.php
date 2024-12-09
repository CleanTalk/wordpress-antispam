<?php

namespace Cleantalk\ApbctWP\AdjustToEnvironmentModule\AdjustToEnv;

abstract class AdjustToEnvAbstract
{
    protected $info;

    protected $changed = false;

    /**
     * @psalm-suppress PossiblyUnusedProperty
     */
    protected $reversed = false;

    public function __construct($info)
    {
        $this->info = $info;
    }

    /**
     * Run
     * @return void
     */
    public function run()
    {
        if (!$this->isEnvReversedOnce()) {
            $this->doAdjust();
        }
    }

    /**
     * Run one
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function runOne()
    {
        if (!$this->hasEnvBeenAdjustedByModule()) {
            $this->doAdjust();
        }
    }

    public function getUpdatedInfo()
    {
        return $this->info;
    }

    /**
     * Check if need to adjust
     * @return bool
     */
    abstract protected function isEnvReversedOnce();

    /**
     * Check if need to adjust
     * @return bool
     */
    abstract protected function isEnvComplyToAdjustRequires();

    /**
     * Check if need to adjust
     * @return bool
     */
    abstract protected function hasEnvBeenAdjustedByModule();

    /**
     * Adjust
     * @return void
     */
    abstract protected function doAdjust();

    /**
     * Reverse the adjustments made by the adjust() method
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    abstract protected function doReverseAdjust();

    /**
     * Keep info what we changed
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    abstract protected function keepEnvChangesByModule();

    /**
     * This method is prepared to get current state of env. It will be required to handle this in realtime.
     * @return mixed
     * @psalm-suppress PossiblyUnusedMethod
     */
    abstract public function isEnvInRequiredStateNow();
}
