<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\ApbctWP\API;
use Cleantalk\ApbctWP\DB;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;
use AllowDynamicProperties;

#[AllowDynamicProperties]
class SFW extends \Cleantalk\Common\Firewall\FirewallModule
{
    /**
     * @var bool
     */
    private $test;

    /**
     * @var array
     */
    private $test_entry;

    // Additional params
    private $sfw_counter = false;
    private $api_key = false;
    private $blocked_ips = array();
    private $data__cookies_type = false;
    private $cookie_domain = false;

    public $module_name = 'SFW';

    /**
     * @var string
     */
    private $db__table__data_personal;

    private $real_ip;
    private $debug;
    private $debug_data = '';

    /**
     * @var string Content of the die page
     */
    private $sfw_die_page;

    /**
     * @var string
     */
    private $server__http_user_agent;
    /**
     * @var string
     */
    private $server__http_host;
    /**
     * @var string
     */
    private $server__request_uri;
    /**
     * @var string
     */
    private $server__remote_addr;
    /**
     * @var string
     */
    private $get__sfw_test_ip;

    /**
     * FireWall_module constructor.
     * Use this method to prepare any data for the module working.
     *
     * @param string $log_table
     * @param string $data_table_personal
     * @param array $params
     */
    public function __construct($log_table, $data_table_personal, $params = array())
    {
        parent::__construct($log_table, $data_table_personal, $params);

        // init server vars
        $this->server__http_user_agent = TT::toString(Server::get('HTTP_USER_AGENT'));
        $this->server__http_host       = TT::toString(Server::get('HTTP_HOST'));
        $this->server__request_uri  = TT::toString(Server::get('REQUEST_URI'));
        $this->get__sfw_test_ip     = TT::toString(Get::get('sfw_test_ip'));

        $this->db__table__data = TT::getArrayValueAsString($params, 'sfw_common_table_name') ?: '';
        $this->db__table__data_personal = $data_table_personal ?: '';
        $this->db__table__logs = $log_table ?: '';

        foreach ($params as $param_name => $param) {
            $this->$param_name = isset($this->$param_name) ? $param : false;
        }

        $this->debug = (bool)Get::get('debug');
    }

    /**
     * @param $ips
     */
    public function ipAppendAdditional(&$ips)
    {
        $this->real_ip = isset($ips['real']) ? $ips['real'] : null;

        if ($this->get__sfw_test_ip) {
            if (Helper::ipValidate($this->get__sfw_test_ip) !== false) {
                $ips['sfw_test'] = $this->get__sfw_test_ip;
                $this->test_ip   = $this->get__sfw_test_ip;
                $this->test      = true;
            }
        }
    }

    /**
     * Use this method to execute main logic of the module.
     *
     * @return array  Array of the check results
     */
    public function check()
    {
        global $apbct;

        $results = array();
        $status  = 0;

        if (
            empty($this->db__table__data) ||
            empty($this->db__table__data_personal)
        ) {
            return $results;
        }

        if ( $this->test ) {
            unset($_COOKIE['ct_sfw_pass_key']);
            Cookie::set('ct_sfw_pass_key', '0');
        }

        // Skip by cookie
        foreach ($this->ip_array as $current_ip) {
            if (
                TT::toString(Cookie::get('ct_sfw_pass_key'))
                && strpos(TT::toString(Cookie::get('ct_sfw_pass_key')), md5($current_ip . $this->api_key)) === 0
            ) {
                if (Cookie::get('ct_sfw_passed')) {
                    if ( ! headers_sent()) {
                        Cookie::set(
                            'ct_sfw_passed',
                            '0',
                            time() + 86400 * 3,
                            '/',
                            '',
                            null,
                            true
                        );
                    } else {
                        $results[] = array(
                            'ip'          => $current_ip,
                            'is_personal' => false,
                            'status'      => 'PASS_SFW__BY_COOKIE'
                        );
                    }

                    // Do logging an one passed request
                    $this->updateLog($current_ip, 'PASS_SFW');

                    if ($this->sfw_counter) {
                        $apbct->data['admin_bar__sfw_counter']['all']++;
                        $apbct->saveData();
                    }
                }

                if (strlen(TT::toString(Cookie::get('ct_sfw_pass_key'))) > 32) {
                    $status = substr(TT::toString(Cookie::get('ct_sfw_pass_key')), -1);
                }

                if ($status) {
                    $results[] = array(
                        'ip'          => $current_ip,
                        'is_personal' => false,
                        'status'      => 'PASS_SFW__BY_WHITELIST'
                    );
                }

                return $results;
            }
        }

        // Common check
        foreach ($this->ip_array as $origin => $current_ip) {
            $current_ip_v4 = sprintf("%u", ip2long($current_ip));
            for ($needles = array(), $m = 6; $m <= 32; $m++) {
                $mask      = str_repeat('1', $m);
                $mask      = str_pad($mask, 32, '0');
                $needles[] = sprintf("%u", bindec($mask & base_convert($current_ip_v4, 10, 2)));
            }
            $needles = array_unique($needles);

            $query =  "(SELECT
				0 as is_personal, network, mask, status
				FROM " . $this->db__table__data . "
				WHERE network IN (" . implode(',', $needles) . ")
				AND	network = " . $current_ip_v4 . " & mask 
				AND " . rand(1, 100000) . "  
				ORDER BY status DESC, status)";

            $query .= " UNION ";

            $query .=  "(SELECT
				1 as is_personal, network, mask, status
				FROM " . $this->db__table__data_personal . "
				WHERE network IN (" . implode(',', $needles) . ")
				AND	network = " . $current_ip_v4 . " & mask 
				AND " . rand(1, 100000) . "  
				ORDER BY status DESC, status)";

