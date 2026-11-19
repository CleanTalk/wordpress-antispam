<?php

use Cleantalk\Antispam\ScriptsIntegration\FluentFormScript;
use Cleantalk\Antispam\ScriptsIntegration\GiveWPScript;
use Cleantalk\ApbctWP\Activator;
use Cleantalk\ApbctWP\BotDetectorService;
use Cleantalk\ApbctWP\Cron;
use WpOrg\Requests\Capability;
use WpOrg\Requests\Requests;

class TestBotDetectorWrapperApiTransport
{
    public static $callback;

    public function request($url, $_headers, $data, $options): string
    {
        return call_user_func(self::$callback, $url, $data, $options);
    }
}

class TestBotDetectorWrapper extends ApbctTestCase
{
    private const TEST_KEY = 'apbct-wrapper-test';
    private const OPTION = 'cleantalk_bot_detector_wrapper_url';
    private const CRON_TASK = 'get_bot_detector_wrapper_url';
    private const DEFAULT_URL = 'https://fd.cleantalk.org/ct-bot-detector-wrapper.js';
    private const API_WRAPPER_URL = 'https://fd-region.cleantalk.org/ct-bot-detector-wrapper.js?channel=stable';
    private const WRAPPER_BODY = 'const script = document.createElement("script");'
        . 'script.src = "https://fd-region.cleantalk.org/2.1/ct-bot-detector.min.js";';

    private $api_response;
    private $wrapper_response;
    private $requests;
    private $previous_api_transports;
    private $previous_http_setting;

    protected function setUp(): void
    {
        global $apbct;

        $apbct->api_key = self::TEST_KEY;
        $apbct->data['bot_detector_enabled'] = 1;
        delete_option(self::OPTION);

        $this->previous_http_setting = $apbct->settings['wp__use_builtin_http_api'];
        $apbct->settings['wp__use_builtin_http_api'] = 1;
        $this->previous_api_transports = Requests::$transport;
        foreach (array(false, true) as $ssl) {
            Requests::$transport[serialize(array(Capability::SSL => $ssl))] = TestBotDetectorWrapperApiTransport::class;
        }
        TestBotDetectorWrapperApiTransport::$callback = array($this, 'mockApiRequest');

        $this->requests = array();
        $this->api_response = self::response(json_encode(array('data' => array('wrapper_url' => self::API_WRAPPER_URL))));
        $this->wrapper_response = self::response(self::WRAPPER_BODY);
        add_filter('pre_http_request', array($this, 'mockHttpRequest'), 10, 3);
    }

    protected function tearDown(): void
    {
        global $apbct;

        TestBotDetectorWrapperApiTransport::$callback = null;
        Requests::$transport = $this->previous_api_transports;
        $apbct->settings['wp__use_builtin_http_api'] = $this->previous_http_setting;
        remove_filter('pre_http_request', array($this, 'mockHttpRequest'), 10);
        delete_option(self::OPTION);
    }

    public function mockApiRequest($url, $data, $options): string
    {
        $this->requests[] = array($options['type'], $url, $data);

        $status = $this->api_response['response']['code'];
        $headers = "HTTP/1.1 $status\r\nContent-Type: application/json\r\n";
        if ( $status === 302 ) {
            $headers .= "Location: https://example.test/redirect\r\n";
        }

        return $headers . "\r\n" . $this->api_response['body'];
    }

    public function mockHttpRequest($_response, $args, $url)
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->requests[] = array($args['method'], $url, $query);

