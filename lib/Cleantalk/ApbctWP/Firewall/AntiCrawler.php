<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\ApbctWP\RequestParameters\RequestParameters;
use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\Validate;
use Cleantalk\Common\Helper;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;

/**
 * Class AntiCrawler
 * @package Cleantalk\ApbctWP\Firewall
 *
 * @psalm-suppress PossiblyUnusedProperty
 */
class AntiCrawler extends \Cleantalk\Common\Firewall\FirewallModule
{
    public $module_name = 'ANTICRAWLER';

    private $db__table__ac_logs;
    private $db__table__ac_ua_bl;
    private $api_key = '';
    private $apbct;
    private $store_interval = 86400;
    private $sign; //Signature - User-Agent + Protocol
    private $ua_id = 'null'; //User-Agent

    private $ac_log_result = '';

    public $isExcluded = false;

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
     * @psalm-suppress UnusedProperty
     */
    private $server__https;
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
    private $server__http_referer;

    /**
     * AntiBot constructor.
     *
     * @param $log_table
     * @param $ac_logs_table
     * @param array $params
     */
    public function __construct($log_table, $ac_logs_table, $params = array())
    {
        parent::__construct($log_table, $ac_logs_table, $params);

        // init server vars
        $this->server__https           = TT::toString(Server::get('HTTPS'));
        $this->server__http_user_agent = TT::toString(Server::get('HTTP_USER_AGENT'));
        $this->server__http_host       = TT::toString(Server::get('HTTP_HOST'));
        $this->server__request_uri  = TT::toString(Server::get('REQUEST_URI'));
        $this->server__http_referer = TT::toString(Server::get('HTTP_REFERER'));

        global $apbct;
        $this->apbct               = $apbct;
        $this->db__table__logs     = $log_table ?: null;
        $this->db__table__ac_logs  = $ac_logs_table ?: null;
        $this->db__table__ac_ua_bl     = defined('APBCT_TBL_AC_UA_BL') ? APBCT_TBL_AC_UA_BL : null;
        $this->sign                    = md5($this->server__http_user_agent . $this->server__https . $this->server__http_host);

        foreach ( $params as $param_name => $param ) {
            $this->$param_name = isset($this->$param_name) ? $param : false;
        }

        $this->isExcluded = $this->checkExclusions();
    }

    public static function update($file_path_ua)
    {
        $file_content = file_get_contents($file_path_ua);

        if ( ! function_exists('gzdecode') ) {
            return array('error' => 'Function gzdecode not exists. Please update your PHP at least to version 5.4 ');
        }

        $unzipped_content = gzdecode($file_content);

        if ( $unzipped_content === false ) {
            return array('error' => 'Can not unpack datafile');
        }

        $lines = \Cleantalk\ApbctWP\Helper::bufferParseCsv($unzipped_content);

        for ( $count_result = 0; current($lines) !== false; ) {
            $query = "INSERT INTO " . APBCT_TBL_AC_UA_BL . " (id, ua_template, ua_status) VALUES ";

            for (
                $i = 0, $values = array();
                APBCT_WRITE_LIMIT !== $i && current($lines) !== false;
                $i++, $count_result++, next($lines)
            ) {
                $entry = current($lines);

                if ( empty($entry) || ! isset($entry[0], $entry[1]) ) {
                    continue;
                }

                // Cast result to int
                $ua_id       = preg_replace('/[^\d]*/', '', $entry[0]);
                $ua_template = isset($entry[1]) && Validate::isRegexp($entry[1]) ? Helper::dbPrepareParam(
                    $entry[1]
                ) : 0;
                $ua_status   = isset($entry[2]) ? $entry[2] : 0;

                if ( ! $ua_template ) {
                    continue;
                }

                $values[] = '(' . $ua_id . ',' . $ua_template . ',' . $ua_status . ')';
            }

            if ( ! empty($values) ) {
                $query = $query . implode(',', $values) . ' ON DUPLICATE KEY UPDATE ua_status=0';
                \Cleantalk\ApbctWP\DB::getInstance()->execute($query);
            }
        }

        if ( file_exists($file_path_ua) ) {
            unlink($file_path_ua);
        }

        return $count_result;
    }

