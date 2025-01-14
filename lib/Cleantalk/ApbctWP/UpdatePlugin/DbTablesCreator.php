<?php

namespace Cleantalk\ApbctWP\UpdatePlugin;

use Cleantalk\Common\Schema;

class DbTablesCreator
{
    /**
     * Create all plugin tables from Schema
     */
    public function createAllTables($wpdb_prefix = '', $skip_tables = array())
    {
        global $wpdb;
        $errors = array();
        $wpdb->show_errors = true;
        $db_schema = Schema::getStructureSchemas();
        $schema_prefix = Schema::getSchemaTablePrefix();
        $wpdb_prefix = $wpdb_prefix ?: $wpdb->prefix;

        foreach ($db_schema as $table_key => $table_schema) {
            //skip table creation
            if ( in_array($table_key, $skip_tables) ) {
                continue;
            }

            //save SFW common table name
            if ( $table_key === 'sfw' ) {
                $current_options = get_option('cleantalk_data');
                $current_options['sfw_common_table_name']  = $wpdb_prefix . $schema_prefix . $table_key;
            }
            //save SFW personal table name for mutual key
            if ( $table_key === 'sfw_personal' ) {
                $current_options = get_option('cleantalk_data');
                $current_options['sfw_personal_table_name'] = $wpdb_prefix . $schema_prefix . $table_key;
            }

            if ( isset($current_options) ) {
                update_option('cleantalk_data', $current_options);
            }

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
            DbAnalyzer::logSchemaErrors($errors, __FUNCTION__);
        }
    }

    /**
     * Create Table by table name
     */
    public function createTable($table_name)
    {
        global $wpdb;
        $errors = array();
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
            DbAnalyzer::logSchemaErrors($errors, __FUNCTION__);
        }
    }
}
