<?php

namespace Cleantalk\Common;

/**
 * CleanTalk abstract Data Base driver.
 * Shows what should be inside.
 * Uses singleton pattern.
 *
 * @version 1.0
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see https://github.com/CleanTalk/php-antispam
 *
 * @psalm-suppress UnusedProperty
 * @psalm-suppress PossiblyUnusedProperty
 */
class DB
{
    private static $instance;

    /**
     * @var string Query string
     */
    private $query;

    /**
     * @var \wpdb result
     */
    private $db_result;

    /**
     * @var array Processed result
     */
    public $result = array();

    /**
     * @var string Database prefix
     */
    public $prefix = '';

    public function __construct()
    {
    }

    public function __clone()
    {
    }

    public function __wakeup()
    {
    }

    public static function getInstance()
    {
        if ( ! isset(static::$instance)) {
            static::$instance = new static();
            static::$instance->init();
        }

        return static::$instance;
    }

    /**
     * Alternative constructor.
     * Initialize Database object and write it to property.
     * Set tables prefix.
     */
    private function init()
    {
    }

    /**
     * Set $this->query string for next uses
     *
     * @param $query
     *
     * @return $this|void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setQuery($query)
    {
    }

    /**
     * Safely replace place holders
     *
     * @param string $query
     * @param array $vars
     *
     * @return $this
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function prepare($query, $vars = array())
    {
        return $this;
    }

    /**
     * Run any raw request
     *
     * @param $query
     *
     * @return bool|int|void Raw result
     * @psalm-suppress PossiblyUnusedParam
     */
    public function execute($query)
    {
    }

    /**
     * Fetchs first column from query.
     * May receive raw or prepared query.
     *
     * @param string $query
     * @param bool|string $response_type
     *
     * @return array|object|void|null
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function fetch($query = '', $response_type = false)
    {
    }

    /**
     * Fetchs all result from query.
     * May receive raw or prepared query.
     *
     * @param string $query
     * @param bool|string $response_type
     *
     * @return array|object|null|void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function fetchAll($query = '', $response_type = false)
    {
    }

    /**
     * Checks if the table exists
     *
     * @param $table_name
     *
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function isTableExists($table_name)
    {
        return (bool)$this->execute("SHOW TABLES LIKE '" . $table_name . "'");
    }
}