    public static function clearDataTable($db, $db__table__data)
    {
        $db->execute("TRUNCATE TABLE {$db__table__data};");
        $db->setQuery("SELECT COUNT(*) as cnt FROM {$db__table__data};")->fetch(); // Check if it is clear
        if ( $db->result['cnt'] != 0 ) {
            $db->execute("DELETE FROM {$db__table__data};"); // Truncate table
            $db->setQuery("SELECT COUNT(*) as cnt FROM {$db__table__data};")->fetch(); // Check if it is clear
            if ( $db->result['cnt'] != 0 ) {
                return array('error' => 'COULD_NOT_CLEAR_UA_BL_TABLE'); // throw an error
            }
        }
        $db->execute("ALTER TABLE {$db__table__data} AUTO_INCREMENT = 1;"); // Drop AUTO INCREMENT
    }

    /**
     * Use this method to execute main logic of the module.
     *
     * @return array  Array of the check results
     */
    public function check()
    {
        $results = array();

        foreach ( $this->ip_array as $_ip_origin => $current_ip ) {
            // Skip by 301 response code
            if ( $this->isRedirected() ) {
                $results[] = array('ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_ANTICRAWLER',);

                return $results;
            }

            // UA check
            $ua_bl_results = $this->db->fetchAll(
                "SELECT * FROM " . $this->db__table__ac_ua_bl . " ORDER BY `ua_status` DESC;"
            );

            if ( ! empty($ua_bl_results) ) {
                $is_blocked = false;

                foreach ( $ua_bl_results as $ua_bl_result ) {
                    if (
                        ! empty($ua_bl_result['ua_template']) && preg_match(
                            "%" . str_replace('"', '', $ua_bl_result['ua_template']) . "%i",
                            $this->server__http_user_agent
                        )
                    ) {
                        $this->ua_id = TT::getArrayValueAsString($ua_bl_result, 'id');

                        if ( TT::getArrayValueAsString($ua_bl_result, 'ua_status') === '1' ) {
                            // Whitelisted
                            $results[] = array(
                                'ip'          => $current_ip,
                                'is_personal' => false,
                                'status'      => 'PASS_ANTICRAWLER_UA',
                            );

                            return $results;
                        } else {
                            // Blacklisted
                            $results[]  = array(
                                'ip'          => $current_ip,
                                'is_personal' => false,
                                'status'      => 'DENY_ANTICRAWLER_UA',
                            );
                            $is_blocked = true;
                            break;
                        }
                    }
                }

                if ( ! $is_blocked ) {
                    $results[] = array('ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_ANTICRAWLER_UA',);
                }
            }

            // Skip by cookie
            if (
                Cookie::get('wordpress_apbct_antibot') == hash(
                    'sha256',
                    $this->api_key . $this->apbct->data['salt']
                ) ||
                RequestParameters::get('apbct_bot_detector_exist', true)  == '1'
            ) {
                if ( Cookie::get('apbct_anticrawler_passed') == 1 ) {
                    if ( ! headers_sent() ) {
                        Cookie::set('apbct_anticrawler_passed', '0', time() - 86400, '/', '', null, true, 'Lax');
                    }

                    // Do logging an one passed request
                    $this->updateLog($current_ip, 'PASS_ANTICRAWLER');
                }

                $results[] = array('ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS_ANTICRAWLER',);

                return $results;
            }
        }

        // Common check
        foreach ( $this->ip_array as $_ip_origin => $current_ip ) {
            // IP check
            $result = $this->db->fetch(
                "SELECT ip"
                . ' FROM `' . $this->db__table__ac_logs . '`'
                . " WHERE ip = '$current_ip'"
                . " AND ua = '$this->sign' AND " . rand(1, 100000) . ";"
            );
            if ( isset($result['ip']) ) {
                if (
                    Cookie::get('wordpress_apbct_antibot') !== hash(
                        'sha256',
                        $this->api_key . $this->apbct->data['salt']
                    ) &&
                    (
                        !RequestParameters::get('apbct_bot_detector_exist', true) ||
                        RequestParameters::get('apbct_bot_detector_exist', true) == '0'
                    )
                ) {
                    $results[] = array('ip' => $current_ip, 'is_personal' => false, 'status' => 'DENY_ANTICRAWLER',);
                } else {
                    if ( Cookie::get('apbct_anticrawler_passed') === '1' ) {
                        if ( ! headers_sent() ) {
                            \Cleantalk\ApbctWP\Variables\Cookie::set(
                                'apbct_anticrawler_passed',
                                '0',
                                time() - 86400,
                                '/',
                                '',
                                false,
                                true,
                                'Lax'
                            );
                        }

                        $results[] = array(
                            'ip'          => $current_ip,
                            'is_personal' => false,
                            'status'      => 'PASS_ANTICRAWLER',
                        );

                        return $results;
                    }
                }
            } else {
                if ( ! Cookie::get('wordpress_apbct_antibot') ) {
                    add_action('template_redirect', array(& $this, 'updateAcLog'), 999);
                }

                add_action('wp_head', array('\Cleantalk\ApbctWP\Firewall\AntiCrawler', 'setCookie'));
                add_action('login_head', array('\Cleantalk\ApbctWP\Firewall\AntiCrawler', 'setCookie'));
            }
        }

        return $results;
    }

