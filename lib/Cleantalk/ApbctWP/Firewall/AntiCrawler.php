<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\ApbctWP\RequestParameters\RequestParameters;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Validate;
use Cleantalk\Common\Helper;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;
use Cleantalk\ApbctWP\ApbctJsBundleResolver;

/**
 * Class AntiCrawler
 * @package Cleantalk\ApbctWP\Firewall
 *
 * @psalm-suppress PossiblyUnusedProperty
 */
class AntiCrawler extends \Cleantalk\Common\Firewall\FirewallModule
{
    const COOKIE_NAME__ANTIBOT = 'wordpress_apbct_antibot';
    const COOKIE_NAME__ANTICRAWLER_PASSED = 'apbct_anticrawler_passed';
    const PARAM_NAME__BOT_DETECTOR_EXIST = 'apbct_bot_detector_exist';

    public $module_name = 'ANTICRAWLER';
    /**
     * @var null|string
     */
    private $db__table__ac_logs;
    /**
     * @var null|string
     */
    private $db__table__ac_ua_bl;
    /**
     * @var string
     */
    private $api_key = '';
    /**
     * @var State
     */
    private $apbct;
    /**
     * @var int
     */
    private $store_interval = 86400;
    /**
     * @var string
     */
    private $sign; //Signature - User-Agent + Protocol
    /**
     * @var string
     */
    private $ua_id = 'null'; //User-Agent
    /**
     * @var string
     */
    private $ac_log_result = '';
    /**
     * @var bool
     */
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

    private $default_module_results = [];

    private $debug_mode = false;

    /**
     * AntiCrawler constructor.
     *
     * @param string|null $log_table     Fully-qualified name of the SFW log table.
     * @param string|null $ac_logs_table Fully-qualified name of the AntiCrawler log table.
     * @param array       $params        Optional map of property overrides.
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
        $this->db__table__logs     = $log_table ?: '';
        $this->db__table__ac_logs  = $ac_logs_table ?: null;
        $this->db__table__ac_ua_bl     = defined('APBCT_TBL_AC_UA_BL') ? APBCT_TBL_AC_UA_BL : null;
        $this->sign                    = md5($this->server__http_user_agent . $this->server__https . $this->server__http_host);

        foreach ( $params as $param_name => $param ) {
            $this->$param_name = isset($this->$param_name) ? $param : false;
        }

        $this->isExcluded = $this->checkExclusions();
    }

    /**
     * Use this method to execute the main logic of the module.
     *
     * @return array  Array of the check results
     */
    public function check()
    {
        /**
         * Module check start.
         */
        $this->debug('check() started', null, true);

        /**
         * Precheck for an empty or invalid key
         */
        if ( ! $this->isApiKeyValid() ) {
            $this->debug('Module exit: isApiKeyValid');
            return $this->default_module_results;
        }

        /**
         * Pre-checks: redirect, UA blacklist, cookie pass
         */
        $this->debug('Start handling pre-checks for IP pool');
        $precheck_result = $this->runPreChecksForIPPool($this->ip_array);
        if ( false !== ($precheck_result) ) {
            // return results if any result found, no need to process logs search
            return $precheck_result;
        }

        /**
         * Logs check: IP in logs
         */
        $this->debug('Start handling IP log entries for IP pool');
        $log_search_results = $this->runLogSearchForIpPool($this->ip_array);

        /**
         * Exit.
         */
        $this->debug('Module exit with results', $log_search_results);
        return $log_search_results;
    }

    /**
     * Return true when both the API key and the key-is-ok flag are non-empty.
     *
     * @return bool
     */
    private function isApiKeyValid()
    {
        return ! empty($this->apbct->key_is_ok) && ! empty($this->apbct->api_key);
    }

