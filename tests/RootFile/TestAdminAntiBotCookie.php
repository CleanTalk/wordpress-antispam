<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for CVE-style hosting-license antibot cookie leak into RemoteCalls.
 *
 * Verifies that:
 * 1. Subscriber (no manage_options) does not receive the antibot cookie in admin.
 * 2. Administrator may receive the cookie, but its value is not an RC token.
 */
class TestAdminAntiBotCookie extends TestCase
{
    private $apbctBackup;
    private $userIds = array();

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

        require_once ABSPATH . 'wp-admin/includes/user.php';
        require_once CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-admin.php';
    }

    protected function tearDown(): void
    {
        global $apbct;

        wp_set_current_user(0);

        foreach ( $this->userIds as $user_id ) {
            wp_delete_user($user_id);
        }
        $this->userIds = array();

        $apbct = $this->apbctBackup;
    }

    /**
     * @return int
     */
    private function createUserWithRole($role, $login)
    {
        $user_id = wp_create_user($login, 'password', $login . '@example.com');
        $this->assertIsInt($user_id);
        $this->userIds[] = $user_id;

        $user = new WP_User($user_id);
        $user->set_role($role);

        return $user_id;
    }

    public function testSubscriberDoesNotReceiveAntiBotCookieInAdmin()
    {
        $user_id = $this->createUserWithRole('subscriber', 'apbct_sub_' . wp_generate_password(6, false));
        wp_set_current_user($user_id);

        $this->assertFalse(current_user_can('manage_options'));

        ob_start();
        apbct_admin_set_cookie_for_anti_bot();
        $output = ob_get_clean();

        $this->assertSame(
            '',
            $output,
            'Subscriber must not receive wordpress_apbct_antibot cookie script in admin'
        );
    }

    public function testAdministratorCookieIsNotRemoteCallToken()
    {
        global $apbct;

        $user_id = $this->createUserWithRole('administrator', 'apbct_adm_' . wp_generate_password(6, false));
        wp_set_current_user($user_id);

        $this->assertTrue(current_user_can('manage_options'));

        ob_start();
        apbct_admin_set_cookie_for_anti_bot();
        $output = ob_get_clean();

        $this->assertStringContainsString('wordpress_apbct_antibot=', $output);

        $antibot_hash = apbct_get_anti_bot_cookie_hash($apbct->api_key, $apbct->data['salt']);
        $rc_token     = hash('sha256', $apbct->api_key . $apbct->data['salt']);

        $this->assertStringContainsString($antibot_hash, $output);
        $this->assertStringNotContainsString(
            $rc_token,
            $output,
            'Admin antibot cookie must not expose hosting-license RemoteCalls token'
        );
    }
}
