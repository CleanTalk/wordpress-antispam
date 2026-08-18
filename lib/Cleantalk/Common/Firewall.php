<?php

namespace Cleantalk\Common;

use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\Common\Firewall\FirewallModule;
use Cleantalk\ApbctWP\Variables\Get;

/**
 * CleanTalk SpamFireWall base class.
 * Compatible with any CMS.
 *
 * @depends       \Cleantalk\Antispam\Helper class
 * @depends       \Cleantalk\Antispam\API class
 * @depends       \Cleantalk\Antispam\DB class
 *
 * @version       3.3
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see           https://github.com/CleanTalk/php-antispam
 *
 * @psalm-suppress PossiblyUnusedProperty
 */
class Firewall
{
    public $ip_array = array();

    // Database
    protected $db;

    //Debug
    public $debug;
    public $debug_data = '';

    /**
     * Statuses from the lowest priority to the highest. The position in this array IS the priority,
     * self::calculatePriority() does nothing but look the status up here.
     *
     * A result of the personal lists of the site owner carries the is_personal flag, which is not a
     * part of the status itself - the very same DENY_SFW comes both from the personal and from the
     * common list. Such results are listed here with the PERSONAL__ prefix, so that both variants
     * can take their own place in the order. A personal status absent from the list falls back to
     * the position of its common variant.
     *
     * Note the position of PASS_SFW__BY_WHITELIST: it is the global (non-personal) white list of the
     * cloud - good bots and the other common exclusions. It has to stay UNDER the DENY_* statuses,
     * so a black listed User-Agent outweighs a good bot IP.
     */
    private $statuses_priority = array(
        // Lowest
        'PASS_SFW',
        'PASS_SFW__BY_COOKIE',
        'PASS_ANTIFLOOD_UA',
        'PASS_ANTIFLOOD',
        'PASS_ANTICRAWLER_UA',
        'PASS_ANTICRAWLER',
        'PASS_SFW__BY_WHITELIST',
        'DENY_ANTIFLOOD_UA',
        'DENY_ANTIFLOOD',
        'DENY_ANTICRAWLER_UA',
        'DENY_ANTICRAWLER',
        'DENY_SFW',
        'PERSONAL__DENY_SFW',
        'PERSONAL__PASS_SFW__BY_WHITELIST',
        // Highest
    );

    private $fw_modules = array();

    private $module_names = array();

    /**
     * Creates Database driver instance.
     *
     * @param $db
     */
    public function __construct($db)
    {
        $this->db       = $db;
        $this->debug    = (bool)Get::get('debug');
        $this->ip_array = $this->ipGet();
    }

    /**
     * Getting arrays of IP (REMOTE_ADDR, X-Forwarded-For, X-Real-Ip, Cf_Connecting_Ip)
     *
     * @param string $ips_input type of IP you want to receive
     * @param bool $v4_only
     *
     * @return array
     */
    public function ipGet($ips_input = 'real', $v4_only = true)
    {
        $result = Helper::ipGet($ips_input, $v4_only);

        return ! empty($result) ? array('real' => $result) : array();
    }

    /**
     * Loads the FireWall module to the array.
     * For inner usage only.
     * Not returns anything, the result is private storage of the modules.
     *
     * @param FirewallModule $module
     */
    public function loadFwModule(FirewallModule $module)
    {
        if ( ! in_array($module, $this->fw_modules)) {
            $module->setDb($this->db);
            $module->ipAppendAdditional($this->ip_array);
            $this->fw_modules[$module->module_name] = $module;
            $module->setIpArray($this->ip_array);
        }
    }

