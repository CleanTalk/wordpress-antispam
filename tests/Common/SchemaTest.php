<?php

use Cleantalk\Common\Schema;

class SchemaTest extends \PHPUnit\Framework\TestCase
{

    public function testgetSchema()
    {
        $this->expectException(Exception::class);
        $this->assertIsArray(
            Schema::getSchema()
        );
        $this->assertIsArray(
            Schema::getSchema('sfw')
        );
        $this->assertIsArray(
            Schema::getSchema(' ')
        );
        $this->assertIsArray(
            Schema::getSchema('')
        );
        $this->assertIsArray(
            Schema::getSchema('wrong')
        );
        $this->assertIsArray(
            Schema::getSchema(false)
        );
        $this->assertIsArray(
            Schema::getSchema(true)
        );
    }

}