    /**
     * @param string[] $ip_array
     * @return array|false
     */
    private function runPreChecksForIPPool($ip_array)
    {
        $results = array();
        // Pre-checks: redirect, UA blacklist, cookie pass
        foreach ( $ip_array as $_ip_origin => $current_ip ) {
            $this->debug('Pre-check IP', $current_ip);
            if ( $this->requestIsRedirected() ) {
                $results[] = $this->makeResult($current_ip, 'PASS_ANTICRAWLER');
                $this->debug('Early module exit: requestIsRedirected', $results);
                return $results;
            }

            $ua_check = $this->performUaCheck($current_ip);
            $results  = array_merge($results, $ua_check['entries']);
            if ( $ua_check['early_return'] ) {
                $this->debug('Early module exit: UA found with result', $results);
                return $results;
            }

            $cookie_passed = $this->visitorHasAntiBotCookie();
            $has_bot_detector = $this->visitorHasBotDetectorRequestParam();

            if ($cookie_passed || $has_bot_detector) {
                $cookie_passed && $this->debug(self::COOKIE_NAME__ANTIBOT . ' cookie found');
                $has_bot_detector && $this->debug('bot detector found in request params');
                $results[] = $this->makeResult($current_ip, 'PASS_ANTICRAWLER');
                $cookie_reset = $this->handleAntiCrawlerPassedCookie(null);
                if ( $cookie_reset ) {
                    $this->updateLog($current_ip, 'PASS_ANTICRAWLER');
                    $this->debug('log updated - PASS_ANTICRAWLER', $current_ip);
                }
                $this->debug('Early module exit: bot detector or antibot cookie found', $results);
                return $results;
            }
        }
        //nothing found - proceed further
        return false;
    }

    /**
     * This method will search records in the AntiCrawler log table for the current IP.
     * If the IP is found, the module will return an array containing the check result entry.
     * If the IP is not found, the method will return null.
     * @param $ip_array
     * @return array 1st element is array of results, 2nd element is early return status
     */
    private function runLogSearchForIpPool($ip_array)
    {
        $results = array();
        foreach ( $ip_array as $_ip_origin => $current_ip ) {
            $this->debug('Check logs for IP', $current_ip);
            $ip_check = $this->performIpLogCheck($current_ip);
            if ( $ip_check !== null ) {
                $results[] = $ip_check['entry'];
                if ( $ip_check['early_return'] ) {
                    $this->debug('Early module exit: IP found in logs', $results);
                    return $results;
                }
            }
        }
        return $results;
    }

    /**
     * Build a single check-result entry.
     *
     * @param string $ip
     * @param string $status
     * @return array
     */
    private function makeResult($ip, $status)
    {
        return array('ip' => $ip, 'is_personal' => false, 'status' => $status);
    }

    /**
     * Match the current User-Agent against the UA blacklist table.
     *
     * @param string $current_ip
     * @return array{entries: array, early_return: bool}
     */
    private function performUaCheck($current_ip)
    {
        $ua_bl_results = $this->db->fetchAll(
            "SELECT * FROM " . $this->db__table__ac_ua_bl . " ORDER BY `ua_status` DESC;"
        );

        if ( empty($ua_bl_results) ) {
            return array('entries' => array(), 'early_return' => false);
        }

        foreach ( $ua_bl_results as $ua_bl_result ) {
            if (
                ! empty($ua_bl_result['ua_template']) && preg_match(
                    "%" . str_replace('"', '', $ua_bl_result['ua_template']) . "%i",
                    $this->server__http_user_agent
                )
            ) {
                $this->ua_id = TT::getArrayValueAsString($ua_bl_result, 'id');

                if ( TT::getArrayValueAsString($ua_bl_result, 'ua_status') === '1' ) {
                    // Whitelisted — stop all further checks
                    return array(
                        'entries'      => array($this->makeResult($current_ip, 'PASS_ANTICRAWLER_UA')),
                        'early_return' => true,
                    );
                }

                // Blacklisted — record but continue to cookie check
                return array(
                    'entries'      => array($this->makeResult($current_ip, 'DENY_ANTICRAWLER_UA')),
                    'early_return' => false,
                );
            }
        }

        // No template matched
        return array(
            'entries'      => array($this->makeResult($current_ip, 'PASS_ANTICRAWLER_UA')),
            'early_return' => false,
        );
    }

    /**
     * Return true when the visitor holds a valid antibot cookie whose value
     * matches the SHA-256 hash of the API key and site salt.
     *
     * @return bool
     */
    private function visitorHasAntiBotCookie()
    {
        $hash = hash('sha256', $this->api_key . $this->apbct->data['salt']);
        return Cookie::getString(self::COOKIE_NAME__ANTIBOT) === $hash;
    }

    /**
     * Return true when the bot-detector request parameter equals '1',
     * indicating that the JS bot-detector script has confirmed the visitor is human.
     *
     * @return bool
     */
    private function visitorHasBotDetectorRequestParam()
    {
        return RequestParameters::get(self::PARAM_NAME__BOT_DETECTOR_EXIST, true) == '1';
    }

