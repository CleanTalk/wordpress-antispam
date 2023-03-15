<?php

namespace Cleantalk\Templates;

if (! trait_exists('Cleantalk\Templates\Singleton')) {
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
            if ( ! isset(static::$instance[static::class]) ) {
                static::$instance[static::class] = new static();
                $params                          = array_values($params);
                static::$instance[static::class]->init(...$params);
            }

            return static::$instance[static::class];
        }

        /**
         * Alternative constructor
         * @param array $_params Parameters to initialize the instance
         */
        protected function init(...$_params)
        {
        }
    }
}
