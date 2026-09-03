<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests that admin antibot cookie is not a hosting-license RemoteCalls token.
 */
class TestAdminAntiBotCookie extends TestCase
{
    private $apbctBackup;

    protected function setUp(): void
    {
        global $apbct;

        $this->apbctBackup = $apbct;

        $apbct = (object) array(
            'api_key' => '',
            'data'    => array(
                'salt'        => 'hosting_salt_for_admin_cookie_test',
                'moderate_ip' => 1,
                'ip_license'  => 1,
                'key_is_ok'   => 1,
            ),
        );

        require_once CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-admin.php';
    }

    protected function tearDown(): void
    {
        global $apbct;
        $apbct = $this->apbctBackup;
    }

    public function testAdminCookieIsNotRemoteCallToken()
    {
        global $apbct;

        ob_start();
        apbct_admin_set_cookie_for_anti_bot();
        $output = ob_get_clean();

        $antibot_hash = apbct_get_anti_bot_cookie_hash($apbct->api_key, $apbct->data['salt']);
        $rc_token     = hash('sha256', $apbct->api_key . $apbct->data['salt']);

        $this->assertStringContainsString('apbct_antibot=', $output);
        $this->assertStringContainsString($antibot_hash, $output);
        $this->assertStringNotContainsString(
            $rc_token,
            $output,
            'Admin antibot cookie must not expose hosting-license RemoteCalls token'
        );
    }

    public function testSubscriberReceivingAdminCookieStillCannotUseItAsRemoteCallToken()
    {
        global $apbct;

        require_once ABSPATH . 'wp-admin/includes/user.php';

        $login   = 'apbct_sub_' . wp_generate_password(6, false);
        $user_id = wp_create_user($login, 'password', $login . '@example.com');
        $this->assertIsInt($user_id);

        $user = new WP_User($user_id);
        $user->set_role('subscriber');
        wp_set_current_user($user_id);

        try {
            $this->assertFalse(current_user_can('manage_options'));

            ob_start();
            apbct_admin_set_cookie_for_anti_bot();
            $output = ob_get_clean();

            $antibot_hash = apbct_get_anti_bot_cookie_hash($apbct->api_key, $apbct->data['salt']);

            $this->assertStringContainsString($antibot_hash, $output);

            $method = new ReflectionMethod(\Cleantalk\ApbctWP\RemoteCalls::class, 'checkToken');
            $method->setAccessible(true);

            $this->assertFalse(
                $method->invoke(null, strtolower($antibot_hash)),
                'Subscriber antibot cookie must not validate as RemoteCalls token'
            );
        } finally {
            wp_set_current_user(0);
            wp_delete_user($user_id);
        }
    }
}
