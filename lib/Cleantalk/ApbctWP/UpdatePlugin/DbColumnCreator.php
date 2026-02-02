<?php

namespace Cleantalk\ApbctWP\UpdatePlugin;

use Cleantalk\Common\Schema;

class DbColumnCreator
{
    /**
     * Table name
     */
    private $dbTableName;

    /**
     * Tables that have been changed
     */
    private $dbTableChanged;

    /**
     * Table structure
     */
    private $dbTableStructure;

    public function __construct($table_name)
    {
        $this->dbTableName = $table_name;
        $this->dbTableChanged = false;
    }

    /**
     * Create columns and drop excess columns
     */
    public function execute()
    {
        global $wpdb;

        $query = 'SHOW COLUMNS FROM ' . $this->dbTableName;
        $this->dbTableStructure = $wpdb->get_results($query, ARRAY_A);

        if ($this->dbTableStructure) {
            $this->addColumnsIfNotExists();
        }
    }

    /**
     * Analise table columns, add not exists columns
     */
    private function addColumnsIfNotExists()
    {
        global $wpdb;
        $errors = array();
        $wpdb->show_errors = true;
        $schema_table_structure = Schema::getStructureSchemas();
        $table_key = explode(Schema::getSchemaTablePrefix(), $this->dbTableName)[1];
        $db_column_names = array();

        // Create array of column names from DB structure
        foreach ($this->dbTableStructure as $column) {
            $db_column_names[] = $column['Field'];
        }

        // Add primary key
        if (! in_array('id', $db_column_names)) {
            $db_column_names[] = 'id';
            $sql = "ALTER TABLE `$this->dbTableName`";
            foreach ($this->dbTableStructure as $column) {
                if (strtolower($column['Key']) === 'pri') {
                    $sql .= " DROP PRIMARY KEY,";
                }
            }
            $sql .= " ADD COLUMN `id`";
            $sql .= " " . $schema_table_structure[$table_key]['__createkey'];
            $result = $wpdb->query($sql);
            $this->dbTableChanged = true;
            if ($result === false) {
                $errors[] = "Failed.\nQuery: $wpdb->last_query\nError: $wpdb->last_error";
            }
        }

        // Add columns
        $counter = 0;
        foreach ($schema_table_structure[$table_key] as $schema_column_name => $schema_column_params) {
            if (! in_array($schema_column_name, $db_column_names) && $schema_column_name !== '__indexes' && $schema_column_name !== '__createkey') {
                $sql = "ALTER TABLE `$this->dbTableName`";
                $sql .= " ADD COLUMN $schema_column_name";
                $sql .= " $schema_column_params";
                if ($counter !== 0) {
                    $schema_prev_column_name = array_keys($schema_table_structure[$table_key])[$counter - 1];
                    $sql .= " AFTER $schema_prev_column_name";
                }

                $result = $wpdb->query($sql);
                $this->dbTableChanged = true;
                if ($result === false) {
                    $errors[] = "Failed.\nQuery: $wpdb->last_query\nError: $wpdb->last_error";
                }
            }
            $counter++;
        }

        // Update indexes
        if ( isset($schema_table_structure[$table_key]['__indexes']) ) {
            $this->updateIndexes($schema_table_structure[$table_key]['__indexes'], $db_column_names, $errors);
        }

        // Logging errors
        if (!empty($errors)) {
            DbAnalyzer::logSchemaErrors($errors, __FUNCTION__);
        }
    }

