<?php
require_once __DIR__ . '/WPRegistrationErrorsTestBase.php';
use Cleantalk\Antispam\IntegrationsByClass\WPRegistrationErrors;

class WPRegistrationErrorsCredentialsTest extends WPRegistrationErrorsTestBase
{
    public function test_probably_facebook_credentials_fills_empty_login_and_email()
    {
        $handler = $this->getMockBuilder(WPRegistrationErrors::class)
            ->onlyMethods(array('getFacebookFlow'))
            ->disableOriginalConstructor()
            ->getMock();

        $this->setHandlerState($handler);

        $handler->method('getFacebookFlow')->willReturn(array(
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ));

        $login = null;
        $email = null;

        $isFacebook = $handler->probablyFacebookCredentials($login, $email);

        $this->assertTrue($isFacebook);
        $this->assertSame('John Doe', $login);
        $this->assertSame('john@example.com', $email);
    }

    public function test_probably_buddypress_credentials_reads_from_post()
    {
        $_POST['signup_username'] = 'buddy_user';
        $_POST['signup_email'] = 'buddy@example.com';

        $handler = new WPRegistrationErrors();

        $login = null;
        $email = null;

        $isBuddyPress = $handler->probablyBuddyPressCredentials($login, $email);

        $this->assertTrue($isBuddyPress);
        $this->assertSame('buddy_user', $login);
        $this->assertSame('buddy@example.com', $email);
    }
}