            $db_results = $this->db->fetchAll($query);

            $test_entry = array(
                'status' => 99
            );

            if ( ! empty($db_results)) {
                foreach ($db_results as $db_result) {
                    switch ( $db_result['status'] ) {
                        case 1:
                            $text_status = 'PASS_SFW__BY_WHITELIST';
                            break;
                        case 0:
                            $this->blocked_ips[] = Helper::ipLong2ip($db_result['network']);
                            $text_status = 'DENY_SFW';
                            break;
                        default:
                            $text_status = 'PASS_SFW';
                            break;
                    }

                    $result_entry = array(
                        'ip'          => $current_ip,
                        'network'     => Helper::ipLong2ip($db_result['network'])
                                         . '/'
                                         . Helper::ipMaskLongToNumber((int)$db_result['mask']),
                        'is_personal' => $db_result['is_personal'],
                        'status'      => $text_status
                    );

                    $test_entry = $result_entry;
                    $test_entry['status'] = (int)($db_result['status']);

                    if ($text_status === 'PASS_SFW__BY_WHITELIST') {
                        break;
                    }
                }
            } else {
                $result_entry = array(
                    'ip'          => $current_ip,
                    'is_personal' => null,
                    'status'      => 'PASS_SFW',
                );
            }

            if (!empty($result_entry)) {
                $results[] = $result_entry;
            }

