<?php

use Cleantalk\Antispam\Integrations\CleantalkExternalFormsBuffer;
use Cleantalk\ApbctWP\ExternalForms\BufferFormSecurity;
use Cleantalk\ApbctWP\ExternalForms\Submission;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;
use PHPUnit\Framework\TestCase;

class TestCleantalkExternalFormsBuffer extends TestCase
{
    private $post_global;
    private $server_global;
    private $home_filter;

    protected function setUp(): void
    {
        $this->post_global = $_POST;
        $this->server_global = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_ORIGIN'] = 'https://site.example';
        unset($_SERVER['HTTP_REFERER']);
        $this->home_filter = static function () {
            return 'https://site.example/subdir';
        };
        add_filter('home_url', $this->home_filter);
        $this->setSignedPost();
    }

    protected function tearDown(): void
    {
        remove_filter('home_url', $this->home_filter);
        $_POST = $this->post_global;
        $_SERVER = $this->server_global;
        $this->resetVariableCaches();
    }

    private function resetVariableCaches()
    {
        Post::getInstance()->variables = array();
        Server::getInstance()->variables = array();
    }

    private function setSignedPost($action = 'https://external.example/submit', $method = 'post')
    {
        $_POST = wp_slash(array(
            Submission::BUFFER_ACTION => $action,
            Submission::BUFFER_METHOD => $method,
            Submission::BUFFER_SIGNATURE => BufferFormSecurity::getSignature($action, $method),
            'email' => 'member@example.org',
            'message' => 'Buffer form test',
        ));
        $this->resetVariableCaches();
    }

    public function testValidSubmissionPreservesUrlBytesAndRemovesSecurityMetadata()
    {
        $action = "https://external.example/submit?q=first%20last&next=%2Fpath&name=O'Brien";
        $this->setSignedPost($action, 'GET');
        // A prior sanitized read must not change the bytes used for HMAC validation.
        Post::getString(Submission::BUFFER_ACTION);
        $integration = new CleantalkExternalFormsBuffer();

        $this->assertTrue($integration->doPrepareActions(null));
        $this->assertIsArray($integration->getDataForChecking(null));
        $this->assertSame(
            array('email' => 'member@example.org', 'message' => 'Buffer form test'),
            $_POST
        );
        $html = $integration->getExternalForm($action, 'GET');
        $this->assertStringContainsString('action="' . esc_url($action, array('http', 'https')) . '"', $html);
        $this->assertStringNotContainsString('cleantalk_buffer_', $html);
    }

    /**
     * @dataProvider invalidMetadataProvider
     */
    public function testInvalidMetadataIsRejectedBeforeChecking($changes, $expected_code)
    {
        foreach ( $changes as $field => $value ) {
            if ( $value === null ) {
                unset($_POST[$field]);
            } else {
                $_POST[$field] = wp_slash($value);
            }
        }
        $this->resetVariableCaches();

        $this->assertSubmissionRejected($expected_code);
    }

    public function invalidMetadataProvider()
    {
        return array(
            'missing signature' => array(array(Submission::BUFFER_SIGNATURE => null), 'CT03'),
            'incorrect signature' => array(array(Submission::BUFFER_SIGNATURE => str_repeat('0', 64)), 'CT03'),
            'array signature' => array(array(Submission::BUFFER_SIGNATURE => array('invalid')), 'CT03'),
            'changed destination' => array(array(Submission::BUFFER_ACTION => 'https://other.example/submit'), 'CT03'),
            'changed method' => array(array(Submission::BUFFER_METHOD => 'get'), 'CT03'),
            'signature only' => array(
                array(Submission::BUFFER_ACTION => null, Submission::BUFFER_METHOD => null),
                'CT01'
            ),
            'invalid action' => array(array(Submission::BUFFER_ACTION => 'not-a-url'), 'CT01'),
            'invalid method' => array(array(Submission::BUFFER_METHOD => 'put'), 'CT02'),
        );
    }