    public function updateAcLog()
    {
        $interval_time = Helper::timeGetIntervalStart($this->store_interval);

        foreach ( $this->ip_array as $_ip_origin => $current_ip ) {
            $id = md5($current_ip . $this->sign . $interval_time);
            $this->db->execute(
                "INSERT INTO " . $this->db__table__ac_logs . " SET
					id = '$id',
					ip = '$current_ip',
					ua = '$this->sign',
					entries = 1,
					interval_start = $interval_time
				ON DUPLICATE KEY UPDATE
					ip = ip,
					entries = entries + 1,
					interval_start = $interval_time;"
            );
        }
    }


    public static function setCookie()
    {
        global $apbct;

        $script =
        "<script>
            window.addEventListener('DOMContentLoaded', function () {
                ctSetCookie( 'wordpress_apbct_antibot', '" . hash('sha256', $apbct->api_key . $apbct->data['salt']) . "', 0 );
            });
        </script>";

        echo $script;
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
        /** @psalm-suppress InvalidLiteralArgument */

        if ( strpos('_UA', $status) !== false ) {
            $id_str = $ip . $this->module_name . '_UA';
        } else {
            $id_str = $ip . $this->module_name;
        }
        $id   = md5($id_str);
        $time = time();

        $query = "INSERT INTO " . $this->db__table__logs . "
			SET
				id = '$id',
				ip = '$ip',
				status = '$status',
				all_entries = 1,
				blocked_entries = " . (strpos($status, 'DENY') !== false ? 1 : 0) . ",
				entries_timestamp = '" . $time . "',
				ua_id = " . $this->ua_id . ",
				ua_name = %s,
				first_url = %s,
                last_url = %s
			ON DUPLICATE KEY
			UPDATE
			    status = '$status',
				all_entries = all_entries + 1,
				blocked_entries = blocked_entries" . (strpos($status, 'DENY') !== false ? ' + 1' : '') . ",
				entries_timestamp = '" . $time . "',
				ua_id = " . $this->ua_id . ",
				ua_name = %s,
				last_url = %s";

        $short_url_to_log = substr($this->server__http_host . $this->server__request_uri, 0, 100);

        $this->db->prepare(
            $query,
            array(
                $this->server__http_user_agent,
                $short_url_to_log,
                $short_url_to_log,
                $this->server__http_user_agent,
                $short_url_to_log,
            )
        );
        $this->db->execute($this->db->getQuery());
    }

