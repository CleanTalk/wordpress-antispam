<?php

namespace Cleantalk\Common\Firewall;

use Cleantalk\ApbctWP\DB;

/**
 * The abstract class for any FireWall modules.
 * Compatible with any CMS.
 *
 * @version       1.0
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @since 2.49
 * @package Cleantalk\Common\Firewall
 *
 * @psalm-suppress PossiblyUnusedProperty
 */
abstract class FirewallModuleAbstract
{
    public $module_name;

    /**
     * @var DB
     */
    protected $db;
    /**
     * @var string
     */
    protected $db__table__logs;
    /**
     * @var string
     */
    protected $db__table__data;

    protected $service_id;

    protected $result_code = '';

    protected $ip_array = array();

    protected $test_ip;

    protected $passed_ip;

    protected $blocked_ip;

    /**
     * FireWall_module constructor.
     * Use this method to prepare any data for the module working.
     *
     * @param $log_table
     * @param $data_table
     * @param array $params
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    abstract public function __construct($log_table, $data_table, $params = array());

    /**
     * Use this method to execute main logic of the module.
     *
     * @return array  Array of the check results
     * @psalm-suppress PossiblyUnusedMethod
     */
    abstract public function check();
}
