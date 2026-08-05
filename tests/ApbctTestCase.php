<?php

use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

class ApbctTestCase extends TestCase
{
    /**
     * @var State
     */
    protected static $state_storage;

    public static function setUpBeforeClass() : void
    {
        parent::setUpBeforeClass();
        self::prepareState(static::class);
    }

    public static function tearDownAfterClass() : void
    {
        self::restoreState(static::class);
        parent::tearDownAfterClass();
    }

    public static function prepareState($_caller_class) {
        if (!property_exists(static::class, 'state_storage')) {
            throw new \Exception(
                'APBCT State save failed: static $state_storage is invalid, static caller: '
                . $_caller_class
                . ', maybe you forget to call parent::setUpBeforeClass()?'
            );
        }
        global $apbct;
        static::$state_storage = $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
    }

    public static function restoreState($_caller_class) {
        if (!(static::$state_storage instanceof State)) {
            throw new \Exception(
                'APBCT State restore failed: static $state_storage is invalid, static caller: '
                . $_caller_class
                . ', did you forget to call parent::tearDownAfterClass()?'
            );
        }
        global $apbct;
        $apbct = static::$state_storage;
    }
}