    /**
     * If the one-shot "anticrawler_passed" cookie is set, expire it.
     *
     * @param false|null $secure_attr
     * @return bool
     */
    private function handleAntiCrawlerPassedCookie($secure_attr)
    {
        if ( Cookie::getString(self::COOKIE_NAME__ANTICRAWLER_PASSED) === '1' ) {
            if ( ! headers_sent() ) {
                Cookie::set(
                    self::COOKIE_NAME__ANTICRAWLER_PASSED,
                    '0',
                    time() - 86400,
                    '/',
                    '',
                    $secure_attr,
                    true,
                    'Lax'
                );
                $this->debug(self::COOKIE_NAME__ANTICRAWLER_PASSED . ' cookie reset to 0');
            }
            return true;
        }
        return false;
    }

    /**
     * Check whether this IP has been seen before (exists in the AC log table).
     * If yes, delegate to handleKnownIp(); if not, register the WP hooks that
     * will record this visit and inject the JS cookie-setter.
     *
     * @param string $current_ip
     * @return array{entry: array, early_return: bool}|null
     */
    private function performIpLogCheck($current_ip)
    {
        $result = $this->db->fetch(
            "SELECT ip"
            . ' FROM `' . $this->db__table__ac_logs . '`'
            . " WHERE ip = '$current_ip'"
            . " AND ua = '$this->sign' AND " . rand(1, 100000) . ";"
        );

        if ( isset($result['ip']) ) {
            return $this->handleKnownIp($current_ip);
        }

        $this->registerNewVisitorHooks();
        return null;
    }

    /**
     * Decide DENY or PASS for an IP that is already present in the AC log.
     *
     * @param string $current_ip
     * @return array{entry: array, early_return: bool}|null
     */
    private function handleKnownIp($current_ip)
    {
        if ( !$this->visitorHasAntiBotCookie() && !$this->visitorHasBotDetectorRequestParam() ) {
            return array(
                'entry'        => $this->makeResult($current_ip, 'DENY_ANTICRAWLER'),
                'early_return' => false,
            );
        }

        $cookie_reset = $this->handleAntiCrawlerPassedCookie(false);
        if ($cookie_reset) {
            return array(
                'entry'        => $this->makeResult($current_ip, 'PASS_ANTICRAWLER'),
                'early_return' => true,
            );
        }

        return null;
    }

    /**
     * Register the WordPress actions that record this visit in the AC log
     * and inject the JS cookie-setter into the page.
     */
    private function registerNewVisitorHooks()
    {
        if ( empty(Cookie::getString(self::COOKIE_NAME__ANTIBOT, '')) ) {
            add_action('template_redirect', array(& $this, 'updateAcLog'), 999);
        }

        add_action('wp_head', array('\Cleantalk\ApbctWP\Firewall\AntiCrawler', 'setCookie'));
        add_action('login_head', array('\Cleantalk\ApbctWP\Firewall\AntiCrawler', 'setCookie'));
    }

    /**
     * Insert or update the visitor's IP and User-Agent signature in the AntiCrawler
     * log table for the current time interval.
     * Intended to be called as a WordPress 'template_redirect' action hook.
     */
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


    /**
     * Output an inline JavaScript snippet that sets the antibot cookie in the
     * visitor's browser once the DOM is ready.
     * Hooked to 'wp_head' and 'login_head'.
     */
    public static function setCookie()
    {
        global $apbct;

        $script =
        "<script>
            window.addEventListener('DOMContentLoaded', function () {
                ctSetCookie( " . json_encode(self::COOKIE_NAME__ANTIBOT) . ", '" . hash('sha256', $apbct->api_key . $apbct->data['salt']) . "', 0 );
            });
        </script>";

        echo $script;
    }

