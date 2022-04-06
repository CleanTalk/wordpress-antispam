<?php

namespace Cleantalk\ApbctWP\UpdatePlugin;

use Cleantalk\Common\Schema;

class DbAnalyzer
{
    /**
     * Contain DB Schema
     */
    private $dbSchema;

    /**
     * Contain DB Schema prefix
     */
    private $dbSchemaPrefix;

    /**
     * Tables exists
     */
    private $table_exists;

    /**
     * Tables not exists
     */
    private $table_not_exists;

    /**
     * WordPress Multisite is On
     */
    private $multisite;

    public function __construct()
    {
        $this->dbSchema = Schema::getStructureSchemas();
        $this->dbSchemaPrefix = Schema::getSchemaTablePrefix();
        $this->multisite = $this->checkingMultisite();
        $this->checkingCurrentScheme();
    }

    /**
     * Cheking WordPress Multisite
     */
    private function checkingMultisite()
    {
        return is_multisite();
    }

    /**
     * Checking the existence of tables and non-existent tables
     * Filled fields of class
     */
    private function checkingCurrentScheme()
    {
        global $wpdb;
        $tablesExists = array();
        $tablesNotExists = array();

        if ($this->dbSchema) {
            $schema_table_keys = array_keys($this->dbSchema);

            // WordPress Multisite
            if ($this->multisite) {
                $sites = get_sites();

                foreach ($sites as $site) {
                    foreach ($schema_table_keys as $table_key) {
                        switch_to_blog($site->blog_id);
                        $table_name = $wpdb->prefix . $this->dbSchemaPrefix . $table_key;
                        $result = $this->showTables($table_name);

                        if (is_null($result)) {
                            $tablesNotExists[] = $table_name;
                        } else {
                            $tablesExists[] = $table_name;
                        }
                    }
                }
                switch_to_blog(get_main_site_id());
            } else {
                foreach ($schema_table_keys as $table_key) {
                    $table_name = $wpdb->prefix . $this->dbSchemaPrefix . $table_key;
                    $result = $this->showTables($table_name);

                    if (is_null($result)) {
                        $tablesNotExists[] = $table_name;
                    } else {
                        $tablesExists[] = $table_name;
                    }
                }
            }
        }

        $this->table_exists = $tablesExists;
        $this->table_not_exists = $tablesNotExists;
    }

    /**
     * Show tables LIKE table name
     */
    private function showTables($table_name)
    {
        global $wpdb;
        $query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name));

        return $wpdb->get_var($query);
    }

    /**
     * Get exists tables
     */
    public function getExistsTables()
    {
        return $this->table_exists;
    }

    /**
     * Get non-exists tables
     */
    public function getNotExistsTables()
    {
        return $this->table_not_exists;
    }
}
