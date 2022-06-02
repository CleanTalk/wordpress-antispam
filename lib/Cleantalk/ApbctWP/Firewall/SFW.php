<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\ApbctWP\API;
use Cleantalk\ApbctWP\DB;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\Variables\Get;
use Cleantalk\Variables\Server;

class SFW extends \Cleantalk\Common\Firewall\FirewallModule
{
    /**
     * @var bool
     */
    private $test;

    // Additional params
    private $sfw_counter = false;
    private $api_key = false;
    private $blocked_ips = array();
    private $data__cookies_type = false;
    private $cookie_domain = false;

    public $module_name = 'SFW';

    private $real_ip;
    private $debug;
    private $debug_data = '';

    /**
     * @var string Content of the die page
     */
    private $sfw_die_page;

    /**
     * FireWall_module constructor.
     * Use this method to prepare any data for the module working.
     *
     * @param string $log_table
     * @param string $data_table
     * @param $params
     */
    public function __construct($log_table, $data_table, $params = array())
    {
        parent::__construct($log_table, $data_table, $params);

        $this->db__table__data = $data_table ?: null;
        $this->db__table__logs = $log_table ?: null;

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

        if (Get::get('sfw_test_ip')) {
            if (Helper::ipValidate(Get::get('sfw_test_ip')) !== false) {
                $ips['sfw_test'] = Get::get('sfw_test_ip');
                $this->test_ip   = Get::get('sfw_test_ip');
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

        // Skip by cookie
        foreach ($this->ip_array as $current_ip) {
            if (strpos(Cookie::get('ct_sfw_pass_key'), md5($current_ip . $this->api_key)) === 0) {
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

                if (strlen(Cookie::get('ct_sfw_pass_key')) > 32) {
                    $status = substr(Cookie::get('ct_sfw_pass_key'), -1);
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
        foreach ($this->ip_array as $_origin => $current_ip) {
            $current_ip_v4 = sprintf("%u", ip2long($current_ip));
            for ($needles = array(), $m = 6; $m <= 32; $m++) {
                $mask      = str_repeat('1', $m);
                $mask      = str_pad($mask, 32, '0');
                $needles[] = sprintf("%u", bindec($mask & base_convert($current_ip_v4, 10, 2)));
            }
            $needles = array_unique($needles);

            $db_results = $this->db->fetchAll(
                "SELECT
				network, mask, status, source
				FROM " . $this->db__table__data . "
				WHERE network IN (" . implode(',', $needles) . ")
				AND	network = " . $current_ip_v4 . " & mask 
				AND " . rand(1, 100000) . "  
				ORDER BY mask DESC LIMIT 1"
            );


            if ( ! empty($db_results)) {
                foreach ($db_results as $db_result) {
                    $result_entry = array(
                        'ip'          => $current_ip,
                        'network'     => Helper::ipLong2ip($db_result['network'])
                                         . '/'
                                         . Helper::ipMaskLongToNumber((int)$db_result['mask']),
                        'is_personal' => $db_result['source'],
                    );

                    if ((int)$db_result['status'] === 1) {
                        $result_entry['status'] = 'PASS_SFW__BY_WHITELIST';
                        if ( $_origin === 'sfw_test' ) {
                            $result_entry['status'] = 'PASS_SFW__BY_STATUS';
                        }
                        break;
                    }
                    if ((int)$db_result['status'] === 0) {
                        $this->blocked_ips[] = Helper::ipLong2ip($db_result['network']);
                        $result_entry['status'] = 'DENY_SFW';
                    }
                }
            } else {
                $result_entry = array(
                    'ip'          => $current_ip,
                    'is_personal' => null,
                    'status'      => 'PASS_SFW',
                );
            }

            $results[] = $result_entry;
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
                Server::get('HTTP_USER_AGENT'),
                $network,
                substr(Server::get('HTTP_HOST') . Server::get('REQUEST_URI'), 0, 100),
                substr(Server::get('HTTP_HOST') . Server::get('REQUEST_URI'), 0, 100),

                Server::get('HTTP_USER_AGENT'),
                $network,
                substr(Server::get('HTTP_HOST') . Server::get('REQUEST_URI'), 0, 100),
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

            $js_url = APBCT_URL_PATH . '/js/apbct-public--functions.min.js?' . APBCT_VERSION;

            $net_count = $apbct->stats['sfw']['entries'];

            $status     = $result['status'] === 'PASS_SFW__BY_WHITELIST' ? '1' : '0';
            $cookie_val = md5($result['ip'] . $this->api_key) . $status;

            $block_message = sprintf(
                esc_html__('SpamFireWall is checking your browser and IP %s for spam bots', 'cleantalk-spam-protect'),
                '<a href="' . $result['ip'] . '" target="_blank">' . $result['ip'] . '</a>'
            );

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
                '{REMOTE_ADDRESS}'                 => $result['ip'],
                '{SERVICE_ID}'                     => $apbct->data['service_id'] . ', ' . $net_count,
                '{HOST}'                           => get_home_url() . ', ' . APBCT_VERSION,
                '{GENERATED}'                      => '<p>The page was generated at&nbsp;' . date('D, d M Y H:i:s') . '</p>',
                '{REQUEST_URI}'                    => Server::get('REQUEST_URI'),

                // Cookie
                '{COOKIE_PREFIX}'                  => '',
                '{COOKIE_DOMAIN}'                  => $this->cookie_domain,
                '{COOKIE_SFW}'                     => $this->test ? $this->test_ip : $cookie_val,
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
            );

            /**
             * Message about IP status
             */
            if (! empty(Get::get('sfw_test_ip'))) {
                $message_ip_status = __(
                    'IP in the common blacklist',
                    'cleantalk-spam-protect'
                );
                $message_ip_status_color = 'red';

                if ($result['status'] === 'PASS_SFW__BY_STATUS') {
                    $message_ip_status = __(
                        'IP in the common whitelist',
                        'cleantalk-spam-protect'
                    );
                    $message_ip_status_color = 'green';
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
                         . Server::get('REMOTE_ADDR')
                         . '<h1>SERVER_ADDR</h1>'
                         . Server::get('REMOTE_ADDR')
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
    public static function sendLog($db, $log_table, $ct_key, $_use_delete_command)
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
                if ($result['rows'] == count($data)) {
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

    public static function directUpdateGetBlackLists($api_key)
    {
        // Getting remote file name
        $result = API::methodGet2sBlacklistsDb($api_key, null, '3_1');

        if ( empty($result['error']) ) {
            return array(
                'blacklist'  => isset($result['data'])             ? $result['data']             : null,
                'useragents' => isset($result['data_user_agents']) ? $result['data_user_agents'] : null,
                'bl_count'   => isset($result['networks_count'])   ? $result['networks_count']   : null,
                'ua_count'   => isset($result['ua_count'])         ? $result['ua_count']         : null,
            );
        }

        return $result;
    }

    public static function directUpdate($db, $db__table__data, $blacklists)
    {
        if ( ! is_array($blacklists) ) {
            return array('error' => 'BlackLists is not an array.');
        }
        for ( $count_result = 0; current($blacklists) !== false; ) {
            $query = "INSERT INTO " . $db__table__data . " (network, mask, status) VALUES ";

            for (
                $i = 0, $values = array();
                APBCT_WRITE_LIMIT !== $i && current($blacklists) !== false;
                $i++, $count_result++, next($blacklists)
            ) {
                $entry = current($blacklists);

                if ( empty($entry) ) {
                    continue;
                }

                // Cast result to int
                $ip      = preg_replace('/[^\d]*/', '', $entry[0]);
                $mask    = preg_replace('/[^\d]*/', '', $entry[1]);
                $private = isset($entry[2]) ? $entry[2] : 0;

                $values[] = '(' . $ip . ',' . $mask . ',' . $private . ')';
            }

            if ( ! empty($values) ) {
                $query .= implode(',', $values) . ';';
                $result = $db->execute($query);
                if ( $result === false ) {
                    return array( 'error' => $db->getLastError() );
                }
            }
        }

        return $count_result;
    }

    /**
     * Updates SFW local base
     *
     * @param $db
     * @param $db__table__data
     * @param null|string $file_url File URL with SFW data.
     *
     * @return array|int array('error' => STRING)
     */
    public static function updateWriteToDb($db, $db__table__data, $file_url = null)
    {
        $file_content = file_get_contents($file_url);

        if (function_exists('gzdecode')) {
            $unzipped_content = gzdecode($file_content);

            if ($unzipped_content !== false) {
                $data = Helper::bufferParseCsv($unzipped_content);

                if (empty($data['errors'])) {
                    reset($data);

                    for ($count_result = 0; current($data) !== false;) {
                        $query = "INSERT INTO " . $db__table__data . " (network, mask, status, source) VALUES ";

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
                            $source = isset($entry[3]) ? (int)$entry[3] : 'NULL';

                            $values[] = "($ip, $mask, $status, $source)";
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
            if ( ! in_array(Server::getDomain(), array('lc', 'loc', 'lh'))) {
                $exclusions[] = Helper::dnsResolve(Server::get('HTTP_HOST'));
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
}