    /**
     * Insert or update a visitor entry in the SFW log table.
     *
     * @param string $ip     Visitor IP address.
     * @param string $status Check result status (e.g. 'PASS_ANTICRAWLER', 'DENY_ANTICRAWLER').
     */
    public function updateLog($ip, $status)
    {
        /** @psalm-suppress InvalidLiteralArgument */

        if ( strpos($status, '_UA') !== false ) {
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

    /**
     * Load the AntiCrawler block-page template, populate all placeholders, and
     * schedule rendering via the 'init' action hook.
     *
     * @param array $result Check result entry containing at least the 'ip' key.
     */
    public function diePage($result)
    {
        global $apbct;

        // File exists?
        if ( file_exists(CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page_anticrawler.html") ) {
            $this->sfw_die_page = file_get_contents(
                CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page_anticrawler.html"
            );

            $js_url = ApbctJsBundleResolver::getFullScriptURI($apbct->settings);

            $net_count = $apbct->stats['sfw']['entries'];

            // Custom Logo
            $custom_logo_img = '';
            $custom_logo_id = isset($apbct->settings['cleantalk_custom_logo']) ? $apbct->settings['cleantalk_custom_logo'] : false;

            if ($custom_logo_id && ($image_attributes = wp_get_attachment_image_src($custom_logo_id, array(150, 150)))) {
                $custom_logo_img = '<img src="' . esc_url(TT::getArrayValueAsString($image_attributes, 0)) . '" width="150" alt="" />';
            }

            $ip = TT::getArrayValueAsString($result, 'ip');

            $block_message = sprintf(
                esc_html__(
                    'Anti-Crawler Protection is checking your browser and IP %s for spam bots',
                    'cleantalk-spam-protect'
                ),
                //HANDLE LINK
                '<a href="https://cleantalk.org/blacklists/' . $ip . '" target="_blank">' . $ip . '</a>'
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
                '{REMOTE_ADDRESS}'                 => $ip,
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

    /**
     * Inject localised JS variables into the die page, send a 403 response header,
     * and terminate execution with the rendered block page.
     * Registered as the 'init' action by diePage().
     */
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
            'bot_detector_enabled' => apbct__is_bot_detector_enabled(),
        );

        $replaces = array(
            '{LOCALIZE_SCRIPT}'   => 'var ctPublicFunctions = ' . json_encode($localize_js) . ';' .
                                     'var ctPublic = ' . json_encode($localize_js_public) . ';',
        );

        foreach ( $replaces as $place_holder => $replace ) {
            $this->sfw_die_page = str_replace($place_holder, $replace, $this->sfw_die_page);
        }

        if ( ! headers_sent() ) {
            http_response_code(403);
        }

        // File exists?
        if ( file_exists(CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/ApbctWP/Firewall/die_page_sfw.html") ) {
            die($this->sfw_die_page);
        }

        die("IP BLACKLISTED. Blocked by AntiCrawler " . $this->apbct->stats['last_sfw_block']['ip']);
    }

    /**
     * Determine whether the current request should bypass AntiCrawler checks.
     *
     * Returns true (excluded) when any of the following conditions is met:
     * - The URI points to a W3 Total Cache minified asset listed in the w3tc_minify option.
     * - The skip_anticrawler_on_rss_feed service constant is defined and the request is an RSS feed.
     * - An SFW test is running and the visitor holds a valid antibot cookie or bot-detector param.
     * - The current user has the 'administrator' or 'editor' role.
     *
     * @return bool True when the request should be excluded from AntiCrawler checks.
     */
    private function checkExclusions()
    {
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
                            $this->debug('exclusions precheck: found W3 rules');
                            return true;
                        }
                    }
                }
            }
        }

        // skip for RSS Feed requests
        if ($this->apbct->service_constants->skip_anticrawler_on_rss_feed->isDefined()) {
            if (Server::getString('REQUEST_URI') &&
                preg_match_all('/feed/i', Server::getString('REQUEST_URI'))
            ) {
                $this->debug(
                    'exclusions precheck: RSS feed requests disabled by service constant',
                    $this->apbct->service_constants->skip_anticrawler_on_rss_feed->allowed_public_names
                );
                return true;
            }
            if (is_feed()) {
                $this->debug('exclusions precheck: native RSS feed request sign');
                return true;
            }
        }

        //skip check if SFW test is running
        if (
            Get::get('sfw_test_ip') &&
            (Cookie::getString(self::COOKIE_NAME__ANTIBOT) == hash(
                'sha256',
                $this->api_key . $this->apbct->data['salt']
            ) ||
            RequestParameters::get(self::PARAM_NAME__BOT_DETECTOR_EXIST, true) == '1')
        ) {
            $this->debug('exclusions precheck: SFW test request sign');
            return true;
        }

        $allowed_roles = array('administrator', 'editor');
        $user          = apbct_wp_get_current_user();

        if ( ! $user ) {
            return false;
        }

        foreach ( $allowed_roles as $role ) {
            if ( in_array($role, (array)$user->roles) ) {
                $this->debug('exclusions precheck: allowed user role found', $role);
                return true;
            }
        }

        return false;
    }