    /**
     * Do main logic of the module.
     *
     * @return void   returns die page or set cookies
     */
    public function run()
    {
        $this->module_names = array_keys($this->fw_modules);

        $results = array();

        // Checking.
        // Every module has to be run before any decision is made. An early exit here would hide the
        // results of the modules below - e.g. a UA black list hit of the AntiCrawler would never be
        // taken into account if the SFW had found the IP in the white list or in a trusted network.
        // The whole picture is collected first, the decision is made by self::prioritize().
        foreach ($this->fw_modules as $module) {
            if (isset($module->isExcluded) && $module->isExcluded) {
                continue;
            }

            $module_results = $module->check();
            if ( ! empty($module_results)) {
                $results[$module->module_name] = $module_results;
            }
        }

        $this->setWhitelistedCookie($results);

        // Write Logs
        foreach ($this->fw_modules as $module) {
            if (array_key_exists($module->module_name, $results)) {
                foreach ($results[$module->module_name] as $result) {
                    if (
                        in_array(
                            $result['status'],
                            array(
                                'PASS_SFW__BY_WHITELIST',
                                'PASS_SFW',
                                'PASS_ANTIFLOOD',
                                'PASS_ANTICRAWLER',
                                'PASS_ANTICRAWLER_UA',
                                'PASS_ANTIFLOOD_UA'
                            )
                        )
                    ) {
                        continue;
                    }
                    $module->updateLog(
                        $result['ip'],
                        $result['status'],
                        isset($result['network']) ? $result['network'] : null,
                        isset($result['is_personal']) ? $result['is_personal'] : 'NULL'
                    );
                }
            }
        }

        // Get the primary result
        $result = $this->prioritize($results);

        // Do finish action - die or set cookies
        foreach ($this->module_names as $module_name) {
            $status = TT::getArrayValueAsString($result, 'status');
            if (strpos($status, $module_name)) {
                // Blocked
                if (strpos($status, 'DENY') !== false) {
                    $this->fw_modules[$module_name]->actionsForDenied($result);
                    $this->fw_modules[$module_name]->diePage($result);
                // Allowed
                } else {
                    if ( Get::get('sfw_test_ip') ) {
                        $this->fw_modules[$module_name]->diePage($result);
                    }
                    $this->fw_modules[$module_name]->actionsForPassed($result);
                }
            }
        }
    }

    /**
     * Sets priorities for firewall results.
     * It generates one main result from multi-level results array.
     *
     * @param array $results
     *
     * @return array Single element array of result
     */
    private function prioritize($results)
    {
        $current_fw_result_priority = 0;
        $result                     = array('status' => 'PASS', 'passed_ip' => '');

        if (is_array($results)) {
            foreach ($this->fw_modules as $module) {
                if (array_key_exists($module->module_name, $results)) {
                    foreach ($results[$module->module_name] as $fw_result) {
                        $priority = $this->calculatePriority($fw_result);
                        if ($priority >= $current_fw_result_priority) {
                            $current_fw_result_priority = $priority;
                            $result['status']           = TT::getArrayValueAsString($fw_result, 'status');
                            $result['passed_ip']        = isset($fw_result['ip'])
                                ? TT::getArrayValueAsString($fw_result, 'ip')
                                : TT::getArrayValueAsString($fw_result, 'passed_ip');
                            $result['blocked_ip']       = isset($fw_result['ip'])
                                ? TT::getArrayValueAsString($fw_result, 'ip')
                                : TT::getArrayValueAsString($fw_result, 'blocked_ip');
                            $result['pattern']          = TT::getArrayValueAsString($fw_result, 'pattern');
                        }
                    }
                }
            }
        }

        $result['ip']     = strpos($result['status'], 'PASS') !== false ? $result['passed_ip'] : TT::getArrayValueAsString($result, 'blocked_ip');
        $result['passed'] = strpos($result['status'], 'PASS') !== false;

        return $result;
    }

    /**
     * Returns the position of a single firewall result in self::$statuses_priority.
     *
     * A result of a personal list is looked up by the PERSONAL__ prefixed status first, so it takes
     * its own place in the order. If there is no such entry, the common variant is used.
     *
     * @param array $fw_result
     *
     * @return int
     */
    private function calculatePriority($fw_result)
    {
        $status = TT::getArrayValueAsString($fw_result, 'status');

        if (isset($fw_result['is_personal']) && $fw_result['is_personal']) {
            $personal_priority = array_search('PERSONAL__' . $status, $this->statuses_priority);
            if ($personal_priority !== false) {
                return $personal_priority;
            }
        }

        $priority = array_search($status, $this->statuses_priority);

        return $priority === false ? 0 : $priority;
    }

    /**
     * Set the white list cookie if any of the results is whitelisted or belongs to a trusted network.
     *
     * @param array $results
     *
     * @return void
     */
    private function setWhitelistedCookie($results)
    {
        global $apbct;

        foreach ($this->fw_modules as $module) {
            if (array_key_exists($module->module_name, $results)) {
                foreach ($results[$module->module_name] as $fw_result) {
                    if (
                        strpos($fw_result['status'], 'PASS_BY_TRUSTED_NETWORK') !== false ||
                        strpos($fw_result['status'], 'PASS_BY_WHITELIST') !== false ||
                        strpos($fw_result['status'], 'PASS_SFW__BY_WHITELIST') !== false
                    ) {
                        if ( ! headers_sent()) {
                            $cookie_val = md5($fw_result['ip'] . $apbct->api_key);
                            Cookie::set('ct_sfw_ip_wl', $cookie_val, time() + 86400 * 30, '/', '', null, true, 'Lax');
                        }

                        return;
                    }
                }
            }
        }
    }
}
