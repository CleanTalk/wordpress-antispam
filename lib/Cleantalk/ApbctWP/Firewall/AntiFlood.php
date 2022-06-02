<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\Common\Helper;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\Variables\Server;

/**
 * Class AntiFlood
 * @package Cleantalk\ApbctWP\Firewall
 *
 * @psalm-suppress PossiblyUnusedProperty
 */
class AntiFlood extends \Cleantalk\Common\Firewall\FirewallModule
{
    public $module_name = 'ANTIFLOOD';

    private $db__table__ac_logs;
    private $db__table__ac_ua_bl;

    private $api_key = '';
    private $view_limit = 20;
    private $apbct = array();
    private $store_interval = 60;
    private $chance_to_clean = 20;

    public $isExcluded = false;

    /**
     * @var string Content of the die page
     */
    private $sfw_die_page;

    /**
     * AntiCrawler constructor.
     *
     * @param $log_table
     * @param $ac_logs_table
     * @param array $params
     */
    public function __construct($log_table, $ac_logs_table, $params = array())
    {
        parent::__construct($log_table, $ac_logs_table, $params);

        $this->db__table__logs     = $log_table ?: null;
        $this->db__table__ac_logs  = $ac_logs_table ?: null;
        $this->db__table__ac_ua_bl = defined('APBCT_TBL_AC_UA_BL') ? APBCT_TBL_AC_UA_BL : null;

        foreach ($params as $param_name => $param) {
            $this->$param_name = isset($this->$param_name) ? $param : false;
        }

        $this->isExcluded = $this->checkExclusions();
    }

    /**
     * Use this method to execute main logic of the module.
     * @return array
     */
    public function check()
    {
        $results = array();

        $this->clearTable();

        $time = time() - $this->store_interval;

        foreach ($this->ip_array as $_ip_origin => $current_ip) {
            // UA check
            $ua_bl_results = $this->db->fetchAll(
                "SELECT * FROM " . $this->db__table__ac_ua_bl . " ORDER BY `ua_status` DESC;"
            );

            if ( ! empty($ua_bl_results)) {
                foreach ($ua_bl_results as $ua_bl_result) {
                    if (
                        ! empty($ua_bl_result['ua_template']) &&
                        preg_match("%" . str_replace('"', '', $ua_bl_result['ua_template']) . "%i", Server::get('HTTP_USER_AGENT'))
                    ) {
                        if ($ua_bl_result['ua_status'] == 1) {
                            // Whitelisted
                            $results[] = array(
                                'ip'          => $current_ip,
                                'is_personal' => false,
                                'status'      => 'PASS_ANTIFLOOD_UA',
                            );

                            return $results;
                        }
                    }
                }
            }

            // Passed
            if (Cookie::get('apbct_antiflood_passed') === md5($current_ip . $this->api_key)) {
                if ( ! headers_sent()) {
                    Cookie::set('apbct_antiflood_passed', '0', time() - 86400, '/', '', null, true);
                }

                // Do logging an one passed request
                $this->updateLog($current_ip, 'PASS_ANTIFLOOD');

                $results[] = array('ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_ANTIFLOOD',);

                return $results;
            }


            // @todo Rename ip column to sign. Use IP + UserAgent for it.

            $result = $this->db->fetchAll(
                "SELECT SUM(entries) as total_count"
                . ' FROM `' . $this->db__table__ac_logs . '`'
                . " WHERE ip = '$current_ip' AND interval_start > '$time' AND " . rand(1, 100000) . ";"
            );

            if ( ! empty($result) && isset($result[0]['total_count']) && $result[0]['total_count'] >= $this->view_limit) {
                $results[] = array('ip' => $current_ip, 'is_personal' => false, 'status' => 'DENY_ANTIFLOOD',);
            }
        }

        if ( ! empty($results)) {
            // Do block page
            return $results;
        } else {
            // Do logging entries
            add_action('template_redirect', array(& $this, 'updateAcLog'), 999);
        }

        return $results;
    }

    public function updateAcLog()
    {
        $interval_time = Helper::timeGetIntervalStart($this->store_interval);

        // @todo Rename ip column to sign. Use IP + UserAgent for it.

        foreach ($this->ip_array as $_ip_origin => $current_ip) {
            $id = md5($current_ip . $interval_time);
            $this->db->execute(
                "INSERT INTO " . $this->db__table__ac_logs . " SET
					id = '$id',
					ip = '$current_ip',
					entries = 1,
					interval_start = $interval_time
				ON DUPLICATE KEY UPDATE
					ip = ip,
					entries = entries + 1,
					interval_start = $interval_time;"
            );
        }
    }

    public function clearTable()
    {
        if (rand(0, 100) < $this->chance_to_clean) {
            $interval_start = \Cleantalk\ApbctWP\Helper::timeGetIntervalStart($this->store_interval);
            $this->db->execute(
                'DELETE
				FROM ' . $this->db__table__ac_logs . '
				WHERE interval_start < ' . $interval_start . ' 
				AND ua = "" 
				LIMIT 100000;'
            );
        }
    }

