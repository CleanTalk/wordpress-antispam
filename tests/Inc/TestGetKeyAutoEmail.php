<?php

namespace Inc;

use Cleantalk\ApbctWP\AJAXService;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Post;

class TestGetKeyAutoEmail extends \WP_UnitTestCase
{
    private $original_apbct;
    private $original_post;
    private $original_request;
    private $original_post_cache;
    private $original_email_filter;
    private $nonce_checks;
    private $admin_id;

    public function set_up()
    {
        parent::set_up();
        require_once CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-settings.php';

        global $apbct, $wp_filter;
        $this->original_apbct = $apbct;
        $this->original_post = $_POST;
        $this->original_request = $_REQUEST;
        $this->original_post_cache = Post::getInstance()->variables;
        $this->original_email_filter = isset($wp_filter['apbct_get_api_key_email'])
            ? clone $wp_filter['apbct_get_api_key_email']
            : null;

        remove_all_filters('apbct_get_api_key_email');
        $_POST = array();
        $_REQUEST = array();
        Post::getInstance()->variables = array();

        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $apbct->data['account_email'] = '';
        $apbct->network_settings['multisite__work_mode'] = 1;
        $apbct->network_settings['multisite__white_label'] = 1;
        $apbct->network_settings['multisite__hoster_api_key'] = 'test-hoster-key';

        update_option('admin_email', 'site-admin@example.org');
        if ( is_multisite() ) {
            update_site_option('admin_email', 'network-admin@example.org');
        }

        $this->admin_id = self::factory()->user->create(array('role' => 'administrator'));
        if ( is_multisite() ) {
            grant_super_admin($this->admin_id);
        }
        wp_set_current_user($this->admin_id);

        $this->nonce_checks = array();
        add_action('check_ajax_referer', array($this, 'recordNonceCheck'), 10, 2);
    }

    public function tear_down()
    {
        global $apbct, $wp_filter;

        remove_action('check_ajax_referer', array($this, 'recordNonceCheck'), 10);
        if ( $this->original_email_filter !== null ) {
            $wp_filter['apbct_get_api_key_email'] = $this->original_email_filter;
        } else {
            unset($wp_filter['apbct_get_api_key_email']);
        }
        if ( is_multisite() ) {
            revoke_super_admin($this->admin_id);
        }
        wp_set_current_user(0);

        $apbct = $this->original_apbct;
        $_POST = $this->original_post;
        $_REQUEST = $this->original_request;
        Post::getInstance()->variables = $this->original_post_cache;

        parent::tear_down();
    }

    /**
     * @dataProvider directRequestProvider
     */
    public function testDirectCallIgnoresRequestEmail($post)
    {
        $expected = is_multisite() ? 'network-admin@example.org' : 'site-admin@example.org';

        $this->assertSame($expected, $this->captureEmail(true, $post));
        $this->assertSame(array(), $this->nonce_checks);
    }

    public function directRequestProvider()
    {
        return array(
            'no request body' => array(array()),
            'forged email' => array(array('email' => 'attacker@example.org')),
            'forged wizard request' => array(array(
                'email' => 'attacker@example.org',
                'apbct_wizard_request' => '1',
                '_ajax_nonce' => 'invalid',
            )),
            'array email' => array(array('email' => array('attacker@example.org'))),
        );
    }

    public function testDirectCallPreservesAccountEmail()
    {
        global $apbct;
        $apbct->data['account_email'] = 'account@example.org';

        $this->assertSame(
            'account@example.org',
            $this->captureEmail(true, array('email' => 'attacker@example.org'))
        );
    }

    public function testDirectCallPreservesEmailFilter()
    {
        add_filter('apbct_get_api_key_email', function () {
            return 'filtered@example.org';
        });

        $this->assertSame(
            'filtered@example.org',
            $this->captureEmail(true, array('email' => 'attacker@example.org'))
        );
    }

    public function testAjaxCallPreservesSelectedEmailAfterNonceCheck()
    {
        $this->assertSame(
            'selected@example.org',
            $this->captureEmail(false, array(
                'email' => 'selected@example.org',
                '_ajax_nonce' => wp_create_nonce(AJAXService::$admin_nonce_id),
            ))
        );
        $this->assertCount(1, $this->nonce_checks);
        $this->assertNotFalse($this->nonce_checks[0]);
    }

    public function testAjaxCallWithoutEmailFallsBackToTrustedEmail()
    {
        $expected = is_multisite() ? 'network-admin@example.org' : 'site-admin@example.org';

        $this->assertSame(
            $expected,
            $this->captureEmail(false, array(
                '_ajax_nonce' => wp_create_nonce(AJAXService::$admin_nonce_id),
            ))
        );
        $this->assertCount(1, $this->nonce_checks);
    }

    /**
     * @dataProvider rejectedNonceProvider
     */
    public function testAjaxCallRejectsEmailWithoutValidNonce($nonce)
    {
        $post = array('email' => 'attacker@example.org');
        if ( $nonce !== null ) {
            $post['_ajax_nonce'] = $nonce;
        }

        $this->expectException(GetKeyAutoNonceRejected::class);
        $this->captureEmail(false, $post);
    }

    public function rejectedNonceProvider()
    {
        return array(
            'missing nonce' => array(null),
            'invalid nonce' => array('invalid'),
        );
    }

    /**
     * @dataProvider multisiteEmailProvider
     */
    public function testDirectCallUsesConfiguredMultisiteEmail($work_mode, $expected)
    {
        if ( ! is_multisite() ) {
            $this->markTestSkipped('Requires WordPress Multisite.');
        }

        global $apbct;
        $blog_id = self::factory()->blog->create(array('user_id' => $this->admin_id));
        switch_to_blog($blog_id);
        try {
            update_option('admin_email', 'subsite-admin@example.org');
            $apbct->network_settings['multisite__work_mode'] = $work_mode;

            $this->assertSame(
                $expected,
                $this->captureEmail(true, array('email' => 'attacker@example.org'))
            );
        } finally {
            restore_current_blog();
        }
    }

    public function multisiteEmailProvider()
    {
        return array(
            'shared account' => array(1, 'network-admin@example.org'),
            'individual account' => array(3, 'subsite-admin@example.org'),
        );
    }

    public function recordNonceCheck($action, $result)
    {
        if ( $action !== AJAXService::$admin_nonce_id ) {
            return;
        }
        $this->nonce_checks[] = $result;
        if ( $result === false ) {
            throw new GetKeyAutoNonceRejected();
        }
    }

    private function captureEmail($direct_call, $post)
    {
        $_POST = $post;
        $_REQUEST = $post;
        Post::getInstance()->variables = array();

        // Stop at the final email filter so these tests cannot contact the API or save a key.
        $capture = function ($email) {
            throw new GetKeyAutoEmailCaptured($email);
        };
        add_filter('apbct_get_api_key_email', $capture, PHP_INT_MAX);
        try {
            apbct_settings__get_key_auto($direct_call);
        } catch (GetKeyAutoEmailCaptured $result) {
            return $result->getMessage();
        } finally {
            remove_filter('apbct_get_api_key_email', $capture, PHP_INT_MAX);
        }

        $this->fail('The API key request did not reach email selection.');
    }
}

class GetKeyAutoEmailCaptured extends \RuntimeException
{
}

class GetKeyAutoNonceRejected extends \RuntimeException
{
}
