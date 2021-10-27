<?php

use Cleantalk\Common\Schema;

class SchemaTest extends \PHPUnit\Framework\TestCase
{

    public function testgetSchema()
    {
        $this->assertIsArray(
            Schema::getStructureSchemas()
        );
    }

}