    /**
     * Return true when the current response is a 301/302 redirect, or when a
     * Cloudflare-proxied request arrives from a different host than its referer,
     * indicating that the visitor was just redirected to this page.
     *
     * @return bool
     */
    private function requestIsRedirected()
    {
        $is_redirect = false;
        if ( $this->server__http_referer !== '' && $this->server__http_host !== '' && $this->serverIsOnCloudflare() ) {
            $parse_referer = parse_url($this->server__http_referer);
            if ( $parse_referer && isset($parse_referer['host']) ) {
                $is_redirect = $this->server__http_host !== $parse_referer['host'];
            }
        }

        return http_response_code() === 301 || http_response_code() === 302 || $is_redirect;
    }

    /**
     * Return true when the request carries Cloudflare-specific HTTP headers
     * (CF-Ray, CF-Connecting-IP, CF-Request-ID), indicating it was proxied through Cloudflare.
     *
     * @return bool
     */
    private function serverIsOnCloudflare()
    {
        return Server::get('HTTP_CF_RAY') && Server::get('HTTP_CF_CONNECTING_IP') && Server::get('HTTP_CF_REQUEST_ID');
    }

    /**
     * Remove stale AntiCrawler log entries that belong to a previous time interval.
     * Should be called once per $store_interval seconds (default: once a day).
     */
    public function clearLogTable()
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
    /**
     * Import the UA blacklist from a gzip-compressed CSV file into the database.
     * Processes records in batches and deletes the source file after import.
     *
     * @param string $file_path_ua Absolute path to the gzipped CSV file.
     * @return int|array           Number of imported rows on success, or an array with an 'error' key on failure.
     */
    public static function updateUADataTable($file_path_ua)
    {
        $file_content = file_get_contents($file_path_ua);

        if ( $file_content === false ) {
            return array('error' => 'Can not read datafile');
        }

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

    /**
     * Truncate the UA blacklist data table and reset its AUTO_INCREMENT counter.
     * Falls back to a full DELETE if TRUNCATE does not completely empty the table.
     *
     * @param \Cleantalk\ApbctWP\DB $db              Database instance.
     * @param string                $db__table__data Fully-qualified table name to clear.
     * @return array|void                            Array with an 'error' key if the table could not be cleared.
     */
    public static function clearUADataTable($db, $db__table__data)
    {
        $db->execute("TRUNCATE TABLE {$db__table__data};");
        $db->setQuery("SELECT COUNT(*) as cnt FROM {$db__table__data};")->fetch(); // Check if it is clear
        if ( isset($db->result['cnt']) && $db->result['cnt'] != 0 ) {
            $db->execute("DELETE FROM {$db__table__data};"); // Truncate table
            $db->setQuery("SELECT COUNT(*) as cnt FROM {$db__table__data};")->fetch(); // Check if it is clear
            if ( isset($db->result['cnt']) && $db->result['cnt'] != 0 ) {
                return array('error' => 'COULD_NOT_CLEAR_UA_BL_TABLE'); // throw an error
            }
        }
        $db->execute("ALTER TABLE {$db__table__data} AUTO_INCREMENT = 1;"); // Drop AUTO INCREMENT
    }

    /**
     * Write a timestamped debug message to the PHP error log when DEBUG mode is enabled.
     *
     * @param string     $message Human-readable description of the event.
     * @param mixed|null $data    Optional context data; arrays and objects are serialised with print_r().
     */
    private function debug(string $message, $data = null, $add_constructor_data = false)
    {
        if ( ! $this->debug_mode) {
            return;
        }

        if ( $data === null ) {
            $data = '';
        } elseif ( is_array($data) || is_object($data) ) {
            $data = ' ' . print_r($data, true);
        } elseif ( is_bool($data) ) {
            $data = ' ' . ($data ? 'true' : 'false');
        } elseif ( is_scalar($data) ) {
            $data = ' ' . (string) $data;
        } else {
            $data = ' [' . gettype($data) . ']';
        }

        $constructor_data = '';
        if ( $add_constructor_data ) {
            $constructor_data = print_r([
                'server__http_user_agent' => $this->server__http_user_agent,
                'ip_array' => $this->ip_array,
                'server__https' => $this->server__https,
                'server__http_host' => $this->server__http_host,
                'server__request_uri' => $this->server__request_uri,
                'server__http_referer' => $this->server__http_referer,
            ], true);
            if ( empty($constructor_data) ) {
                $constructor_data = 'invalid constructor data';
            }
        }

        $log_record = sprintf(
            '%s: [%s] %s%s%s',
            date('Y-m-d H:i:s'),
            $this->module_name,
            $message,
            $data,
            $constructor_data
        );

        error_log($log_record);
    }
}
