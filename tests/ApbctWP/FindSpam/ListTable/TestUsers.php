<?php

namespace Cleantalk\ApbctWP\FindSpam\ListTable;

use Cleantalk\ApbctWP\FindSpam\ListTable\Users;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Users list table column_ct_username method.
 */
class TestUsers extends TestCase
{
    /**
     * @var Users
     */
    private $instance;

    /**
     * @var \ReflectionMethod
     */
    private $columnCtUsername;

    protected function setUp(): void
    {
        $reflection = new \ReflectionClass(Users::class);
        $this->instance = $reflection->newInstanceWithoutConstructor();

        $this->columnCtUsername = $reflection->getMethod('column_ct_username');
        $this->columnCtUsername->setAccessible(true);

        // Mock apbct: white_label and login_ip_keeper (object with getIP method)
        $ipKeeper = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getIP'])
            ->getMock();
        $ipKeeper->method('getIP')->willReturn(null);

        $this->instance->apbct = (object)[
            'white_label'      => true,
            'login_ip_keeper'  => $ipKeeper,
        ];
    }

    /**
     * column_ct_username contains user login and row actions with nonce.
     */
    public function testColumnCtUsernameContainsLoginAndActionsWithNonce(): void
    {
        $_GET['page'] = 'ct_check_users';

        $user = (object)[
            'ID'          => 42,
            'user_login'  => 'testuser',
            'user_email'  => 'test@example.com',
        ];
        $item = ['ct_username' => $user];

        $result = $this->columnCtUsername->invoke($this->instance, $item);

        $this->assertStringContainsString('testuser', $result);
        $this->assertStringContainsString('test@example.com', $result);
        $this->assertStringContainsString('mailto:test@example.com', $result);
        $this->assertStringContainsString('Approve', $result);
        $this->assertStringContainsString('Delete', $result);
        $this->assertStringContainsString('_wpnonce=', $result);
        $this->assertStringContainsString('action=approve', $result);
        $this->assertStringContainsString('action=delete', $result);
        $this->assertStringContainsString('spam=42', $result);
    }

    /**
     * column_ct_username shows "No email" when user has no email.
     */
    public function testColumnCtUsernameShowsNoEmailWhenEmpty(): void
    {
        $_GET['page'] = 'ct_check_users';

        $user = (object)[
            'ID'          => 1,
            'user_login'  => 'noemailuser',
            'user_email'  => '',
        ];
        $item = ['ct_username' => $user];

        $result = $this->columnCtUsername->invoke($this->instance, $item);

        $this->assertStringContainsString('noemailuser', $result);
        $this->assertStringContainsString('No email', $result);
    }

    /**
     * column_ct_username shows IP and link when login_ip_keeper returns IP.
     */
    public function testColumnCtUsernameShowsIpWhenKeeperReturnsIp(): void
    {
        $_GET['page'] = 'ct_check_users';

        $ipKeeper = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getIP'])
            ->getMock();
        $ipKeeper->method('getIP')->with(99)->willReturn('192.168.1.1');
        $this->instance->apbct->login_ip_keeper = $ipKeeper;

        $user = (object)[
            'ID'          => 99,
            'user_login'  => 'ipuser',
            'user_email'  => 'ip@test.com',
        ];
        $item = ['ct_username' => $user];

        $result = $this->columnCtUsername->invoke($this->instance, $item);

        $this->assertStringContainsString('192.168.1.1', $result);
        $this->assertStringContainsString('user-edit.php?user_id=99', $result);
    }

    /**
     * column_ct_username shows "No IP adress" when keeper returns null.
     */
    public function testColumnCtUsernameShowsNoIpWhenKeeperReturnsNull(): void
    {
        $_GET['page'] = 'ct_check_users';

        $user = (object)[
            'ID'          => 1,
            'user_login'  => 'noipuser',
            'user_email'  => 'a@b.com',
        ];
        $item = ['ct_username' => $user];

        $result = $this->columnCtUsername->invoke($this->instance, $item);

        $this->assertStringContainsString('No IP adress', $result);
    }
}
