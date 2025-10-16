<?php

namespace Cleantalk\Common\Templates;

if (! trait_exists('Cleantalk\Common\Templates\Singleton')) {
    trait Singleton
    {
        protected static $instance;

        public function __construct()
        {
        }

        public function __wakeup()
        {
        }

        public function __clone()
        {
        }

        /**
         * Constructor
         * @return $this
         */
        public static function getInstance($params = array())
        {
            if ( ! isset(static::$instance) ) {
                static::$instance = new static();
                static::$instance->init($params);
            }
            return static::$instance;
        }

        /**
         * Alternative constructor
         */
        protected function init()
        {
        }

        public static function resetInstance()
        {
            static::$instance = null;
        }
    }
}
