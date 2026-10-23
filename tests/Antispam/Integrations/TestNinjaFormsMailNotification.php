<?php

use Cleantalk\Antispam\Integrations\NinjaForms;

/**
 * Tests for NinjaForms::hookChangeMailNotification().
 */
class TestNinjaFormsMailNotification extends \ApbctTestCase
{
    protected function setUp(): void
    {
        global $apbct;

        $apbct->user_token = 'secretusertoken0123456789abcdef0';
        $apbct->sender_email = 'visitor@example.com';
        $apbct->sender_ip = '10.0.0.1';
    }

    public function testAdminMailGetsNoticeWithoutUserToken()
    {
        $result = NinjaForms::hookChangeMailNotification('Original body', array(), array('to' => 'admin@example.com'));

        $this->assertStringContainsString('CleanTalk Anti-Spam: This message could be spam.', $result);
        $this->assertStringContainsString('Original body', $result);
        $this->assertStringNotContainsString('secretusertoken0123456789abcdef0', $result);
    }

    public function testAdminMailGetsNoticeWhenSenderIsSubstringOfRecipient()
    {
        global $apbct;
        $apbct->sender_email = 'min@example.com';

        $result = NinjaForms::hookChangeMailNotification('Original body', array(), array('to' => 'admin@example.com'));

        $this->assertStringContainsString('CleanTalk Anti-Spam: This message could be spam.', $result);
    }

    public function testVisitorMailWithEmailLikeDisplayNameIsDetected()
    {
        global $apbct;
        $apbct->sender_email = "o'connor@example.com";

        $to_visitor = NinjaForms::hookChangeMailNotification('Thank you', array(), array('to' => "O'Connor <o'connor@example.com>"));
        $to_admin = NinjaForms::hookChangeMailNotification('Original body', array(), array('to' => "\"o'connor@example.com\" <admin@example.com>"));

        $this->assertSame('Thank you', $to_visitor);
        $this->assertStringContainsString('CleanTalk Anti-Spam: This message could be spam.', $to_admin);
    }

    public function testMailToSenderIsNotChanged()
    {
        $result = NinjaForms::hookChangeMailNotification('Thank you', array(), array('to' => 'admin@example.com,visitor@example.com'));

        $this->assertSame('Thank you', $result);
    }
}
