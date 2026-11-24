<?php

use Cleantalk\Antispam\Integrations\CleantalkExternalForms;
use Cleantalk\Antispam\Integrations\CleantalkExternalFormsBuffer;
use Cleantalk\Antispam\Integrations\CleantalkExternalFormsForceAjax;
use Cleantalk\ApbctWP\ExternalForms\Submission;
use Cleantalk\ApbctWP\ExternalForms\BufferFormSecurity;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;
use PHPUnit\Framework\TestCase;

class TestExternalFormFlows extends TestCase
{
    private $post_global;
    private $server_global;

    protected function setUp(): void
    {
        $this->post_global = $_POST;
        $this->server_global = $_SERVER;
        $_POST = array();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['HTTP_ORIGIN']);
        $_SERVER['HTTP_REFERER'] = get_home_url();
        $this->resetVariableCaches();
    }

    protected function tearDown(): void
    {
        $_POST = $this->post_global;
        $_SERVER = $this->server_global;
        $this->resetVariableCaches();
    }

    private function resetVariableCaches()
    {
        Post::getInstance()->variables = array();
        Server::getInstance()->variables = array();
    }

    /**
     * @dataProvider relayFlowProvider
     */
    public function testRelayOnlyAcceptsItsOwnFields($class, $action_field, $method_field, $other_class)
    {
        $_POST = array(
            $action_field => 'https://external.example/submit',
            $method_field => 'get',
        );
        $this->signBufferSubmission();
        $this->resetVariableCaches();

        $integration = new $class();
        $other = new $other_class();
        $this->assertTrue($integration->doPrepareActions(null));
        $this->assertFalse($other->doPrepareActions(null));
        $this->assertNull($other->getDataForChecking(null));
    }

    /**
     * @dataProvider relayFlowProvider
     */
    public function testRelayRemovesItsMetadataBeforeCheckingAndRendering($class, $action_field, $method_field)
    {
        $_POST = array(
            $action_field => 'https://external.example/submit',
            $method_field => 'get',
            'email' => 'member@example.org',
            'message' => 'External form test',
        );
        $this->signBufferSubmission();
        $this->resetVariableCaches();
        $checked_post = null;
        $filter = static function ($post) use (&$checked_post) {
            $checked_post = $post;
            return $post;
        };
        add_filter('apbct__filter_post', $filter);

        try {
            $integration = new $class();
            $this->assertIsArray($integration->getDataForChecking(null));
        } finally {
            remove_filter('apbct__filter_post', $filter);
        }

        $this->assertSame(
            array('email' => 'member@example.org', 'message' => 'External form test'),
            $checked_post
        );
        $html = $integration->getExternalForm('https://external.example/submit', 'get');
        $this->assertStringContainsString('<form method="GET" action="https://external.example/submit">', $html);
        $this->assertStringContainsString('form.submit();', $html);
        $this->assertStringNotContainsString($action_field, $html);
        $this->assertStringNotContainsString($method_field, $html);
        $this->assertStringNotContainsString(Submission::BUFFER_SIGNATURE, $html);
    }

    public function relayFlowProvider()
    {
        return array(
            'JS and legacy buffer' => array(
                CleantalkExternalForms::class,
                Submission::JS_ACTION,
                Submission::JS_METHOD,
                CleantalkExternalFormsBuffer::class,
            ),
            'buffer' => array(
                CleantalkExternalFormsBuffer::class,
                Submission::BUFFER_ACTION,
                Submission::BUFFER_METHOD,
                CleantalkExternalForms::class,
            ),
        );
    }

    private function signBufferSubmission()
    {
        if ( isset($_POST[Submission::BUFFER_ACTION], $_POST[Submission::BUFFER_METHOD]) ) {
            $_POST[Submission::BUFFER_SIGNATURE] = BufferFormSecurity::getSignature(
                $_POST[Submission::BUFFER_ACTION],
                $_POST[Submission::BUFFER_METHOD]
            );
        }
    }

    public function testLegacyRelayAcceptsItsOwnOriginWithoutASignature()
    {
        $home_filter = static function () {
            return 'https://site.example/subdir';
        };
        add_filter('home_url', $home_filter);
        $_SERVER['HTTP_ORIGIN'] = 'https://site.example';
        $_POST = array(
            Submission::JS_ACTION => 'https://external.example/submit',
            Submission::JS_METHOD => 'post',
            'email' => 'member@example.org',
        );
        $this->resetVariableCaches();

        try {
            $integration = new CleantalkExternalForms();
            $this->assertTrue($integration->doPrepareActions(null));
            $this->assertIsArray($integration->getDataForChecking(null));
        } finally {
            remove_filter('home_url', $home_filter);
        }
    }

    /**
     * @dataProvider untrustedLegacySubmissionProvider
     */
    public function testLegacyRelayRejectsUntrustedSourcesBeforeChecking($headers, $fields)
    {
        unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_REFERER']);
        $_SERVER = array_merge($_SERVER, $headers);
        $_POST = $fields;
        $this->resetVariableCaches();
        $original_post = $_POST;
        $response = null;
        $checked = false;
        $handler = static function () use (&$response) {
            return static function ($message, $title, $args) use (&$response) {
                $response = array('message' => $message, 'status' => $args['response']);
                throw new RuntimeException('External form response completed.');
            };
        };
        $filter = static function ($post) use (&$checked) {
            $checked = true;
            return $post;
        };
        add_filter('wp_die_handler', $handler);
        add_filter('apbct__filter_post', $filter);

        try {
            $integration = new CleantalkExternalForms();
            foreach ( array('doPrepareActions', 'getDataForChecking') as $method ) {
                $response = null;
                try {
                    $integration->$method(null);
                    $this->fail('An untrusted legacy submission must terminate.');
                } catch (RuntimeException $exception) {
                    $this->assertSame('External form response completed.', $exception->getMessage());
                }
                $this->assertSame(403, $response['status']);
                $this->assertNotEmpty($response['message']);
                $this->assertStringContainsString('CT05', $response['message']);
                $this->assertFalse($checked);
                $this->assertSame($original_post, $_POST);
            }
        } finally {
            remove_filter('wp_die_handler', $handler);
            remove_filter('apbct__filter_post', $filter);
        }
    }

    public function untrustedLegacySubmissionProvider()
    {
        $fields = array(
            Submission::JS_ACTION => 'https://external.example/submit',
            Submission::JS_METHOD => 'post',
            'email' => 'member@example.org',
        );

        return array(
            'foreign origin with trusted referer' => array(array(
                'HTTP_ORIGIN' => 'https://attacker.example',
                'HTTP_REFERER' => get_home_url(),
            ), $fields),
            'missing source headers' => array(array(), $fields),
            'action only with null value' => array(array(), array(Submission::JS_ACTION => null)),
            'method only with empty value' => array(array(), array(Submission::JS_METHOD => '')),
        );
    }

    public function testDirectAjaxRemainsSeparateFromBothRelays()
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://attacker.example';
        $_POST = array(
            'action' => Submission::AJAX_ACTION,
            'email' => 'member@example.org',
            'message' => 'External form test',
        );
        $this->resetVariableCaches();

        $this->assertFalse((new CleantalkExternalForms())->doPrepareActions(null));
        $this->assertFalse((new CleantalkExternalFormsBuffer())->doPrepareActions(null));
        $integration = new CleantalkExternalFormsForceAjax();
        $this->assertTrue($integration->doPrepareActions(null));
        $this->assertIsArray($integration->getDataForChecking(null));
        $this->assertArrayNotHasKey('action', $_POST);
    }

    public function testMixedRelaysReturnAnHtmlErrorWithoutChangingPost()
    {
        $_POST = array(
            Submission::JS_ACTION => '',
            Submission::BUFFER_METHOD => null,
        );
        $original_post = $_POST;
        $response = null;
        $handler = static function () use (&$response) {
            return static function ($message, $title, $args) use (&$response) {
                $response = array('message' => $message, 'status' => $args['response']);
                throw new RuntimeException('External form response completed.');
            };
        };
        add_filter('wp_die_handler', $handler);

        try {
            foreach ( array(new CleantalkExternalForms(), new CleantalkExternalFormsBuffer()) as $integration ) {
                foreach ( array('doPrepareActions', 'getDataForChecking') as $method ) {
                    $response = null;
                    try {
                        $integration->$method(null);
                        $this->fail('A mixed relay submission must terminate.');
                    } catch (RuntimeException $exception) {
                        $this->assertSame('External form response completed.', $exception->getMessage());
                    }
                    $this->assertSame(400, $response['status']);
                    $this->assertNotEmpty($response['message']);
                    $this->assertSame($original_post, $_POST);
                }
            }
        } finally {
            remove_filter('wp_die_handler', $handler);
        }
    }

    /**
     * @dataProvider ajaxRelayFieldProvider
     */
    public function testMixedAjaxAndRelayReturnAJsonError($class, $relay_field)
    {
        $_POST = array(
            'action' => Submission::AJAX_ACTION,
            $relay_field => '',
        );
        $original_post = $_POST;
        $handler = static function () {
            return static function () {
                throw new RuntimeException('External form response completed.');
            };
        };
        add_filter('wp_doing_ajax', '__return_true');
        add_filter('wp_die_ajax_handler', $handler);
        ob_start();

        try {
            try {
                (new $class())->doPrepareActions(null);
                $this->fail('A mixed AJAX request must terminate.');
            } catch (RuntimeException $exception) {
                $this->assertSame('External form response completed.', $exception->getMessage());
            }
            $response = json_decode(ob_get_contents(), true);
            $this->assertTrue($response['apbct']['blocked']);
            $this->assertNotEmpty($response['apbct']['comment']);
            $this->assertSame($original_post, $_POST);
        } finally {
            ob_end_clean();
            remove_filter('wp_die_ajax_handler', $handler);
            remove_filter('wp_doing_ajax', '__return_true');
        }
    }

    public function ajaxRelayFieldProvider()
    {
        return array(
            'JS init and AJAX' => array(CleantalkExternalForms::class, Submission::JS_ACTION),
            'buffer init and AJAX' => array(CleantalkExternalFormsBuffer::class, Submission::BUFFER_METHOD),
            'AJAX and JS' => array(CleantalkExternalFormsForceAjax::class, Submission::JS_ACTION),
            'AJAX and buffer' => array(CleantalkExternalFormsForceAjax::class, Submission::BUFFER_METHOD),
            'AJAX and signature only' => array(CleantalkExternalFormsForceAjax::class, Submission::BUFFER_SIGNATURE),
        );
    }
}
