<?php

namespace Cleantalk\Common\State;

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