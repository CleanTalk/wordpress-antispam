<?php

use PHPUnit\Framework\TestCase;
use Cleantalk\ApbctWP\FindSpam\LoginIPKeeper;

class LoginIPKeeperTest extends TestCase
{
    /**
     * @var LoginIPKeeper
     */
    private $loginIPKeeper;

    protected function setUp(): void
    {
        $this->loginIPKeeper = new LoginIPKeeper();
    }

    public function testAddRecord()
    {
        // Create a WordPress user
        $user_id = wp_create_user('testuser', 'password', 'testuser@example.com');
        $wp_user = get_user_by('id', $user_id);

        // Set session tokens
        $session_tokens = [
            'token1' => [
                'ip' => '192.168.1.1',
                'login' => time(),
            ],
        ];
        update_user_meta($wp_user->ID, 'session_tokens', $session_tokens);

        // Call the method
        $this->loginIPKeeper->addRecord($wp_user);

        // Assert that the meta record was updated
        $meta_data = get_user_meta($wp_user->ID, '_cleantalk_ip_keeper_data', true);
        $meta_data = json_decode($meta_data, true);

        $this->assertEquals('192.168.1.1', $meta_data['ip']);
        $this->assertNotEmpty($meta_data['last_login']);
    }

    public function testGetMetaRecordValue()
    {
        // Create a WordPress user
        $user_id = wp_create_user('testuser2', 'password', 'testuser2@example.com');

        // Set meta data
        $meta_data = json_encode(['ip' => '192.168.1.1', 'last_login' => 1000000]);
        update_user_meta($user_id, '_cleantalk_ip_keeper_data', $meta_data);

        // Call the method
        $result = $this->loginIPKeeper->getMetaRecordValue($user_id, 'ip');

        // Assert the result
        $this->assertEquals('192.168.1.1', $result);

        // Call the method
        $result = $this->loginIPKeeper->getMetaRecordValue($user_id, 'last_login');

        // Assert the result
        $this->assertEquals('1000000', $result);
    }

    public function testRotateData()
    {
        // Create a WordPress user
        $user_id = wp_create_user('testuser3', 'password', 'testuser3@example.com');

        // Set meta data with an too old last login
        $meta_data = json_encode(['ip' => '192.168.1.1', 'last_login' => time() - (86400 * 31)]);
        update_user_meta($user_id, '_cleantalk_ip_keeper_data', $meta_data);

        // Call the method with rotation inside
        $this->loginIPKeeper->hookSaveLoggedInUserData(null);

        // Assert that the meta record was deleted
        $meta_data = get_user_meta($user_id, '_cleantalk_ip_keeper_data', true);
        $this->assertEmpty($meta_data);
    }
}