    /**
     * Update indexes based on schema definition
     *
     * @param mixed $schema_indexes_raw Index definitions from schema
     * @param array $db_column_names Array of existing column names in the database
     * @param array $errors Reference to errors array to append any errors
     * @return void
     */
    private function updateIndexes($schema_indexes_raw, $db_column_names, &$errors)
    {
        global $wpdb;

        // Extract index definitions from schema
        $schema_indexes = array();
        $schema_index_names = array();
        $db_indexes_raw = $wpdb->get_results("SHOW INDEX FROM `$this->dbTableName`", ARRAY_A);

        if (is_string($schema_indexes_raw) && !empty($schema_indexes_raw)) {
            // Parse index definitions from string like: "PRIMARY KEY (`id`), INDEX (  `network` ,  `mask` ), INDEX ( `status` )"
            preg_match_all('/(?:PRIMARY\s+KEY|INDEX)\s*\([^)]+\)/i', $schema_indexes_raw, $matches);
            if (isset($matches[0]) && !empty($matches[0])) {
                foreach ($matches[0] as $match) {
                    // Extract column names from index definition
                    if (preg_match('/\(([^)]+)\)/', $match, $col_match) && isset($col_match[1])) {
                        $columns_raw = trim($col_match[1]);
                        $columns = preg_split('/\s*,\s*/', $columns_raw);
                        // Normalize column names
                        $normalized_columns = array();
                        foreach ($columns as $col) {
                            $normalized_columns[] = trim($col, '` ');
                        }

                        // Skip PRIMARY KEY as it should already exist if table has primary key
                        if (stripos($match, 'PRIMARY') !== false) {
                            continue;
                        }

                        if (!empty($normalized_columns)) {
                            $index_name = $normalized_columns[0];

                            $normalized_def = 'INDEX (' . implode(',', array_map(function ($col) {
                                return '`' . $col . '`';
                            }, $normalized_columns)) . ')';

                            // Use first column as index name
                            $schema_indexes[$index_name] = $normalized_def;
                            $schema_index_names[] = $index_name;
                        }
                    }
                }
            }
        } elseif (is_array($schema_indexes_raw)) {
            // If it's already an array, assume it's in format: index_name => definition
            foreach ($schema_indexes_raw as $index_name => $index_def) {
                if (is_string($index_name)) {
                    $schema_indexes[$index_name] = $index_def;
                    $schema_index_names[] = $index_name;
                } else {
                    $schema_index_names[] = $index_def;
                }
            }
        }

        // Extract index names and definitions from DB results
        $db_indexes = array(); // Index names
        $db_index_definitions = array(); // Index name => array of columns (normalized)
        if (is_array($db_indexes_raw)) {
            foreach ($db_indexes_raw as $index_row) {
                if (isset($index_row['Key_name'])) {
                    $key_name = $index_row['Key_name'];
                    if (!in_array($key_name, $db_indexes)) {
                        $db_indexes[] = $key_name;
                    }
                    // Build index definition: collect all columns for this index
                    if (!isset($db_index_definitions[$key_name])) {
                        $db_index_definitions[$key_name] = array();
                    }
                    // Store column name and sequence (order matters for composite indexes)
                    $column_name = isset($index_row['Column_name']) ? trim($index_row['Column_name'], '` ') : '';
                    $seq_in_index = isset($index_row['Seq_in_index']) ? (int)$index_row['Seq_in_index'] : 0;
                    if (!empty($column_name)) {
                        $db_index_definitions[$key_name][$seq_in_index] = $column_name;
                    }
                }
            }
            // Normalize: sort by sequence and create comma-separated string for comparison
            foreach ($db_index_definitions as $key_name => $columns) {
                ksort($columns);
                $db_index_definitions[$key_name] = implode(',', $columns);
            }
        }

        // Build normalized schema index definitions for comparison
        $schema_index_definitions = array();
        foreach ($schema_indexes as $index_name => $index_def) {
            // Extract columns from schema definition
            if (preg_match('/\(([^)]+)\)/', $index_def, $col_match) && isset($col_match[1])) {
                $columns = preg_split('/\s*,\s*/', trim($col_match[1], '` '));
                // Normalize: trim and create comma-separated string
                $normalized_columns = array();
                foreach ($columns as $col) {
                    $normalized_columns[] = trim($col, '` ');
                }
                $schema_index_definitions[$index_name] = implode(',', $normalized_columns);
            }
        }

        // Find indexes that need to be updated (exist in both but definitions differ)
        $indexes_to_update = array();
        foreach ($schema_index_names as $index_name) {
            if (in_array($index_name, $db_indexes)) {
                // Index exists in both - compare definitions
                $schema_def = isset($schema_index_definitions[$index_name]) && is_string($schema_index_definitions[$index_name]) ? $schema_index_definitions[$index_name] : '';
                $db_def = isset($db_index_definitions[$index_name]) && is_string($db_index_definitions[$index_name]) ? $db_index_definitions[$index_name] : '';
                // Compare normalized definitions (case-insensitive)
                if (strtolower($schema_def) !== strtolower($db_def)) {
                    $indexes_to_update[] = $index_name;
                }
            }
        }

        // Update indexes that have changed definitions
        if (!empty($indexes_to_update)) {
            foreach ($indexes_to_update as $index_name) {
                // Skip PRIMARY KEY - it should be handled separately
                if (strtoupper($index_name) === 'PRIMARY') {
                    continue;
                }

                // Drop the old index
                $sql = "ALTER TABLE `$this->dbTableName` DROP INDEX `$index_name`";
                $result = $wpdb->query($sql);
                if ($result === false) {
                    $errors[] = "Failed to drop index `$index_name` for update.\nQuery: $wpdb->last_query\nError: $wpdb->last_error";
                    continue; // Skip recreation if drop failed
                }

                // Recreate with new definition
                if (isset($schema_indexes[$index_name])) {
                    $index_def = $schema_indexes[$index_name];
                    if (preg_match('/\(([^)]+)\)/', $index_def, $col_match) && isset($col_match[1])) {
                        $columns = trim($col_match[1]);
                        $sql = "ALTER TABLE `$this->dbTableName` ADD INDEX `$index_name` ($columns)";
                        $result = $wpdb->query($sql);
                        if ($result === false) {
                            $errors[] = "Failed to recreate index `$index_name`.\nQuery: $wpdb->last_query\nError: $wpdb->last_error";
                        } else {
                            $this->dbTableChanged = true;
                        }
                    }
                }
            }
        }

        // Compare indexes - find indexes that are in schema but not in DB
        $schema_index_names_lower = array_map('strtolower', $schema_index_names);
        $db_indexes_lower = array_map('strtolower', $db_indexes);
        $diff_indexes_lower = array_diff($schema_index_names_lower, $db_indexes_lower);

        if (!empty($diff_indexes_lower)) {
            // Map back to original case for schema lookup
            $diff_indexes = array();
            foreach ($diff_indexes_lower as $lower_name) {
                $original_key = array_search($lower_name, $schema_index_names_lower);
                if ($original_key !== false) {
                    $diff_indexes[] = $schema_index_names[$original_key];
                }
            }

            // Add indexes to DB
            foreach ($diff_indexes as $diff_index_name) {
                // Get the full index definition from schema
                if (isset($schema_indexes[$diff_index_name])) {
                    $index_def = $schema_indexes[$diff_index_name];
                    // Create proper SQL: ALTER TABLE `table` ADD INDEX `name` (`column`)
                    // Parse the index definition to extract columns
                    if (preg_match('/\(([^)]+)\)/', $index_def, $col_match) && isset($col_match[1])) {
                        $columns = trim($col_match[1]);
                        $sql = "ALTER TABLE `$this->dbTableName` ADD INDEX `$diff_index_name` ($columns)";
                        $result = $wpdb->query($sql);
                        if ($result === false) {
                            $errors[] = "Failed to add index `$diff_index_name`.\nQuery: $wpdb->last_query\nError: $wpdb->last_error";
                        } else {
                            $this->dbTableChanged = true;
                        }
                    } else {
                        $errors[] = "Could not parse index definition for `$diff_index_name`: $index_def";
                    }
                } else {
                    // if we don't have the definition, try to create index on column with same name
                    if (in_array($diff_index_name, $db_column_names)) {
                        $sql = "ALTER TABLE `$this->dbTableName` ADD INDEX `$diff_index_name` (`$diff_index_name`)";
                        $result = $wpdb->query($sql);
                        if ($result === false) {
                            $errors[] = "Failed to add index `$diff_index_name`.\nQuery: $wpdb->last_query\nError: $wpdb->last_error";
                        } else {
                            $this->dbTableChanged = true;
                        }
                    } else {
                        $errors[] = "Index `$diff_index_name` not found in schema definitions and column doesn't exist for fallback creation.";
                    }
                }
            }
        }

        // Compare indexes - find indexes that are in DB but not in schema
        $excess_indexes = array_diff($db_indexes, $schema_index_names);
        if (!empty($excess_indexes)) {
            // Remove indexes that don't exist in schema
            foreach ($excess_indexes as $excess_index_name) {
                // Skip PRIMARY KEY - it should be handled separately
                if (strtoupper($excess_index_name) === 'PRIMARY') {
                    continue;
                }

                // Drop the index
                $sql = "ALTER TABLE `$this->dbTableName` DROP INDEX `$excess_index_name`";
                $result = $wpdb->query($sql);
                if ($result === false) {
                    $errors[] = "Failed to drop index `$excess_index_name`.\nQuery: $wpdb->last_query\nError: $wpdb->last_error";
                } else {
                    $this->dbTableChanged = true;
                }
            }
        }
    }

    /**
     * Get information about table changes
     */
    public function getTableChangedStatus()
    {
        return $this->dbTableChanged;
    }
}
