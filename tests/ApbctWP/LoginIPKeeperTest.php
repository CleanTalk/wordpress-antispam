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
            ],
        ];
        update_user_meta($wp_user->ID, 'session_tokens', $session_tokens);

        // Call the method
        $this->loginIPKeeper->addUserIP($wp_user);

        // Assert that the meta record was updated
        $ip = get_user_meta($wp_user->ID, '_cleantalk_ip_keeper_data', true);

        $this->assertEquals('192.168.1.1', $ip);
    }

    public function testGetMetaRecordValue()
    {
        // Create a WordPress user
        $user_id = wp_create_user('testuser2', 'password', 'testuser2@example.com');

        // Set meta data
        update_user_meta($user_id, '_cleantalk_ip_keeper_data', '192.168.1.1');

        // Call the method
        $result = $this->loginIPKeeper->getIP($user_id);

        // Assert the result
        $this->assertEquals('192.168.1.1', $result);
    }
}
