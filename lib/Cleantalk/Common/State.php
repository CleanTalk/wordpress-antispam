<?php

namespace Cleantalk\Common;

abstract class State
{
    /**
     * @var string
     */
    public $option_prefix = '';

    /**
     * @var array
     */
    protected $options;

    /**
     * @param string $option_prefix
     * @param array $options
     */
    public function __construct($option_prefix, $options = array('settings'))
    {
        $this->option_prefix = $option_prefix;
        $this->options       = $options;
        $this->setOptions();
        $this->setDefinitions();
        $this->init();
    }

    /**
     * Define necessary constants
     */
    abstract protected function setDefinitions();

    /**
     * Get options from the database
     * Set it to object
     */
    abstract protected function setOptions();

    /**
     * Adding some dynamic properties
     */
    abstract protected function init();


    abstract protected function getOption($option_name);

    abstract public function save($option_name, $use_prefix = true, $autoload = true);

    /**
     * @param $option_name
     * @param false $use_prefix
     *
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    abstract public function deleteOption($option_name, $use_prefix = false);
}
