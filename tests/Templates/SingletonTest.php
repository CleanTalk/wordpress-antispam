<?php

use Cleantalk\Variables\ServerVariables;
use Cleantalk\Templates\Singleton;
use PHPUnit\Framework\TestCase;

class SingletonTest extends TestCase
{
    public function testCreatingSingletonWithTrait()
    {
        $trait_mock = $this->getMockForAbstractClass(ServerVariables::class);
        $trait_object = $this->getObjectForTrait(Singleton::class);

        self::assertInstanceOf(get_class($trait_mock), $trait_mock::getInstance());
        self::assertInstanceOf(get_class($trait_object), $trait_object::getInstance());
    }
}
