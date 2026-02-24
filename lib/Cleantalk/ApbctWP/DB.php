<?php

namespace Cleantalk\ApbctWP;

/**
 * CleanTalk WordPress Data Base driver
 * Compatible only with WordPress.
 * Uses singleton pattern.
 *
 * @depends \Cleantalk\Common\DB
 *
 * @version 3.2
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see https://github.com/CleanTalk/wordpress-antispam
 */
class DB extends \Cleantalk\Common\DB
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
        if ( ! isset(static::$instance) ) {
            static::$instance = new static();
            static::$instance->init();
        }

        return static::$instance;
    }

    private function init()
    {
        global $apbct;
        $this->prefix = $apbct->db_prefix;
    }

    /**
     * Set $this->query string for next uses
     *
     * @param $query
     *
     * @return $this
     */
    public function setQuery($query)
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Set $this->query string for next uses
     *
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Safely replace place holders
     *
     * @param string $query
     * @param array $vars
     *
     * @return $this
     */
    public function prepare($query, $vars = array())
    {
        global $wpdb;

        $query = $query ?: $this->query;
        $vars  = $vars ?: array();
        array_unshift($vars, $query);

        $this->query = call_user_func_array(array($wpdb, 'prepare'), $vars);

        return $this;
    }

    /**
     * Run any raw request
     *
     * @param $query
     *
     * @return bool|int Raw result
     */
    public function execute($query)
    {
        global $wpdb;

        $this->db_result = $wpdb->query($query);

        return $this->db_result;
    }

    /**
     * Fetch first column from query.
     * May receive raw or prepared query.
     *
     * @param string $query
     * @param bool|string $response_type
     *
     * @return array|object|void|null
     */
    public function fetch($query = '', $response_type = false)
    {
        global $wpdb;

        $query         = $query ?: $this->query;
        $response_type = $response_type ?: ARRAY_A;

        $this->result = $wpdb->get_row($query, $response_type);

        return $this->result;
    }

    /**
     * Fetch all result from query.
     * May receive raw or prepared query.
     *
     * @param string $query
     * @param bool|string $response_type
     *
     * @return array|object|null
     */
    public function fetchAll($query = '', $response_type = false)
    {
        global $wpdb;

        $query         = $query ?: $this->query;
        $response_type = $response_type ?: ARRAY_A;

        $this->result = $wpdb->get_results($query, $response_type);

        return $this->result;
    }

    /**
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getLastError()
    {
        global $wpdb;

        return $wpdb->last_error;
    }
}
