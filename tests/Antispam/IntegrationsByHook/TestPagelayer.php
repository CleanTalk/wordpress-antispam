<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Request;
use PHPUnit\Framework\TestCase;

class TestPagelayer extends TestCase
{
    /**
     * Service fields Pagelayer must never receive back in $formdata
     */
    private static $service_fields = array(
        'action',
        'pagelayer_nonce',
        'pagelayer-contact-submit',
        'cfa-pagelayer-id',
        'cfa-post-id',
        'cfa-custom-template',
        'cfa-redirect',
        'apbct_visible_fields',
        'ct_bot_detector_event_token',
        'ct_no_cookie_hidden_field',
    );

    private $post_backup;
    private $get_backup;

    protected function setUp(): void
    {
        $this->post_backup = $_POST;
        $this->get_backup = $_GET;
    }

    protected function tearDown(): void
    {
        $_POST = $this->post_backup;
        $_GET = $this->get_backup;
        $this->resetVariablesCache();
    }

    public function testGetDataForCheckingExcludesServiceFields()
    {
        $this->setPost($this->getContactFormPost());

        $data = (new Pagelayer())->getDataForChecking(1);
        $message = isset($data['message']) && is_array($data['message']) ? $data['message'] : array();

        foreach ( self::$service_fields as $service_field ) {
            $this->assertArrayNotHasKey(
                $service_field,
                $message,
                'Service field "' . $service_field . '" must not be sent for checking'
            );
        }
    }

    public function testGetDataForCheckingExcludesServiceValues()
    {
        $this->setPost($this->getContactFormPost());

        $encoded = json_encode((new Pagelayer())->getDataForChecking(1));

        $this->assertStringNotContainsString('https://example.com/thank-you/', $encoded);
        $this->assertStringNotContainsString('pagelayer_nonce_value', $encoded);
        $this->assertStringNotContainsString('test_vfields', $encoded);
    }

    public function testGetDataForCheckingKeepsUserData()
    {
        $this->setPost($this->getContactFormPost());

        $data = (new Pagelayer())->getDataForChecking(1);

        $this->assertSame('john@example.com', $data['email']);
        $this->assertStringContainsString('Please call me back', json_encode($data['message']));
    }

    public function testGetDataForCheckingPassesEventToken()
    {
        $token = str_repeat('a', 64);
        $post = $this->getContactFormPost();
        $post['ct_bot_detector_event_token'] = $token;
        $this->setPost($post);

        $data = (new Pagelayer())->getDataForChecking(1);

        $this->assertSame($token, $data['event_token']);
    }

    /**
     * Pagelayer stops on empty($continue), so the allowed value has to stay truthy
     */
    public function testAllowLetsPagelayerContinue()
    {
        $this->assertNotEmpty((new Pagelayer())->allow());
    }

    public function testDoFinalActionsClearsServiceFieldsFromPost()
    {
        $this->setPost($this->getContactFormPost());

        $returned = (new Pagelayer())->doFinalActions(1);

        $this->assertSame(1, $returned, 'The filtered value must be passed through untouched');
        $this->assertArrayNotHasKey('ct_bot_detector_event_token', $_POST);
        $this->assertArrayNotHasKey('apbct_visible_fields', $_POST);
        $this->assertArrayNotHasKey('ct_no_cookie_hidden_field', $_POST);
        $this->assertArrayHasKey('email', $_POST, 'User data must stay in $_POST for Pagelayer');
    }

    public function testIntegrationIsRegisteredOnPagelayerFilter()
    {
        $integrations = $this->loadIntegrationsRegistry();

        $this->assertArrayHasKey('Pagelayer', $integrations);
        $this->assertSame(
            'pagelayer_contact_submit_start',
            $integrations['Pagelayer']['hook'],
            'The filter runs before Pagelayer copies $_POST into $formdata'
        );
        $this->assertFalse(
            $integrations['Pagelayer']['ajax'],
            'The hook is a filter, not a wp_ajax_ action'
        );
    }

    /**
     * The registry file declares $apbct_active_integrations at its own scope
     *
     * @return array
     */
    private function loadIntegrationsRegistry()
    {
        include CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-integrations-by-hook.php';

        return $apbct_active_integrations;
    }

    /**
     * Pagelayer posts to admin-ajax.php?action=pagelayer_contact_submit, so the action is GET only
     */
    public function testSkipRequestDetectsActionPassedViaGet()
    {
        $post = $this->getContactFormPost();
        unset($post['action']);
        $this->setPost($post);
        $_GET['action'] = 'pagelayer_contact_submit';
        $this->resetVariablesCache();

        $result = $this->callSkipRequestWithPagelayerActive();

        $this->assertSame('Pagelayer contact form - has the direct integration', $result);
    }

    public function testSkipRequestIgnoresForeignAction()
    {
        $_POST = array();
        $_GET['action'] = 'some_other_action';
        $this->resetVariablesCache();

        $this->assertFalse($this->callSkipRequestWithPagelayerActive());
    }

    private function callSkipRequestWithPagelayerActive()
    {
        $active_plugins = static function () {
            return array('pagelayer/pagelayer.php');
        };

        add_filter('pre_option_active_plugins', $active_plugins);
        $result = apbct_is_skip_request(false);
        remove_filter('pre_option_active_plugins', $active_plugins);

        return $result;
    }

    private function getContactFormPost()
    {
        return array(
            'name' => 'John',
            'email' => 'john@example.com',
            'Subject' => 'Lawn care',
            'message' => 'Please call me back',
            'action' => 'pagelayer_contact_submit',
            'pagelayer_nonce' => 'pagelayer_nonce_value',
            'pagelayer-contact-submit' => 'Submit',
            'cfa-pagelayer-id' => 'mwt2449',
            'cfa-post-id' => '43',
            'cfa-custom-template' => 'true',
            'cfa-redirect' => 'https://example.com/thank-you/',
            'apbct_visible_fields' => 'test_vfields',
            'ct_bot_detector_event_token' => str_repeat('a', 64),
            'ct_no_cookie_hidden_field' => '_ct_no_cookie_data_test',
        );
    }

    private function setPost(array $post)
    {
        $_POST = $post;
        $this->resetVariablesCache();
    }

    /**
     * Post/Get/Request keep every read value in the singleton for the whole process
     */
    private function resetVariablesCache()
    {
        Post::getInstance()->variables = array();
        Get::getInstance()->variables = array();
        Request::getInstance()->variables = array();
    }
}