    public function testNonPostBufferSubmissionIsRejectedWithRequestMethodCode()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->resetVariableCaches();

        $this->assertSubmissionRejected('CT00');
    }

    public function testAValidSignatureDoesNotAllowAForeignSource()
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://attacker.example';
        $_SERVER['HTTP_REFERER'] = 'https://site.example/subdir/form';
        $this->resetVariableCaches();

        $this->assertSubmissionRejected('CT04');
    }

    private function assertSubmissionRejected($expected_code)
    {
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
            $integration = new CleantalkExternalFormsBuffer();
            foreach ( array('doPrepareActions', 'getDataForChecking') as $method ) {
                $response = null;
                try {
                    $integration->$method(null);
                    $this->fail('An invalid buffer submission must terminate.');
                } catch (RuntimeException $exception) {
                    $this->assertSame('External form response completed.', $exception->getMessage());
                }
                $this->assertSame(403, $response['status']);
                $this->assertNotEmpty($response['message']);
                $this->assertStringContainsString($expected_code, $response['message']);
                $this->assertFalse($checked);
                $this->assertSame($original_post, $_POST);
            }
        } finally {
            remove_filter('wp_die_handler', $handler);
            remove_filter('apbct__filter_post', $filter);
        }
    }

    /**
     * @dataProvider sourceProvider
     */
    public function testSourceMustMatchTheConfiguredOrigin($headers, $expected)
    {
        unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_REFERER']);
        $_SERVER = array_merge($_SERVER, $headers);
        $this->resetVariableCaches();

        $this->assertSame($expected, BufferFormSecurity::hasTrustedSource());
    }

    public function sourceProvider()
    {
        return array(
            'same origin' => array(array('HTTP_ORIGIN' => 'https://site.example'), true),
            'case and default port' => array(array('HTTP_ORIGIN' => 'https://SITE.EXAMPLE:443'), true),
            'referer fallback' => array(array('HTTP_REFERER' => 'https://site.example/subdir/form?x=1'), true),
            'different scheme' => array(array('HTTP_ORIGIN' => 'http://site.example'), false),
            'different port' => array(array('HTTP_ORIGIN' => 'https://site.example:444'), false),
            'same-site subdomain' => array(array('HTTP_ORIGIN' => 'https://forms.site.example'), false),
            'null origin with trusted referer' => array(array(
                'HTTP_ORIGIN' => 'null',
                'HTTP_REFERER' => 'https://site.example/subdir/form',
            ), false),
            'empty origin with trusted referer' => array(array(
                'HTTP_ORIGIN' => '',
                'HTTP_REFERER' => 'https://site.example/subdir/form',
            ), false),
            'null-valued origin with trusted referer' => array(array(
                'HTTP_ORIGIN' => null,
                'HTTP_REFERER' => 'https://site.example/subdir/form',
            ), false),
            'foreign referer' => array(array('HTTP_REFERER' => 'https://attacker.example/form'), false),
            'missing headers' => array(array(), false),
            'origin with path' => array(array('HTTP_ORIGIN' => 'https://site.example/subdir'), false),
            'referer credentials' => array(array('HTTP_REFERER' => 'https://user@site.example/form'), false),
            'array origin' => array(array('HTTP_ORIGIN' => array('https://site.example')), false),
        );
    }

    public function testSignatureIsBoundToTheCurrentSite()
    {
        $signature = $_POST[Submission::BUFFER_SIGNATURE];
        $other_home = static function () {
            return 'https://other-site.example';
        };
        add_filter('home_url', $other_home, 11);

        try {
            $this->assertFalse(BufferFormSecurity::hasValidSignature(
                $_POST[Submission::BUFFER_ACTION],
                $_POST[Submission::BUFFER_METHOD],
                $signature
            ));
        } finally {
            remove_filter('home_url', $other_home, 11);
        }
    }
}