    public function diePage($result)
    {
        global $apbct;

        // File exists?
        if ( file_exists(CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page_anticrawler.html") ) {
            $this->sfw_die_page = file_get_contents(
                CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page_anticrawler.html"
            );

            $js_url = APBCT_URL_PATH . '/js/apbct-public-bundle.min.js?' . APBCT_VERSION;

            $net_count = $apbct->stats['sfw']['entries'];

            // Custom Logo
            $custom_logo_img = '';
            $custom_logo_id = isset($apbct->settings['cleantalk_custom_logo']) ? $apbct->settings['cleantalk_custom_logo'] : false;

            if ($custom_logo_id && ($image_attributes = wp_get_attachment_image_src($custom_logo_id, array(150, 150)))) {
                $custom_logo_img = '<img src="' . esc_url(TT::getArrayValueAsString($image_attributes, 0)) . '" width="150" alt="" />';
            }

            $block_message = sprintf(
                esc_html__(
                    'Anti-Crawler Protection is checking your browser and IP %s for spam bots',
                    'cleantalk-spam-protect'
                ),
                //HANDLE LINK
                '<a href="https://cleantalk.org/blacklists/' . $result['ip'] . '" target="_blank">' . $result['ip'] . '</a>'
            );

            // Translation
            $replaces = array(
                '{SFW_DIE_NOTICE_IP}'              => $block_message,
                '{SFW_DIE_MAKE_SURE_JS_ENABLED}'   => __(
                    'To continue working with the web site, please make sure that you have enabled JavaScript.',
                    'cleantalk-spam-protect'
                ),
                '{SFW_DIE_YOU_WILL_BE_REDIRECTED}' =>
                    sprintf(
                        __('You will be automatically redirected to the requested page after %d seconds.', 'cleantalk-spam-protect'),
                        3
                    ) . '<br>'
                    . __('Don\'t close this page. Please, wait for 3 seconds to pass to the page.', 'cleantalk-spam-protect'),
                '{CLEANTALK_TITLE}'                => $apbct->data['wl_brandname'],
                '{CLEANTALK_URL}'                  => $apbct->data['wl_url'],
                '{REMOTE_ADDRESS}'                 => $result['ip'],
                '{SERVICE_ID}'                     => $this->apbct->data['service_id'] . ', ' . $net_count,
                '{HOST}'                           => get_home_url() . ', ' . APBCT_VERSION,
                '{COOKIE_ANTICRAWLER}'             => hash('sha256', $apbct->api_key . $apbct->data['salt']),
                '{COOKIE_ANTICRAWLER_PASSED}'      => '1',
                '{GENERATED}'                      => '<p>The page was generated at&nbsp;' . date('D, d M Y H:i:s') . "</p>",
                '{SCRIPT_URL}'                     => $js_url,

                // Custom Logo
                '{CUSTOM_LOGO}'                    => $custom_logo_img
            );

            foreach ( $replaces as $place_holder => $replace ) {
                $this->sfw_die_page = str_replace($place_holder, $replace, $this->sfw_die_page);
            }

            if ( Get::get('debug') ) {
                $debug = '<h1>Headers</h1>'
                         . str_replace("\n", "<br>", print_r(\apache_request_headers(), true))
                         . '<h1>$_SERVER</h1>'
                         . str_replace("\n", "<br>", print_r($_SERVER, true))
                         . '<h1>AC_LOG_RESULT</h1>'
                         . str_replace("\n", "<br>", print_r($this->ac_log_result, true))
                         . '<h1>IPS</h1>'
                         . str_replace("\n", "<br>", print_r($this->ip_array, true));
            } else {
                $debug = '';
            }
            $this->sfw_die_page = str_replace("{DEBUG}", $debug, $this->sfw_die_page);
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

        foreach ( $replaces as $place_holder => $replace ) {
            $this->sfw_die_page = str_replace($place_holder, $replace, $this->sfw_die_page);
        }

        http_response_code(403);

        // File exists?
        if ( file_exists(CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page_sfw.html") ) {
            die($this->sfw_die_page);
        }

        die("IP BLACKLISTED. Blocked by AntiCrawler " . $this->apbct->stats['last_sfw_block']['ip']);
    }

    private function checkExclusions()
    {
        global $apbct;
        /**
         * Check if W3 Total Cache minified files requested during Anti-Crawler Work.
         * All the next conditions should be true:
         * 1. W3 minified file sign found in server URI
         * 2. W3 plugin is active
         * 2. W3 option w3tc_minify contains this sign
         */
        if (Server::get('REQUEST_URI') && apbct_is_plugin_active('w3-total-cache/w3-total-cache.php')) {
            //get match in uri
            preg_match_all('/\/wp-content\/cache\/minify\/(.+\.(js|css))/', $this->server__request_uri, $matches);
            $w3tc_js_file_name_in_uri = isset($matches[1], $matches[1][0]) ? $matches[1][0] : null;
            if ( !empty($w3tc_js_file_name_in_uri) ) {
                //get option
                $w3tc_minify_option = get_option('w3tc_minify');
                $w3tc_minify_option = false !== $w3tc_minify_option ? json_decode($w3tc_minify_option, true) : null;
                // if option found and is an array
                if (is_array($w3tc_minify_option)) {
                    // check if sign is in option keys
                    $w3tc_minified_files = array_keys($w3tc_minify_option);
                    if ( !empty($w3tc_minified_files) && is_array($w3tc_minified_files) ) {
                        if (in_array($w3tc_js_file_name_in_uri, $w3tc_minified_files)) {
                            return true;
                        }
                    }
                }
            }
        }

        // skip for RSS Feed requests
        if ($apbct->service_constants->skip_anticrawler_on_rss_feed->isDefined()) {
            if (Server::getString('REQUEST_URI') &&
                preg_match_all('/feed/i', Server::getString('REQUEST_URI'))
            ) {
                return true;
            }
            if (is_feed()) {
                return true;
            }
        }

        //skip check if SFW test is running
        if (
            Get::get('sfw_test_ip') &&
            (Cookie::get('wordpress_apbct_antibot') == hash(
                'sha256',
                $this->api_key . $this->apbct->data['salt']
            ) ||
            RequestParameters::get('apbct_bot_detector_exist', true) == '1')
        ) {
            return true;
        }

        $allowed_roles = array('administrator', 'editor');
        $user          = apbct_wp_get_current_user();

        if ( ! $user ) {
            return false;
        }

        foreach ( $allowed_roles as $role ) {
            if ( in_array($role, (array)$user->roles) ) {
                return true;
            }
        }

        return false;
    }

    private function isRedirected()
    {
        $is_redirect = false;
        if ( $this->server__http_referer !== '' && $this->server__http_host !== '' && $this->isCloudflare() ) {
            $parse_referer = parse_url($this->server__http_referer);
            if ( $parse_referer && isset($parse_referer['host']) ) {
                $is_redirect = $this->server__http_host !== $parse_referer['host'];
            }
        }

        return http_response_code() === 301 || http_response_code() === 302 || $is_redirect;
    }

    private function isCloudflare()
    {
        return Server::get('HTTP_CF_RAY') && Server::get('HTTP_CF_CONNECTING_IP') && Server::get('HTTP_CF_REQUEST_ID');
    }

    /**
     * Clear table APBCT_TBL_AC_LOG
     * once a day
     */
    public function clearTable()
    {
        $interval_start = \Cleantalk\ApbctWP\Helper::timeGetIntervalStart($this->store_interval);

        $this->db->execute(
            'DELETE
				FROM ' . $this->db__table__ac_logs . '
				WHERE interval_start < ' . $interval_start . ' 
				AND ua <> ""
				LIMIT 100000;'
        );
    }
}
