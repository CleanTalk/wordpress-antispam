<?php

namespace Cleantalk\ApbctWP\UpdatePlugin;

use Cleantalk\Common\Schema;

class DbTablesCreator
{
    /**
     * Create all plugin tables from Schema
     */
    public function createAllTables($wpdb_prefix = '')
    {
        global $wpdb;
        $wpdb->show_errors = true;
        $db_schema = Schema::getStructureSchemas();
        $schema_prefix = Schema::getSchemaTablePrefix();
        $wpdb_prefix = $wpdb_prefix ?: $wpdb->prefix;

        foreach ($db_schema as $table_key => $table_schema) {
            $sql = 'CREATE TABLE IF NOT EXISTS `%s' . $schema_prefix . $table_key . '` (';
            $sql = sprintf($sql, $wpdb_prefix);
            foreach ($table_schema as $column_name => $column_params) {
                if ($column_name !== '__indexes' && $column_name !== '__createkey') {
                    $sql .= '`' . $column_name . '` ' . $column_params . ', ';
                } elseif ($column_name === '__indexes') {
                    $sql .= $table_schema['__indexes'];
                }
            }
            $sql .= ');';

            $result = $wpdb->query($sql);
            if ($result === false) {
                $errors[] = "Failed.\nQuery: $wpdb->last_query\nError: $wpdb->last_error";
            }
        }

        // Logging errors
        if (!empty($errors)) {
            apbct_log($errors);
        }
    }

    /**
     * Create Table by table name
     */
    public function createTable($table_name)
    {
        global $wpdb;
        $wpdb->show_errors = true;
        $db_schema = Schema::getStructureSchemas();
        $schema_prefix = Schema::getSchemaTablePrefix();
        $table_key = explode($schema_prefix, $table_name)[1];

        $sql = 'CREATE TABLE IF NOT EXISTS `' . $table_name . '` (';
        foreach ($db_schema[$table_key] as $column_name => $column_params) {
            if ($column_name !== '__indexes' && $column_name !== '__createkey') {
                $sql .= '`' . $column_name . '` ' . $column_params . ', ';
            } elseif ($column_name === '__indexes') {
                $sql .= $db_schema[$table_key]['__indexes'];
            }
        }
        $sql .= ');';

        $result = $wpdb->query($sql);
        if ($result === false) {
            $errors[] = "Failed.\nQuery: $wpdb->last_query\nError: $wpdb->last_error";
        }

        // Logging errors
        if (!empty($errors)) {
            apbct_log($errors);
        }
    }
}
