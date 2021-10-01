<?php

namespace Cleantalk\ApbctWP\State;

class OptionsFactory
{
    public $options = array();

    /**
     * @param array $options
     */
    public function __construct($options = array('settings'), $is_network = false)
    {
        if ( ! is_array($options) ) {
            //throw new cleantalkInvalidArgumentException('$options must be an array.');
        }

        if ( ! count($options) ) {
            //throw new cleantalkInvalidArgumentException('$options must not be empty.');
        }

        // Additional options for WPMS
        $options[] = 'network_settings';
        $options[] = 'network_data';

        foreach ( $options as $option ) {
            $class = __NAMESPACE__ . '\\' . ucfirst($option);
            if ( class_exists($class) ) {
                $this->options[] = new $class();
            } else {
                //throw new cleantalkInvalidArgumentException('$options must not be empty.');
            }
        }
    }
}