        return $this->wrapper_response;
    }

    public function testUpdateStoresVerifiedUrlForTheEffectiveAccessKey(): void
    {
        global $apbct;

        $this->assertSame(self::DEFAULT_URL, BotDetectorService::getWrapperUrl());
        BotDetectorService::updateWrapperUrl();

        $this->assertCount(2, $this->requests);
        $this->assertSame('GET', $this->requests[0][0]);
        $this->assertSame('get_bot_detector_wrapper_url', $this->requests[0][2]['method_name']);
        $this->assertSame(self::TEST_KEY, $this->requests[0][2]['auth_key']);
        $this->assertSame(self::API_WRAPPER_URL, $this->requests[1][1]);
        $this->assertSame(self::API_WRAPPER_URL, BotDetectorService::getWrapperUrl());

        $apbct->api_key = 'another-access-key';
        $this->assertSame(self::DEFAULT_URL, BotDetectorService::getWrapperUrl());
        $this->assertCount(2, $this->requests);
    }

    /** @dataProvider failedRefreshes */
    public function testFailedRefreshFallsBackEvenWhenAnEarlierUrlWasStored(
        string $api_body,
        int $api_status,
        string $wrapper_body,
        int $wrapper_status,
        int $expected_requests
    ): void {
        BotDetectorService::updateWrapperUrl();
        $this->assertSame(self::API_WRAPPER_URL, BotDetectorService::getWrapperUrl());

        $this->api_response = self::response($api_body, $api_status);
        $this->wrapper_response = self::response($wrapper_body, $wrapper_status);
        $this->requests = array();
        BotDetectorService::updateWrapperUrl();

        $this->assertSame(self::DEFAULT_URL, BotDetectorService::getWrapperUrl());
        $this->assertCount($expected_requests, $this->requests);
    }

    public static function failedRefreshes(): array
    {
        $valid_response = json_encode(array('data' => array('wrapper_url' => self::API_WRAPPER_URL)));
        return array(
            'API unavailable' => array($valid_response, 503, self::WRAPPER_BODY, 200, 1),
            'API redirect' => array($valid_response, 302, self::WRAPPER_BODY, 200, 1),
            'invalid JSON' => array('broken JSON', 200, self::WRAPPER_BODY, 200, 1),
            'missing URL' => array('{"data":[]}', 200, self::WRAPPER_BODY, 200, 1),
            'unsupported host' => array(
                '{"data":{"wrapper_url":"https://example.org/bot.js"}}',
                200,
                self::WRAPPER_BODY,
                200,
                1,
            ),
            'untrusted CleanTalk domain suffix' => array(
                '{"data":{"wrapper_url":"https://fd.cleantalk.example/bot.js"}}',
                200,
                self::WRAPPER_BODY,
                200,
                1,
            ),
            'cleantalk.ru returned by the API' => array(
                '{"data":{"wrapper_url":"https://fd.cleantalk.ru/ct-bot-detector-wrapper-ru.js"}}',
                200,
                self::WRAPPER_BODY,
                200,
                1,
            ),
            'wrapper unavailable' => array($valid_response, 200, self::WRAPPER_BODY, 404, 2),
            'unexpected body' => array($valid_response, 200, '<html>Not a wrapper</html>', 200, 2),
        );
    }

    public function testPreviouslyStoredUrlOutsideTrustedDomainsIsIgnored(): void
    {
        update_option(self::OPTION, array(
            'url' => 'https://fd.cleantalk.ru/ct-bot-detector-wrapper-ru.js',
            'key_hash' => hash('sha256', self::TEST_KEY),
        ));

        $this->assertSame(self::DEFAULT_URL, BotDetectorService::getWrapperUrl());
        $this->assertSame(array(), $this->requests);
    }

    public function testSubsiteDelegatesOnlyWhenTheMainSiteCanRefreshItsWrapper(): void
    {
        if ( ! is_multisite() ) {
            $this->markTestSkipped('Requires WordPress Multisite.');
        }

        global $apbct;

        $main_site = get_main_site_id();
        $main_plugins = get_blog_option($main_site, 'active_plugins', array());
        $network_plugins = get_site_option('active_sitewide_plugins', array());
        $allow_custom_key = $apbct->allow_custom_key;
        $white_label = $apbct->white_label;

        remove_action('wp_initialize_site', 'apbct_activation__new_blog', 10);
        try {
            $subsite_id = wpmu_create_blog(
                'wrapper-test.' . wp_parse_url(home_url(), PHP_URL_HOST),
                '/',
                'Bot Detector wrapper',
                1
            );
        } finally {
            add_action('wp_initialize_site', 'apbct_activation__new_blog', 10, 2);
        }
        $this->assertIsInt($subsite_id);

        $main_url = 'https://backup.cleantalk.org/ct-bot-detector-wrapper.js';
        update_blog_option($main_site, self::OPTION, array(
            'url' => $main_url,
            'key_hash' => hash('sha256', self::TEST_KEY),
        ));
        update_blog_option($main_site, 'active_plugins', array());
        update_site_option('active_sitewide_plugins', array());

        switch_to_blog($subsite_id);
        try {
            update_option('active_plugins', array(APBCT_PLUGIN_BASE_NAME));
            $apbct->allow_custom_key = false;
            $apbct->white_label = false;

            $this->assertSame(self::DEFAULT_URL, BotDetectorService::getWrapperUrl());
            BotDetectorService::updateWrapperUrl();
            $this->assertCount(2, $this->requests);
            $this->assertSame(self::API_WRAPPER_URL, BotDetectorService::getWrapperUrl());
            $this->assertSame(self::API_WRAPPER_URL, get_option(self::OPTION)['url']);

            update_blog_option($main_site, 'active_plugins', array(APBCT_PLUGIN_BASE_NAME));
            $this->assertSame($main_url, BotDetectorService::getWrapperUrl());
            BotDetectorService::updateWrapperUrl();
            $this->assertCount(2, $this->requests);

            update_blog_option($main_site, 'active_plugins', array());
            update_site_option('active_sitewide_plugins', array(APBCT_PLUGIN_BASE_NAME => time()));
            $this->assertSame($main_url, BotDetectorService::getWrapperUrl());
            BotDetectorService::updateWrapperUrl();
            $this->assertCount(2, $this->requests);
        } finally {
            restore_current_blog();
            update_blog_option($main_site, 'active_plugins', $main_plugins);
            update_site_option('active_sitewide_plugins', $network_plugins);
            $apbct->allow_custom_key = $allow_custom_key;
            $apbct->white_label = $white_label;
            wp_delete_site($subsite_id);
        }
    }

    public function testFrontendAndIntegrationsUseTheUpdatedUrl(): void
    {
        global $wp_scripts;

        BotDetectorService::updateWrapperUrl();
        $previous_scripts = $wp_scripts;

        try {
            $wp_scripts = new WP_Scripts();
            apbct_enqueue_and_localize_public_scripts();
            $this->assertSame(self::API_WRAPPER_URL, $wp_scripts->registered['ct_bot_detector']->src);

            wp_deregister_script('ct_bot_detector');
            (new GiveWPScript())->integrate();
            $this->assertSame(self::API_WRAPPER_URL, $wp_scripts->registered['ct_bot_detector']->src);

            ob_start();
            apbct_print_public_scripts();
            $booking_html = ob_get_clean();
            $this->assertStringContainsString('src="' . self::API_WRAPPER_URL . '"', $booking_html);

            ob_start();
            (new FluentFormScript())->integrate();
            $form_html = ob_get_clean();
            $this->assertStringContainsString(self::API_WRAPPER_URL . '&#038;ver=' . APBCT_VERSION, $form_html);
        } finally {
            $wp_scripts = $previous_scripts;
        }
    }

    public function testActivationRegistersTheWrapperRefresh(): void
    {
        $previous_tasks = get_option('cleantalk_cron', array());
        $previous_start = get_option('cleantalk_cron_last_start', 0);

        try {
            delete_option('cleantalk_cron');
            update_option('cleantalk_cron_last_start', time());
            Activator::setCronJobs();

            $tasks = get_option('cleantalk_cron');
            $this->assertSame(BotDetectorService::class . '::updateWrapperUrl', $tasks[self::CRON_TASK]['handler']);
            $this->assertSame(3600, $tasks[self::CRON_TASK]['period']);
            $this->assertArrayHasKey('check_account_status', $tasks);
        } finally {
            update_option('cleantalk_cron', $previous_tasks);
            update_option('cleantalk_cron_last_start', $previous_start);
        }
    }

    public function testRegisteringTheRefreshKeepsExistingTasksAndRunsItHourly(): void
    {
        $task = self::CRON_TASK;
        $previous_tasks = get_option('cleantalk_cron', array());
        $previous_start = get_option('cleantalk_cron_last_start', 0);
        $previous_pid = get_option('cleantalk_cron_pid', 0);

        try {
            $other_task = array(
                'handler' => 'ct_send_feedback',
                'next_call' => time() + 3600,
                'period' => 3600,
                'params' => array(),
            );
            update_option('cleantalk_cron', array('send_feedback' => $other_task));
            BotDetectorService::scheduleWrapperUrlUpdate();
            update_option('cleantalk_cron_last_start', 0);

            $cron = Cron::getInstance();
            $due_tasks = $cron->checkTasks();
            $this->assertSame(array($task), $due_tasks);
            $this->assertSame(array($task => true), $cron->runTasks($due_tasks));
            $this->assertSame(self::API_WRAPPER_URL, BotDetectorService::getWrapperUrl());

            $saved = get_option('cleantalk_cron');
            $this->assertSame($other_task['next_call'], $saved['send_feedback']['next_call']);
            $this->assertSame(3600, $saved[$task]['period']);
            $this->assertEqualsWithDelta($saved[$task]['last_call'] + 3600, $saved[$task]['next_call'], 1);

            BotDetectorService::scheduleWrapperUrlUpdate();
            $this->assertSame($saved, get_option('cleantalk_cron'));
        } finally {
            update_option('cleantalk_cron', $previous_tasks);
            update_option('cleantalk_cron_last_start', $previous_start);
            update_option('cleantalk_cron_pid', $previous_pid);
        }
    }

    private static function response(string $body, int $status = 200): array
    {
        return array(
            'body' => $body,
            'response' => array('code' => $status),
        );
    }
}
