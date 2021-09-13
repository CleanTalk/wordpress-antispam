<?php

namespace Cleantalk\ApbctWP\UpdatePlugin;

use Cleantalk\Common\Schema;

class DbColumnCreator {
    
    /**
     * Table name
     */
    private $dbTableName;
    
    /**
     * Table structure
     */
    private $dbTableStructure;
    
    public function __construct($table_name)
    {
        $this->dbTableName = $table_name;
    }
    
    /**
     * Create columns and drop excess columns
     */
    public function execute()
    {
        global $wpdb;
        
        $query = 'SHOW COLUMNS FROM '.$this->dbTableName;
        $this->dbTableStructure = $wpdb->get_results( $query, ARRAY_A );
        
        if($this->dbTableStructure) {
            $this->addColumnsIfNotExists();
        }
    }
    
    /**
     * Analise table columns, add not exists columns
     */
    private function addColumnsIfNotExists()
    {
        global $wpdb;
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
            $result = $wpdb->query( $sql );
            if( $result === false ) {
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
                    $schema_prev_column_name = array_keys($schema_table_structure[$table_key])[$counter-1];
                    $sql .= " AFTER $schema_prev_column_name";
                }
                
                $result = $wpdb->query( $sql );
                if( $result === false ) {
                    $errors[] = "Failed.\nQuery: $wpdb->last_query\nError: $wpdb->last_error";
                }
            }
            $counter++;
        }
        
        $wpdb->show_errors = true;
        
        // Logging errors
        if(!empty($errors)) {
            apbct_log( $errors );
        }
    }
    
    /**
     * Create column in table
     */
    public function createColumn ($table_name, $column_name, $column_params, $after = '')
    {
        global $wpdb;
        
        $sql = "ALTER TABLE `$table_name`";
        $sql .= " ADD COLUMN $column_name";
        $sql .= " $column_params";
        if ($after) {
            $sql .= " AFTER $after";
        }
        
        $result = $wpdb->query( $sql );
        if( $result === false ) {
            $errors[] = "Failed.\nQuery: $wpdb->last_query\nError: $wpdb->last_error";
        }
        
        $wpdb->show_errors = true;
        
        // Logging errors
        if(!empty($errors)) {
            apbct_log( $errors );
        }
    }
}