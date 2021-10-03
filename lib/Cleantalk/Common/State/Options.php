<?php

namespace Cleantalk\Common\State;

/** @psalm-suppress PossiblyUnusedProperty */
abstract class Options
{
    public $defaults;

    public function __construct()
    {
        $this->defaults = $this->setDefaults();
    }

    /**
     * @return array
     */
    abstract protected function setDefaults();
}
