<?php

namespace Cleantalk\ApbctWP\UpdatePlugin;

use Cleantalk\Common\Schema;
use Cleantalk\Common\TT;

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
                if (is_array($sites)) {
                    foreach ($sites as $site) {
                        if ($site instanceof \WP_Site) {
                            foreach ($schema_table_keys as $table_key) {
                                switch_to_blog(TT::toInt($site->blog_id));
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
                    switch_to_blog(get_main_site_id());
                }
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

    /**
     * Write $message to the plugin's debug option
     *
     * @param string[] $messages Array of strings to log
     * @param null|string $function Caller function
     *
     * @return void
     */
    public static function logSchemaErrors($messages = array(), $function = 'N/A')
    {
        global $apbct;

        $current_log = isset($apbct->data['sql_schema_errors'])
            ? $apbct->data['sql_schema_errors']
            : array();
        $current_log = $current_log instanceof \ArrayObject
            ? $current_log->getArrayCopy()
            : $current_log;
        $current_log = is_array($current_log)
            ? $current_log
            : array();

        if ( is_array($messages) ) {
            $messages = print_r($messages, true);
        }

        // Add new message to the log
        $new_entry = array(
            'message' => $messages,
            'datetime' => TT::toString(date('Y-m-d H:i:s', time())),
            'function' => TT::toString($function),
        );
        $current_log[] = $new_entry;

        // Check if the log count exceeds 10
        if (count($current_log) > 10) {
            // Remove the oldest entries
            $current_log = array_slice($current_log, -10);
        }

        // Save the updated log
        $apbct->data['sql_schema_errors'] = $current_log;
        $apbct->save('sql_schema_errors', true, false);
    }
}