    /**
     * Add entry to SFW log.
     * Writes to database.
     *
     * @param string $ip
     * @param $status
     */
    public function updateLog($ip, $status)
    {
        $id   = md5($ip . $this->module_name);
        $time = time();

        $query = "INSERT INTO " . $this->db__table__logs . "
		SET
			id = '$id',
			ip = '$ip',
			status = '$status',
			all_entries = 1,
			blocked_entries = " . (strpos($status, 'DENY') !== false ? 1 : 0) . ",
			entries_timestamp = '" . $time . "',
			ua_name = %s
		ON DUPLICATE KEY
		UPDATE
			status = '$status',
			all_entries = all_entries + 1,
			blocked_entries = blocked_entries" . (strpos($status, 'DENY') !== false ? ' + 1' : '') . ",
			entries_timestamp = '" . $time . "',
			ua_name = %s";

        $this->db->prepare($query, array(Server::get('HTTP_USER_AGENT'), Server::get('HTTP_USER_AGENT')));
        $this->db->execute($this->db->getQuery());
    }

    public function diePage($result)
    {
        global $apbct;

        // File exists?
        if (file_exists(CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk/ApbctWP/Firewall/die_page_antiflood.html')) {
            $this->sfw_die_page = file_get_contents(
                CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk/ApbctWP/Firewall/die_page_antiflood.html'
            );

            $js_url = APBCT_URL_PATH . '/js/apbct-public--functions.min.js?' . APBCT_VERSION;

            $net_count = $apbct->stats['sfw']['entries'];

            // Translation
            $replaces = array(
                '{SFW_DIE_NOTICE_IP}'              => __(
                    'Anti-Flood is activated for your IP',
                    'cleantalk-spam-protect'
                ),
                '{SFW_DIE_MAKE_SURE_JS_ENABLED}'   => __(
                    'To continue working with the web site, please make sure that you have enabled JavaScript.',
                    'cleantalk-spam-protect'
                ),
                '{SFW_DIE_YOU_WILL_BE_REDIRECTED}' => sprintf(
                    __(
                        'You will be automatically redirected to the requested page after %d seconds.',
                        'cleantalk-spam-protect'
                    ),
                    30
                ),
                '{CLEANTALK_TITLE}'                => __('Anti-Spam by CleanTalk', 'cleantalk-spam-protect'),
                '{REMOTE_ADDRESS}'                 => $result['ip'],
                '{REQUEST_URI}'                    => Server::get('REQUEST_URI'),
                '{SERVICE_ID}'                     => $this->apbct->data['service_id'] . ', ' . $net_count,
                '{HOST}'                           => get_home_url() . ', ' . APBCT_VERSION,
                '{GENERATED}'                      => '<p>The page was generated at&nbsp;' . date('D, d M Y H:i:s') . "</p>",
                '{COOKIE_ANTIFLOOD_PASSED}'        => md5($this->api_key . $result['ip']),
                '{SCRIPT_URL}'                     => $js_url
            );

            foreach ($replaces as $place_holder => $replace) {
                $this->sfw_die_page = str_replace($place_holder, $replace, $this->sfw_die_page);
            }

            add_action('init', array($this, 'printDiePage'));
        }
    }

    public function printDiePage()
    {
        global $apbct;

        parent::diePage('');

        $localize_js = array(
            '_ajax_nonce'                          => wp_create_nonce('ct_secret_stuff'),
            '_rest_nonce'                          => wp_create_nonce('wp_rest'),
            '_ajax_url'                            => admin_url('admin-ajax.php', 'relative'),
            '_rest_url'                            => esc_url(get_rest_url()),
            'data__cookies_type'                   => $apbct->data['cookies_type'],
            'data__ajax_type'                      => $apbct->data['ajax_type'],
            'sfw__random_get'                      => $apbct->settings['sfw__random_get'] === '1' ||
                                                      ($apbct->settings['sfw__random_get'] === '-1' && apbct_is_cache_plugins_exists()),
            'cookiePrefix'                         => apbct__get_cookie_prefix(),
        );

        $js_jquery_url = includes_url() . 'js/jquery/jquery.min.js';

        $replaces = array(
            '{JQUERY_SCRIPT_URL}' => $js_jquery_url,
            '{LOCALIZE_SCRIPT}'   => 'var ctPublicFunctions = ' . json_encode($localize_js),
        );

        foreach ($replaces as $place_holder => $replace) {
            $this->sfw_die_page = str_replace($place_holder, $replace, $this->sfw_die_page);
        }

        http_response_code(403);

        // File exists?
        if (file_exists(CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page_sfw.html")) {
            die($this->sfw_die_page);
        }

        die("IP BLACKLISTED. Blocked by AntiFlood " . $this->apbct->stats['last_sfw_block']['ip']);
    }

    private function checkExclusions()
    {
        $allowed_roles = array('administrator', 'editor');
        $user          = apbct_wp_get_current_user();

        if ( ! $user) {
            return false;
        }

        foreach ($allowed_roles as $role) {
            if (in_array($role, (array)$user->roles)) {
                return true;
            }
        }

        return false;
    }
}
