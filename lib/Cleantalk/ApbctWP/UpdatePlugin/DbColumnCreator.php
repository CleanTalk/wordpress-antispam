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

        // Logging errors
        if (!empty($errors)) {
            apbct_log($errors);
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
