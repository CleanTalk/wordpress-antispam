<?php

use Cleantalk\ApbctWP\DB;
use Cleantalk\ApbctWP\Firewall\SFW;
use PHPUnit\Framework\TestCase;

class TestReplaceDataTablesAtomically extends TestCase
{
    private $db;
    private $test_table_name;
    private $test_table_temp;
    private $test_table_old;

    public function setUp(): void
    {
        $this->db = DB::getInstance();
        $this->test_table_name = $this->db->prefix . 'test_atomic_rename';
        $this->test_table_temp = $this->test_table_name . '_temp';
        $this->test_table_old = $this->test_table_name . '_old';
        
        // Clean up before each test
        $this->dropAllTestTables();
    }

    public function tearDown(): void
    {
        // Clean up after each test
        $this->dropAllTestTables();
    }

    private function dropAllTestTables(): void
    {
        $this->db->execute('DROP TABLE IF EXISTS `' . $this->test_table_name . '`;');
        $this->db->execute('DROP TABLE IF EXISTS `' . $this->test_table_temp . '`;');
        $this->db->execute('DROP TABLE IF EXISTS `' . $this->test_table_old . '`;');
    }

    private function createTable(string $table_name, int $test_value = 1): void
    {
        $this->db->execute(
            'CREATE TABLE `' . $table_name . '` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                network INT UNSIGNED NOT NULL,
                mask INT UNSIGNED NOT NULL,
                status TINYINT NOT NULL DEFAULT 0
            );'
        );
        // Insert test data to identify which table we're reading from
        $this->db->execute(
            'INSERT INTO `' . $table_name . '` (network, mask, status) VALUES (' . $test_value . ', 4294967295, 0);'
        );
    }

    private function getTableFirstNetwork(string $table_name): ?int
    {
        $result = $this->db->fetch('SELECT network FROM `' . $table_name . '` LIMIT 1;');
        return $result ? (int)$result['network'] : null;
    }

    /**
     * Test: Temp table doesn't exist - should return error
     */
    public function testErrorWhenTempTableNotExists(): void
    {
        // Create only main table, no temp
        $this->createTable($this->test_table_name, 100);

        $result = SFW::replaceDataTablesAtomically($this->db, $this->test_table_name);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('TEMP TABLE NOT EXISTS', $result['error']);
    }

    /**
     * Test: Main table exists - should be replaced with temp
     */
    public function testReplaceExistingMainTable(): void
    {
        // Create main table with value 100
        $this->createTable($this->test_table_name, 100);
        // Create temp table with value 200
        $this->createTable($this->test_table_temp, 200);

        $result = SFW::replaceDataTablesAtomically($this->db, $this->test_table_name);

        // Should succeed
        $this->assertTrue($result);
        
        // Main table should now have data from temp (200)
        $this->assertEquals(200, $this->getTableFirstNetwork($this->test_table_name));
        
        // Temp table should not exist
        $this->assertFalse($this->db->isTableExists($this->test_table_temp));
        
        // Old table should be cleaned up
        $this->assertFalse($this->db->isTableExists($this->test_table_old));
    }

    /**
     * Test: Main table doesn't exist - temp should become main
     */
    public function testCreateMainFromTemp(): void
    {
        // Create only temp table with value 300
        $this->createTable($this->test_table_temp, 300);

        $result = SFW::replaceDataTablesAtomically($this->db, $this->test_table_name);

        // Should succeed
        $this->assertTrue($result);
        
        // Main table should now exist with data from temp
        $this->assertTrue($this->db->isTableExists($this->test_table_name));
        $this->assertEquals(300, $this->getTableFirstNetwork($this->test_table_name));
        
        // Temp table should not exist
        $this->assertFalse($this->db->isTableExists($this->test_table_temp));
    }

    /**
     * Test: Old table exists from previous failed update - should be cleaned up
     */
    public function testCleanupExistingOldTable(): void
    {
        // Simulate previous failed update: _old table exists
        $this->createTable($this->test_table_old, 50);
        $this->createTable($this->test_table_name, 100);
        $this->createTable($this->test_table_temp, 200);

        $result = SFW::replaceDataTablesAtomically($this->db, $this->test_table_name);

        // Should succeed
        $this->assertTrue($result);
        
        // Old table should be cleaned up
        $this->assertFalse($this->db->isTableExists($this->test_table_old));
        
        // Main should have new data
        $this->assertEquals(200, $this->getTableFirstNetwork($this->test_table_name));
    }

    /**
     * Test: Multiple tables at once
     */
    public function testMultipleTables(): void
    {
        $table1 = $this->test_table_name . '_1';
        $table2 = $this->test_table_name . '_2';

        // Create tables
        $this->createTable($table1, 100);
        $this->createTable($table1 . '_temp', 101);
        $this->createTable($table2, 200);
        $this->createTable($table2 . '_temp', 201);

        $result = SFW::replaceDataTablesAtomically($this->db, [$table1, $table2]);

        // Should succeed
        $this->assertTrue($result);
        
        // Both tables should have new data
        $this->assertEquals(101, $this->getTableFirstNetwork($table1));
        $this->assertEquals(201, $this->getTableFirstNetwork($table2));

        // Cleanup
        $this->db->execute('DROP TABLE IF EXISTS `' . $table1 . '`;');
        $this->db->execute('DROP TABLE IF EXISTS `' . $table2 . '`;');
    }

    /**
     * Test: Atomicity - main table is never missing during operation
     * This is more of a conceptual test - RENAME TABLE is atomic in MySQL
     */
    public function testAtomicityMainTableAlwaysAccessible(): void
    {
        $this->createTable($this->test_table_name, 100);
        $this->createTable($this->test_table_temp, 200);

        // Before rename - main exists
        $this->assertTrue($this->db->isTableExists($this->test_table_name));
        
        $result = SFW::replaceDataTablesAtomically($this->db, $this->test_table_name);
        
        // After rename - main still exists (with new data)
        $this->assertTrue($result);
        $this->assertTrue($this->db->isTableExists($this->test_table_name));
    }

    /**
     * Test: Empty array input
     */
    public function testEmptyArrayInput(): void
    {
        $result = SFW::replaceDataTablesAtomically($this->db, []);

        // Should succeed (nothing to do)
        $this->assertTrue($result);
    }

    /**
     * Test: String input is cast to array
     */
    public function testStringInputCastToArray(): void
    {
        $this->createTable($this->test_table_temp, 500);

        // Pass string instead of array
        $result = SFW::replaceDataTablesAtomically($this->db, $this->test_table_name);

        $this->assertTrue($result);
        $this->assertEquals(500, $this->getTableFirstNetwork($this->test_table_name));
    }
}