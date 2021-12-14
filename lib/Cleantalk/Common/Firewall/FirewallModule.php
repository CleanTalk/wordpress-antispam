<?php

namespace Cleantalk\Common\Firewall;

/*
 * The abstract class for any FireWall modules.
 * Compatible with any CMS.
 *
 * @version       1.0
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @since 2.49
 */

class FirewallModule extends FirewallModuleAbstract
{
    /**
     * FireWall_module constructor.
     * Use this method to prepare any data for the module working.
     *
     * @param $log_table
     * @param $data_table
     * @param array $params
     *
     * @psalm-suppress PossiblyUnusedMethod
     * @psalm-suppress PossiblyUnusedParam
     */
    public function __construct($log_table, $data_table, $params = array())
    {
    }

    public function ipAppendAdditional(&$ips)
    {
    }

    /**
     * Use this method to execute main logic of the module.
     *
     * @return array  Array of the check results
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function check()
    {
        return array();
    }

    /**
     * @param $result
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function actionsForDenied($result)
    {
    }

    /**
     * @param $result
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function actionsForPassed($result)
    {
    }

    /**
     * @param mixed $db
     */
    public function setDb($db)
    {
        $this->db = $db;
    }

    /**
     * @param array $ip_array
     */
    public function setIpArray($ip_array)
    {
        $this->ip_array = $ip_array;
    }

    /**
     * @param $result
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function diePage($result)
    {
        // JCH Optimize caching preventing
        add_filter('jch_optimize_page_cache_set_caching', static function ($_is_cache_active) {
            return false;
        }, 999, 1);
        // Headers
        if (headers_sent() === false) {
            header('Expires: ' . date(DATE_RFC822, mktime(0, 0, 0, 1, 1, 1971)));
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
            header("HTTP/1.0 403 Forbidden");
        }

        if ( ! defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        if ( ! defined('DONOTCACHEDB')) {
            define('DONOTCACHEDB', true);
        }
        if ( ! defined('DONOTCDN')) {
            define('DONOTCDN', true);
        }
        if ( ! defined('DONOTCACHEOBJECT')) {
            define('DONOTCACHEOBJECT', true);
        }
        if (function_exists('wpfc_exclude_current_page')) {
            wpfc_exclude_current_page();
        }
    }
}
