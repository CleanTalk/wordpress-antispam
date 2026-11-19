<?php

use Cleantalk\Antispam\ScriptsIntegration\FluentFormScript;
use Cleantalk\Antispam\ScriptsIntegration\GiveWPScript;
use Cleantalk\ApbctWP\BotDetectorService;
use Cleantalk\ApbctWP\Cron;

class TestBotDetectorWrapper extends ApbctTestCase
{
    private const TEST_KEY = 'apbct-wrapper-test';
    private const OPTION = 'cleantalk_bot_detector_wrapper_url';
    private const DEFAULT_URL = 'https://fd.cleantalk.org/ct-bot-detector-wrapper.js';
    private const REGIONAL_URL = 'https://fd.cleantalk.ru/ct-bot-detector-wrapper-ru.js?channel=stable';
    private const WRAPPER_BODY = 'const script = document.createElement("script");'
        . 'script.src = "https://fd.cleantalk.ru/2.1/ct-bot-detector.min.js";';

    private $api_response;
    private $wrapper_response;
    private $requests;

    protected function setUp(): void
    {
        global $apbct;

        $apbct->api_key = self::TEST_KEY;
        $apbct->data['bot_detector_enabled'] = 1;
        delete_option(self::OPTION);

        $this->requests = array();
        $this->api_response = self::response(json_encode(array('data' => array('wrapper_url' => self::REGIONAL_URL))));
        $this->wrapper_response = self::response(self::WRAPPER_BODY);
        add_filter('pre_http_request', array($this, 'mockHttpRequest'), 10, 3);
    }

    protected function tearDown(): void
    {
        remove_filter('pre_http_request', array($this, 'mockHttpRequest'), 10);
        delete_option(self::OPTION);
    }

    public function mockHttpRequest($_response, $args, $url)
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->requests[] = array($args['method'], $url, $query);

        return isset($query['method_name']) ? $this->api_response : $this->wrapper_response;
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
        $this->assertSame(self::REGIONAL_URL, $this->requests[1][1]);
        $this->assertSame(self::REGIONAL_URL, BotDetectorService::getWrapperUrl());

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
        $this->assertSame(self::REGIONAL_URL, BotDetectorService::getWrapperUrl());

        $this->api_response = self::response($api_body, $api_status);
        $this->wrapper_response = self::response($wrapper_body, $wrapper_status);
        $this->requests = array();
        BotDetectorService::updateWrapperUrl();

        $this->assertSame(self::DEFAULT_URL, BotDetectorService::getWrapperUrl());
        $this->assertCount($expected_requests, $this->requests);
    }

    public static function failedRefreshes(): array
    {
        $regional = json_encode(array('data' => array('wrapper_url' => self::REGIONAL_URL)));
        return array(
            'API unavailable' => array($regional, 503, self::WRAPPER_BODY, 200, 1),
            'invalid JSON' => array('broken JSON', 200, self::WRAPPER_BODY, 200, 1),
            'missing URL' => array('{"data":[]}', 200, self::WRAPPER_BODY, 200, 1),
            'unsupported host' => array(
                '{"data":{"wrapper_url":"https://example.org/bot.js"}}',
                200,
                self::WRAPPER_BODY,
                200,
                1,
            ),
            'wrapper unavailable' => array($regional, 200, self::WRAPPER_BODY, 404, 2),
            'unexpected body' => array($regional, 200, '<html>Not a wrapper</html>', 200, 2),
        );
    }

    public function testFrontendAndIntegrationsUseTheUpdatedUrl(): void
    {
        global $wp_scripts;

        BotDetectorService::updateWrapperUrl();
        $previous_scripts = $wp_scripts;

        try {
            $wp_scripts = new WP_Scripts();
            apbct_enqueue_and_localize_public_scripts();
            $this->assertSame(self::REGIONAL_URL, $wp_scripts->registered['ct_bot_detector']->src);

            wp_deregister_script('ct_bot_detector');
            (new GiveWPScript())->integrate();
            $this->assertSame(self::REGIONAL_URL, $wp_scripts->registered['ct_bot_detector']->src);

            ob_start();
            apbct_print_public_scripts();
            $booking_html = ob_get_clean();
            $this->assertStringContainsString('src="' . self::REGIONAL_URL . '"', $booking_html);

            ob_start();
            (new FluentFormScript())->integrate();
            $form_html = ob_get_clean();
            $this->assertStringContainsString(self::REGIONAL_URL . '&#038;ver=' . APBCT_VERSION, $form_html);
        } finally {
            $wp_scripts = $previous_scripts;
        }
    }

    public function testCronAddsRefreshToTheExistingScheduleAndRunsItHourly(): void
    {
        $task = 'get_bot_detector_wrapper_url';
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
            update_option('cleantalk_cron_last_start', 0);

            $cron = Cron::getInstance();
            $due_tasks = $cron->checkTasks();
            $this->assertSame(array($task), $due_tasks);
            $this->assertSame(array($task => true), $cron->runTasks($due_tasks));
            $this->assertSame(self::REGIONAL_URL, BotDetectorService::getWrapperUrl());

            $saved = get_option('cleantalk_cron');
            $this->assertSame($other_task['next_call'], $saved['send_feedback']['next_call']);
            $this->assertSame(3600, $saved[$task]['period']);
            $this->assertEqualsWithDelta($saved[$task]['last_call'] + 3600, $saved[$task]['next_call'], 1);
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
