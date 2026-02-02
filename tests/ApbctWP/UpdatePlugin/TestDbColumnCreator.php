<?php

use Cleantalk\ApbctWP\UpdatePlugin\DbColumnCreator;
use Cleantalk\ApbctWP\UpdatePlugin\DbTablesCreator;
use Cleantalk\Common\Schema;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cleantalk\ApbctWP\UpdatePlugin\DbColumnCreator
 */
class DbColumnCreatorIndexesIntegrationTest extends TestCase
{
    private static $tablePrefix = 'cleantalk_';
    private $dbColumnCreator;
    private $dbTablesCreator;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$tablePrefix = Schema::getSchemaTablePrefix();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->dbTablesCreator = new DbTablesCreator();
    }

    /**
     * @dataProvider tableSchemaProvider
     */
    public function testIndexesCreationFromSchema($tableKey, $schema)
    {
        $tableName = self::$tablePrefix . $tableKey;

        // Arrange: create table via DbTablesCreator (correct structure)
        $this->createTableViaDbTablesCreator($tableName, $tableKey);

        // Remove all non-PRIMARY indexes for test
        $this->dropAllNonPrimaryIndexes($tableName);

        // Act: run updateIndexes
        $this->dbColumnCreator = new DbColumnCreator($tableName);
        $errors = $this->executeUpdateIndexes($schema['__indexes'], array_keys($schema));

        // Assert
        $this->assertEmpty($errors, "There should be no errors when creating indexes for the table {$tableKey}");

        $actualIndexes = $this->getTableIndexes($tableName);
        $expectedIndexes = $this->parseIndexesFromSchema($schema['__indexes']);

        foreach ($expectedIndexes as $indexName => $columns) {
            if ($indexName === 'PRIMARY') {
                $this->assertArrayHasKey(
                    'PRIMARY',
                    $actualIndexes,
                    "PRIMARY KEY should exist in table {$tableKey}"
                );
            } else {
                $this->assertArrayHasKey(
                    $indexName,
                    $actualIndexes,
                    "Index {$indexName} have to be created into table {$tableKey}"
                );

                $this->assertEquals(
                    $columns,
                    $actualIndexes[$indexName],
                    "Index columns {$indexName} have to be same as in table {$tableKey}"
                );
            }
        }
    }

    /**
     * @dataProvider tableSchemaProvider
     */
    public function testIndexesUpdateWhenChanged($tableKey, $schema)
    {
        $tableName = self::$tablePrefix . $tableKey;

        // Arrange: create correct table
        $this->createTableViaDbTablesCreator($tableName, $tableKey);

        // Change indexes to incorrect ones
        $this->dropAllNonPrimaryIndexes($tableName);
        $firstColumn = $this->getFirstNonIdColumn($schema);
        if ($firstColumn) {
            global $wpdb;
            $wpdb->query("ALTER TABLE `{$tableName}` ADD INDEX `wrong_single_idx` (`{$firstColumn}`)");
        }

        // Act
        $this->dbColumnCreator = new DbColumnCreator($tableName);
        $errors = $this->executeUpdateIndexes($schema['__indexes'], array_keys($schema));

        // Assert
        $this->assertEmpty($errors, "There should be no errors when updating indexes for the table {$tableKey}");

        $actualIndexes = $this->getTableIndexes($tableName);
        $expectedIndexes = $this->parseIndexesFromSchema($schema['__indexes']);

        foreach ($expectedIndexes as $indexName => $columns) {
            if ($indexName !== 'PRIMARY') {
                $this->assertArrayHasKey($indexName, $actualIndexes);
                $this->assertEquals($columns, $actualIndexes[$indexName]);
            }
        }

        // Check that wrong index was removed
        $this->assertArrayNotHasKey('wrong_single_idx', $actualIndexes);
    }

    /**
     * @dataProvider tableSchemaProvider
     */
    public function testRemoveExcessIndexes($tableKey, $schema)
    {
        $tableName = self::$tablePrefix . $tableKey;

        // Arrange: create correct table
        $this->createTableViaDbTablesCreator($tableName, $tableKey);

        // Add extra indexes
        global $wpdb;
        $columns = array_keys($schema);
        foreach ($columns as $column) {
            if ($column !== 'id' && $column !== '__indexes' && $column !== '__createkey') {
                $wpdb->query("ALTER TABLE `{$tableName}` ADD INDEX `excess_{$column}_idx` (`{$column}`)");
                break;
            }
        }

        // Act: DbColumnCreator should remove extra indexes
        $this->dbColumnCreator = new DbColumnCreator($tableName);
        $errors = $this->executeUpdateIndexes($schema['__indexes'], array_keys($schema));

        // Assert
        $this->assertEmpty($errors);

        $actualIndexes = $this->getTableIndexes($tableName);
        $expectedIndexes = $this->parseIndexesFromSchema($schema['__indexes']);

        // Check there are no extra indexes
        foreach ($actualIndexes as $indexName => $columns) {
            if ($indexName !== 'PRIMARY') {
                $this->assertArrayHasKey(
                    $indexName,
                    $expectedIndexes,
                    "Index {$indexName} is an extra-index in table {$tableKey}"
                );
            }
        }
    }

    /**
     * DataProvider: all tables from Schema
     */
    public function tableSchemaProvider()
    {
        $schemas = Schema::getStructureSchemas();
        $data = [];

        foreach ($schemas as $tableKey => $schema) {
            if (empty($schema['__indexes'])) {
                continue;
            }

            $data[$tableKey] = [$tableKey, $schema];
        }

        return $data;
    }

    /**
     * Create table via DbTablesCreator (correct structure)
     */
    private function createTableViaDbTablesCreator($tableName)
    {
        global $wpdb;

        // Drop if exists
        $wpdb->query("DROP TABLE IF EXISTS `{$tableName}`");

        // Use DbTablesCreator to create table with correct schema
        $this->dbTablesCreator->createTable($tableName);
    }

    /**
     * Remove all non-PRIMARY indexes from table
     */
    private function dropAllNonPrimaryIndexes($tableName)
    {
        global $wpdb;

        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$tableName}`", ARRAY_A);

        foreach ($indexes as $index) {
            if ($index['Key_name'] !== 'PRIMARY') {
                // Check if index exists before trying to drop it
                $check_sql = $wpdb->prepare(
                    "SELECT COUNT(*) FROM information_schema.statistics 
                WHERE table_schema = DATABASE() 
                AND table_name = %s 
                AND index_name = %s",
                    $tableName,
                    $index['Key_name']
                );

                $exists = $wpdb->get_var($check_sql);

                if ($exists) {
                    $wpdb->query("ALTER TABLE `{$tableName}` DROP INDEX `{$index['Key_name']}`");
                }
            }
        }
    }

    /**
     * Get table indexes
     */
    private function getTableIndexes($tableName)
    {
        global $wpdb;

        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$tableName}`", ARRAY_A);
        $result = [];

        foreach ($indexes as $index) {
            $result[$index['Key_name']][] = $index['Column_name'];
        }

        return $result;
    }

    /**
     * Parse indexes from schema string
     */
    private function parseIndexesFromSchema($indexesString)
    {
        $result = [];

        if (empty($indexesString)) {
            return $result;
        }

        preg_match_all('/(PRIMARY\s+KEY|INDEX|KEY)\s*(?:`?(\w+)`?\s*)?\(([^)]+)\)/i', $indexesString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $type = strtoupper($match[1]);
            $indexName = !empty($match[2]) ? $match[2] : null;
            $columns = preg_split('/\s*,\s*/', trim($match[3], '` '));

            $normalizedColumns = array_map(function($col) {
                return trim($col, '` ');
            }, $columns);

            if ($type === 'PRIMARY KEY' || $type === 'PRIMARY') {
                $result['PRIMARY'] = $normalizedColumns;
            } else {
                if (empty($indexName)) {
                    $indexName = $normalizedColumns[0];
                }
                $result[$indexName] = $normalizedColumns;
            }
        }

        return $result;
    }

    /**
     * Get first non-id column from schema
     */
    private function getFirstNonIdColumn($schema)
    {
        foreach ($schema as $column => $definition) {
            if ($column !== 'id' && $column !== '__indexes' && $column !== '__createkey') {
                return $column;
            }
        }
        return null;
    }

    /**
     * Call private method updateIndexes via reflection
     */
    private function executeUpdateIndexes($schemaIndexesRaw, $dbColumnNames)
    {
        $reflection = new \ReflectionClass($this->dbColumnCreator);
        $method = $reflection->getMethod('updateIndexes');
        $method->setAccessible(true);

        $errors = [];
        $method->invokeArgs($this->dbColumnCreator, [$schemaIndexesRaw, $dbColumnNames, &$errors]);

        return $errors;
    }

    protected function tearDown(): void
    {
        global $wpdb;

        $schemas = Schema::getStructureSchemas();
        foreach (array_keys($schemas) as $tableKey) {
            $tableName = self::$tablePrefix . $tableKey;
            $wpdb->query("DROP TABLE IF EXISTS `{$tableName}`");
        }

        parent::tearDown();
    }
}