            if ( $this->test && $origin === 'sfw_test' ) {
                $this->test_entry = $test_entry;
            }
        }

        return $results;
    }

    /**
     * Add entry to SFW log.
     * Writes to database.
     *
     * @param string $ip
     * @param $status
     * @param string $network
     * @param string $source
     */
    public function updateLog($ip, $status, $network = 'NULL', $source = 'NULL')
    {
        $id   = md5($ip . $this->module_name);
        $time = time();
        $short_url_to_log = substr($this->server__http_host . $this->server__request_uri, 0, 100);

        $this->db->prepare(
            "INSERT INTO " . $this->db__table__logs . "
            SET
                id = '$id',
                ip = '$ip',
                status = '$status',
                all_entries = 1,
                blocked_entries = " . (strpos($status, 'DENY') !== false ? 1 : 0) . ",
                entries_timestamp = '" . $time . "',
                ua_name = %s,
                source = $source,
                network = %s,
                first_url = %s,
                last_url = %s
            ON DUPLICATE KEY
            UPDATE
                status = '$status',
                source = $source,
                all_entries = all_entries + 1,
                blocked_entries = blocked_entries" . (strpos($status, 'DENY') !== false ? ' + 1' : '') . ",
                entries_timestamp = '" . $time . "',
                ua_name = %s,
                network = %s,
                last_url = %s",
            array(
                $this->server__http_user_agent,
                $network,
                $short_url_to_log,
                $short_url_to_log,
                $this->server__http_user_agent,
                $network,
                $short_url_to_log,
            )
        );
        $this->db->execute($this->db->getQuery());
    }

    public function actionsForDenied($result)
    {
        global $apbct;
        if ($this->sfw_counter) {
            $apbct->data['admin_bar__sfw_counter']['blocked']++;
            $apbct->saveData();
        }
    }

    public function actionsForPassed($result)
    {
        if ($this->data__cookies_type === 'native' && ! headers_sent()) {
            $status     = $result['status'] === 'PASS_SFW__BY_WHITELIST' ? '1' : '0';
            $cookie_val = md5($result['ip'] . $this->api_key) . $status;
            Cookie::setNativeCookie(
                'ct_sfw_pass_key',
                $cookie_val,
                time() + 86400 * 30,
                '/'
            );
        }
    }

    /**
     * Shows DIE page.
     * Stops script executing.
     *
     * @param $result
     */
    public function diePage($result)
    {
        global $apbct;

        // Statistics
        if ( ! empty($this->blocked_ips)) {
            reset($this->blocked_ips);
            $apbct->stats['last_sfw_block']['time'] = time();
            $apbct->stats['last_sfw_block']['ip']   = $result['ip'];
            $apbct->save('stats');
        }

        // File exists?
        if (file_exists(CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page_sfw.html")) {
            $this->sfw_die_page = file_get_contents(
                CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page_sfw.html"
            );

            $js_url = APBCT_URL_PATH . '/js/apbct-public-bundle.min.js?' . APBCT_VERSION;

            $net_count = $apbct->stats['sfw']['entries'];

            $status     = $result['status'] === 'PASS_SFW__BY_WHITELIST' ? '1' : '0';
            $cookie_val = md5($result['ip'] . $this->api_key) . $status;

            $block_message = sprintf(
                esc_html__('SpamFireWall is checking your browser and IP %s for spam bots', 'cleantalk-spam-protect'),
                '<a href="https://cleantalk.org/blacklists/' . $result['ip'] . '" target="_blank">' . $result['ip'] . '</a>'
            );

            $request_uri = $this->server__request_uri;
            if ( $this->test ) {
                // Remove "sfw_test_ip" get parameter from the uri
                $request_uri = preg_replace('%sfw_test_ip=\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}&?%', '', $request_uri);
            }

            // Custom Logo
            $custom_logo_img = '';
            $custom_logo_id = isset($apbct->settings['cleantalk_custom_logo']) ? $apbct->settings['cleantalk_custom_logo'] : false;

            if ($custom_logo_id && ($image_attributes = wp_get_attachment_image_src($custom_logo_id, array(150, 150)))) {
                $custom_logo_img = '<img src="' . esc_url(TT::getArrayValueAsString($image_attributes, 0)) . '" width="150" alt="" />';
            }

            // Translation
            $replaces = array(
                '{SFW_DIE_NOTICE_IP}'              => $block_message,
                '{SFW_DIE_MAKE_SURE_JS_ENABLED}'   => __(
                    'To continue working with the web site, please make sure that you have enabled JavaScript.',
                    'cleantalk-spam-protect'
                ),
                '{SFW_DIE_CLICK_TO_PASS}'          => __(
                    'Please click the link below to pass the protection,',
                    'cleantalk-spam-protect'
                ),
                '{SFW_DIE_YOU_WILL_BE_REDIRECTED}' => sprintf(
                    __(
                        'Or you will be automatically redirected to the requested page after %d seconds.',
                        'cleantalk-spam-protect'
                    ),
                    3
                ),
                '{CLEANTALK_TITLE}'                => ($this->test ? __(
                    'This is the testing page for SpamFireWall',
                    'cleantalk-spam-protect'
                ) : ''),
                '{CLEANTALK_URL}'                  => $apbct->data['wl_url'],
                '{REMOTE_ADDRESS}'                 => $result['ip'],
                '{SERVICE_ID}'                     => $apbct->data['service_id'] . ', ' . $net_count,
                '{HOST}'                           => get_home_url() . ', ' . APBCT_VERSION,
                '{GENERATED}'                      => '<p>The page was generated at&nbsp;' . date('D, d M Y H:i:s') . '</p>',
                '{REQUEST_URI}'                    => $request_uri,

                // Cookie
                '{COOKIE_PREFIX}'                  => '',
                '{COOKIE_DOMAIN}'                  => $this->cookie_domain,
                '{COOKIE_SFW}'                     => $cookie_val,
                '{COOKIE_ANTICRAWLER}'             => hash('sha256', $apbct->api_key . $apbct->data['salt']),

                // Test
                '{TEST_TITLE}'                     => '',
                '{REAL_IP__HEADER}'                => '',
                '{TEST_IP__HEADER}'                => '',
                '{TEST_IP}'                        => '',
                '{REAL_IP}'                        => '',
                '{SCRIPT_URL}'                     => $js_url,

                // Message about IP status
                '{MESSAGE_IP_STATUS}'              => '',

                // Custom Logo
                '{CUSTOM_LOGO}'                    => $custom_logo_img
            );

            /**
             * Message about IP status
             */
            if ( $this->test ) {
                $is_personal = isset($this->test_entry['is_personal']) && (int)$this->test_entry['is_personal'] === 1;
                $test_status = isset($this->test_entry['status']) ? (int)$this->test_entry['status'] : null;
                $common_text_passed = __('This IP is passed', 'cleantalk-spam-protect');
                $common_text_blocked = __('This IP is blocked', 'cleantalk-spam-protect');
                $global_text = __('(in global lists)', 'cleantalk-spam-protect');
                $personal_text = __('(in personal lists)', 'cleantalk-spam-protect');
                $lists_text = $is_personal ? $personal_text : $global_text;
                switch ( $test_status ) {
                    case 1:
                        $message_ip_status = $common_text_passed . ' ' . $lists_text;
                        $message_ip_status_color = 'green';
                        break;
                    case 0:
                        $message_ip_status = $common_text_blocked . ' ' . $lists_text;
                        $message_ip_status_color = 'red';
                        break;
                    default:
                        $message_ip_status = __('This IP is passed (not in any lists)', 'cleantalk-spam-protect');
                        $message_ip_status_color = 'green';
                        break;
                }

                $replaces['{MESSAGE_IP_STATUS}'] = "<h3 style='color:$message_ip_status_color;'>$message_ip_status</h3>";
            }

            // Test
            if ($this->test) {
                $replaces['{TEST_TITLE}']      = __(
                    'This is the testing page for SpamFireWall',
                    'cleantalk-spam-protect'
                );
                $replaces['{REAL_IP__HEADER}'] = 'Real IP:';
                $replaces['{TEST_IP__HEADER}'] = 'Test IP:';
                $replaces['{TEST_IP}']         = $this->test_ip;
                $replaces['{REAL_IP}']         = $this->real_ip;
            }

            // Debug
            if ($this->debug) {
                $debug = '<h1>Headers</h1>'
                         . var_export(apache_request_headers(), true)
                         . '<h1>REMOTE_ADDR</h1>'
                         . $this->server__remote_addr
                         . '<h1>SERVER_ADDR</h1>'
                         . $this->server__remote_addr
                         . '<h1>IP_ARRAY</h1>'
                         . var_export($this->ip_array, true)
                         . '<h1>ADDITIONAL</h1>'
                         . var_export($this->debug_data, true);
            }
            $replaces['{DEBUG}'] = isset($debug) ? $debug : '';

            foreach ($replaces as $place_holder => $replace) {
                $this->sfw_die_page = str_replace($place_holder, $replace, $this->sfw_die_page);
            }
        }

        add_action('init', array($this, 'printDiePage'));
    }

    public function printDiePage()
    {
        global $apbct;

        parent::diePage('');

        $localize_js = array(
            '_ajax_nonce'                          => $apbct->ajax_service->getPublicNonce(),
            '_rest_nonce'                          => wp_create_nonce('wp_rest'),
            '_ajax_url'                            => admin_url('admin-ajax.php', 'relative'),
            '_rest_url'                            => esc_url(get_rest_url()),
            'data__cookies_type'                   => $apbct->data['cookies_type'],
            'data__ajax_type'                      => $apbct->data['ajax_type'],
            'sfw__random_get'                      => $apbct->settings['sfw__random_get'] === '1' ||
                                                      ($apbct->settings['sfw__random_get'] === '-1' && apbct_is_cache_plugins_exists()),
            'cookiePrefix'                         => apbct__get_cookie_prefix(),
        );

        $localize_js_public = array(
            'pixel__setting'                => $apbct->settings['data__pixel'],
            'pixel__enabled'                => $apbct->settings['data__pixel'] === '2' ||
                                               ($apbct->settings['data__pixel'] === '3' && apbct_is_cache_plugins_exists()),
            'pixel__url'                    => $apbct->pixel_url,
            'data__email_check_before_post' => $apbct->settings['data__email_check_before_post'],
            'data__cookies_type'            => $apbct->data['cookies_type'],
            'data__visible_fields_required' => ! apbct_is_user_logged_in() || $apbct->settings['data__protect_logged_in'] == 1,
        );

        $replaces = array(
            '{LOCALIZE_SCRIPT}'   => 'var ctPublicFunctions = ' . json_encode($localize_js) . ';' .
                                     'var ctPublic = ' . json_encode($localize_js_public) . ';',
        );

        foreach ($replaces as $place_holder => $replace) {
            $this->sfw_die_page = str_replace($place_holder, $replace, $this->sfw_die_page);
        }

        http_response_code(403);

        // File exists?
        if (file_exists(CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page_sfw.html")) {
            die($this->sfw_die_page);
        }

        die("IP BLACKLISTED. Blocked by SFW " . $apbct->stats['last_sfw_block']['ip']);
    }

    /**
     * Sends and wipe SFW log
     *
     * @param $db
     * @param $log_table
     * @param string $ct_key Access key
     * @param bool $_use_delete_command Determs whether use DELETE or TRUNCATE to delete the logs table data
     *
     * @return array|bool array('error' => STRING)
     */
    public static function sendLog($db, $log_table, $ct_key)
    {
        //Getting logs
        $query = "SELECT * FROM $log_table ORDER BY entries_timestamp DESC LIMIT 0," . APBCT_SFW_SEND_LOGS_LIMIT . ";";
        $db->fetchAll($query);

        if (count($db->result)) {
            $logs = $db->result;

            //Compile logs
            $ids_to_delete = array();
            $data          = array();
            foreach ($logs as $_key => &$value) {
                $ids_to_delete[] = $value['id'];

                // Converting statuses to API format
                $value['status'] = $value['status'] === 'DENY_ANTICRAWLER' ? 'BOT_PROTECTION' : $value['status'];
                $value['status'] = $value['status'] === 'PASS_ANTICRAWLER' ? 'BOT_PROTECTION' : $value['status'];
                $value['status'] = $value['status'] === 'DENY_ANTICRAWLER_UA' ? 'BOT_PROTECTION' : $value['status'];
                $value['status'] = $value['status'] === 'PASS_ANTICRAWLER_UA' ? 'BOT_PROTECTION' : $value['status'];

                $value['status'] = $value['status'] === 'DENY_ANTIFLOOD' ? 'FLOOD_PROTECTION' : $value['status'];
                $value['status'] = $value['status'] === 'PASS_ANTIFLOOD' ? 'FLOOD_PROTECTION' : $value['status'];
                $value['status'] = $value['status'] === 'DENY_ANTIFLOOD_UA' ? 'FLOOD_PROTECTION' : $value['status'];
                $value['status'] = $value['status'] === 'PASS_ANTIFLOOD_UA' ? 'FLOOD_PROTECTION' : $value['status'];

                $value['status'] = $value['status'] === 'PASS_SFW__BY_COOKIE' ? 'DB_MATCH' : $value['status'];
                $value['status'] = $value['status'] === 'PASS_SFW' ? 'DB_MATCH' : $value['status'];
                $value['status'] = $value['status'] === 'DENY_SFW' ? 'DB_MATCH' : $value['status'];

                $value['status'] = $value['source'] ? 'PERSONAL_LIST_MATCH' : $value['status'];

                $additional = array();
                if ($value['network']) {
                    $additional['nd'] = $value['network'];
                }
                if ($value['first_url']) {
                    $additional['fu'] = $value['first_url'];
                }
                if ($value['last_url']) {
                    $additional['lu'] = $value['last_url'];
                }
                $additional = $additional ?: 'EMPTY_ASSOCIATIVE_ARRAY';

                $data[] = array(
                    trim($value['ip']),
                    // IP
                    $value['blocked_entries'],
                    // Count showing of block pages
                    $value['all_entries'] - $value['blocked_entries'],
                    // Count passed requests after block pages
                    $value['entries_timestamp'],
                    // Last timestamp
                    $value['status'],
                    // Status
                    $value['ua_name'],
                    // User-Agent name
                    $value['ua_id'],
                    // User-Agent ID
                    $additional
                    // Network, first URL, last URL
                );
            }
            unset($value);

            //Sending the request
            $result = API::methodSfwLogs($ct_key, $data);
            //Checking answer and deleting all lines from the table
            if (empty($result['error'])) {
                if (isset($result['rows']) && TT::toInt($result['rows']) === count($data)) {
                    $db->execute("BEGIN;");
                    $db->execute("DELETE FROM $log_table WHERE id IN ( '" . implode('\',\'', $ids_to_delete) . "' );");
                    $db->execute("COMMIT;");

                    return $result;
                }

                return array('error' => 'SENT_AND_RECEIVED_LOGS_COUNT_DOESNT_MACH');
            } else {
                return $result;
            }
        } else {
            return array('rows' => 0);
        }
    }

    public static function directUpdateProcessFiles()
    {
        global $apbct;

        // get list of files in the upd folder
        $files = glob($apbct->fw_stats['updating_folder'] . '/*csv.gz');
        $files = array_filter($files, static function ($element) {
            return strpos($element, 'list') !== false;
        });

        $success = true;
        $result = array();

        if ( count($files) ) {
            foreach ($files as $concrete_file) {
                //get direction on how the file should be processed (common/personal)
                if (
                    // we should have a personal list id (hash) to make sure the file belongs to private lists
                    !empty($apbct->fw_stats['personal_lists_url_id'])
                    && strpos($concrete_file, $apbct->fw_stats['personal_lists_url_id']) !== false
                ) {
                    $direction = 'personal';
                } elseif (
                    // we should have a common list id (hash) to make sure the file belongs to common lists
                    !empty($apbct->fw_stats['common_lists_url_id'])
                    && strpos($concrete_file, $apbct->fw_stats['common_lists_url_id']) !== false ) {
                    $direction = 'common';
                } else {
                    // no id found in fw_stats or file namse does not contain any of them
                    $result['error_on_direct_update'] = 'SFW_DIRECTION_FAILED';
                    $success = false;
                    break;
                }

                // do proceed file with networks itself
                if ( strpos($concrete_file, 'bl_list') !== false ) {
                    $counter = SFWUpdateHelper::processFile($concrete_file, $direction);
                    if ( empty($counter['error']) ) {
                        $result['apbct_sfw_update__process_file'] = is_scalar($counter) ? (int) $counter : 0;
                    } else {
                        $result['apbct_sfw_update__process_file'] = $counter['error'];
                        $success = false;
                        break;
                    }
                }

                // do proceed ua file
                if ( strpos($concrete_file, 'ua_list') !== false ) {
                    $counter = SFWUpdateHelper::processUA($concrete_file);
                    if ( empty($counter['error']) ) {
                        $result['apbct_sfw_update__process_ua'] = is_scalar($counter) ? (int) $counter : 0;
                    } else {
                        $result['apbct_sfw_update__process_ua'] = $counter['error'];
                        $success = false;
                        break;
                    }
                }

                // do proceed checking file
                if ( strpos($concrete_file, 'ck_list') !== false ) {
                    $counter = SFWUpdateHelper::processCK($concrete_file, $direction);
                    if ( empty($counter['error']) ) {
                        $result['apbct_sfw_update__process_ck'] = is_scalar($counter) ? (int) $counter : 0;
                    } else {
                        $result['apbct_sfw_update__process_ck'] = $counter['error'];
                        $success = false;
                        break;
                    }
                }
            }
        }

        return $success ? 'OK' : array('error' => $result);
    }

    /**
     * Updates SFW local base
     *
     * @param DB $db instance of DB object
     * @param string $db__table__data name of table to write data
     * @param string $file_url File URL with SFW data.
     *
     * @return array|int array('error' => STRING)
     */
    public static function updateWriteToDb($db, $db__table__data, $file_url = '')
    {
        if ( ! $db->isTableExists($db__table__data) ) {
            return array('error' => 'Temp table not exist');
        }

        $file_content = file_get_contents($file_url);

        if (function_exists('gzdecode')) {
            $unzipped_content = @gzdecode($file_content);

            if ($unzipped_content !== false) {
                $data = Helper::bufferParseCsv($unzipped_content);

                if (empty($data['errors'])) {
                    reset($data);

                    for ($count_result = 0; current($data) !== false;) {
                        $query = "INSERT INTO " . $db__table__data . " (network, mask, status) VALUES ";

                        for (
                            $i = 0, $values = array();
                            APBCT_WRITE_LIMIT !== $i && current($data) !== false;
                            $i++, $count_result++, next($data)
                        ) {
                            $entry = current($data);

                            if (empty($entry) || empty($entry[0]) || empty($entry[1])) {
                                continue;
                            }

                            // Cast result to int
                            $ip     = preg_replace('/[^\d]*/', '', $entry[0]);
                            $mask   = preg_replace('/[^\d]*/', '', $entry[1]);
                            $status = isset($entry[2]) ? $entry[2] : 0;

                            $values[] = "($ip, $mask, $status)";
                        }

                        if ( ! empty($values)) {
                            $query .= implode(',', $values) . ';';
                            if ( ! $db->execute($query) ) {
                                return array(
                                    'error' => 'WRITE ERROR: FAILED TO INSERT DATA: ' . $db__table__data
                                        . ' DB Error: ' . $db->getLastError()
                                );
                            }
                            if (file_exists($file_url)) {
                                unlink($file_url);
                            }
                        }
                    }

                    return $count_result;
                } else {
                    return $data;
                }
            } else {
                return array('error' => 'Can not unpack datafile');
            }
        } else {
            return array('error' => 'Function gzdecode not exists. Please update your PHP at least to version 5.4 ');
        }
    }

    public static function updateWriteToDbExclusions($db, $db__table__data, $exclusions = array())
    {
        global $wpdb, $apbct;

        $query = 'INSERT INTO `' . $db__table__data . '` (network, mask, status) VALUES ';

        //Exclusion for servers IP (SERVER_ADDR)
        if (Server::get('HTTP_HOST')) {
            // Do not add exceptions for local hosts
            if (defined('APBCT_IS_LOCALHOST') && !APBCT_IS_LOCALHOST) {
                if ( $current_host_ip = Helper::dnsResolve(Server::get('HTTP_HOST')) ) {
                    $exclusions[] = $current_host_ip;
                }
                $exclusions[] = '127.0.0.1';
                // And delete all 127.0.0.1 entries for local hosts
            } else {
                $wpdb->query('DELETE FROM ' . $db__table__data . ' WHERE network = ' . ip2long('127.0.0.1') . ';');
                if ($wpdb->rows_affected > 0) {
                    $apbct->fw_stats['expected_networks_count'] -= $wpdb->rows_affected;
                    $apbct->save('fw_stats');
                }
            }
        }

        foreach ($exclusions as $exclusion) {
            if (Helper::ipValidate($exclusion) && sprintf('%u', ip2long($exclusion))) {
                $query .= '(' . sprintf('%u', ip2long($exclusion)) . ', ' . sprintf(
                    '%u',
                    bindec(str_repeat('1', 32))
                ) . ', 1),';
            }
        }

        if ($exclusions) {
            $sql_result = $db->execute(substr($query, 0, -1) . ';');

            return $sql_result === false
                ? array('error' => 'COULD_NOT_WRITE_TO_DB 4: ' . $db->getLastError())
                : count($exclusions);
        }

        return 0;
    }

    /**
     * Creating a temporary updating table
     *
     * @param DB $db database handler
     * @param array|string $table_names Array with table names to create
     *
     * @return bool|array
     */
    public static function createTempTables($db, $table_names)
    {
        // Cast it to array for simple input
        $table_names = (array)$table_names;

        foreach ($table_names as $table_name) {
            if ( !$db->isTableExists($table_name) ) {
                continue;
            }

            $table_name__temp = $table_name . '_temp';

            if ( ! $db->execute('CREATE TABLE IF NOT EXISTS `' . $table_name__temp . '` LIKE `' . $table_name . '`;')) {
                return array('error' => 'CREATE TEMP TABLES: COULD NOT CREATE ' . $table_name__temp
                    . ' DB Error: ' . $db->getLastError() );
            }

            if ( ! $db->execute('TRUNCATE TABLE `' . $table_name__temp . '`;')) {
                return array('error' => 'CREATE TEMP TABLES: COULD NOT TRUNCATE' . $table_name__temp
                    . ' DB Error: ' . $db->getLastError() );
            }
        }

        return true;
    }

    /**
     * Delete tables with given names if they exists
     *
     * @param DB $db
     * @param array|string $table_names Array with table names to delete
     *
     * @return bool|array
     */
    public static function dataTablesDelete($db, $table_names)
    {
        // Cast it to array for simple input
        $table_names = (array)$table_names;

        foreach ($table_names as $table_name) {
            if ( $db->isTableExists($table_name) && ! $db->execute('DROP TABLE ' . $table_name . ';') ) {
                return array(
                    'error' => 'DELETE TABLE: FAILED TO DROP: ' . $table_name
                               . ' DB Error: ' . $db->getLastError()
                );
            }
        }

        return true;
    }

    /**
     * Renaming a temporary updating table into production table name
     *
     * @param DB $db database handler
     * @param array|string $table_names Array with table names to rename
     *
     * @return bool|array
     */
    public static function renameDataTablesFromTempToMain($db, $table_names)
    {
        // Cast it to array for simple input
        $table_names = (array)$table_names;

        foreach ($table_names as $table_name) {
            $table_name__temp = $table_name . '_temp';

            if ( ! $db->isTableExists($table_name__temp)) {
                return array('error' => 'RENAME TABLE: TEMPORARY TABLE IS NOT EXISTS: ' . $table_name__temp);
            }

            if ($db->isTableExists($table_name)) {
                return array('error' => 'RENAME TABLE: MAIN TABLE IS STILL EXISTS: ' . $table_name);
            }

            if ( ! $db->execute('ALTER TABLE `' . $table_name__temp . '` RENAME `' . $table_name . '`;') ) {
                return array(
                    'error' => 'RENAME TABLE: FAILED TO RENAME: ' . $table_name
                               . ' DB Error: ' . $db->getLastError()
                );
            }
        }

        return true;
    }

    /**
     * Add a new records to the SFW table. Duplicates will be updated on "status" field.
     * @param DB $db
     * @param $db__table__data
     * @param $metadata
     * @return array
     * @throws \Exception
     */
    public static function privateRecordsAdd(DB $db, $db__table__data, $metadata)
    {
        $added_count = 0;
        $updated_count = 0;
        $ignored_count = 0;


        foreach ( $metadata as $_key => $row ) {
            //find duplicate to use it on updating
            $has_duplicate = false;
            $query = "SELECT id,status FROM " . $db__table__data . " WHERE 
            network = '" . $row['network'] . "' AND 
            mask = '" . $row['mask'] . "'";

            $db_result = $db->fetch($query);
            if ( $db_result === false ) {
                throw new \RuntimeException($db->getLastError());
            }

            //if the record is same - pass
            if ( isset($db_result['status']) && $db_result['status'] == $row['status'] ) {
                $ignored_count++;
                continue;
            }

            //if duplicate found create a chunk
            if ( isset($db_result['id']) ) {
                $id_chunk = "id ='" . $db_result['id'] . "',";
                $has_duplicate = true;
            } else {
                $id_chunk = '';
            }

            //insertion
            $query = "INSERT INTO " . $db__table__data . " SET 
            " . $id_chunk . "
            network = '" . $row['network'] . "',
            mask = '" . $row['mask'] . "',
            status = '" . $row['status'] . " '
            ON DUPLICATE KEY UPDATE 
            id = id,
            network = network, 
            mask = mask, 
            status = '" . $row['status'] . "';";

            $db_result = $db->execute($query);
            if ( $db_result === false ) {
                throw new \RuntimeException($db->getLastError());
            }

            $added_count = $has_duplicate ? $added_count : $added_count + 1;
            $updated_count = $has_duplicate ? $updated_count + 1 : $updated_count;
        }

        return array(
            'total' => $added_count + $updated_count + $ignored_count,
            'added' => $added_count,
            'updated' => $updated_count,
            'ignored' => $ignored_count,
        );
    }

    /**
     * Delete private records from SFW table.
     * @param DB $db
     * @param $db__table__data
     * @param $metadata
     * @return array|int[]
     * @throws \Exception
     */
    public static function privateRecordsDelete(DB $db, $db__table__data, $metadata)
    {
        $success_count = 0;
        $ignored_count = 0;

        foreach ( $metadata as $_key => $row ) {
            $query = "DELETE FROM " . $db__table__data . " WHERE 
            network = '" . $row['network'] . "' AND
            mask = '" . $row['mask'] . "';";
            $db_result = $db->execute($query);
            if ( $db_result === false ) {
                throw new \Exception($db->getLastError());
            }

            $success_count = $db_result === 1 ? $success_count + 1 : $success_count;
            $ignored_count = $db_result === 0 ? $ignored_count + 1 : $ignored_count;
        }

        return array(
            'total' => $success_count + $ignored_count,
            'deleted' => $success_count,
            'ignored' => $ignored_count
        );
    }

    public static function getSFWTablesNames()
    {
        global $apbct;
        $out = array();
        $out['sfw_personal_table_name'] = APBCT_TBL_FIREWALL_DATA_PERSONAL;
        $out['sfw_common_table_name'] = APBCT_TBL_FIREWALL_DATA;


        if ( APBCT_WPMS && !is_main_site() ) {
            $main_blog_options = get_blog_option(get_main_site_id(), 'cleantalk_data');
            if ( !isset($main_blog_options['sfw_common_table_name']) || !is_string($main_blog_options['sfw_common_table_name'])) {
                return false;
            } else {
                $out['sfw_common_table_name'] = $main_blog_options['sfw_common_table_name'];
            }
        }

        //if mutual key use the main personal table
        if ( APBCT_WPMS && $apbct->network_settings['multisite__work_mode'] === 2 ) {
            if (!isset($main_blog_options['sfw_personal_table_name']) || !is_string($main_blog_options['sfw_personal_table_name'])) {
                return false;
            } else {
                $out['sfw_personal_table_name'] = $main_blog_options['sfw_personal_table_name'];
            }
        }

        return $out;
    }
}
