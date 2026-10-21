<?php

namespace Inc;

/**
 * Tests for apbct_form__contactForm7__changeMailNotification().
 */
class TestContactForm7MailNotification extends \ApbctTestCase
{
    protected function setUp(): void
    {
        global $apbct;

        require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-public-integrations.php');

        $apbct->user_token = 'secretusertoken0123456789abcdef0';
        $apbct->sender_email = 'visitor@example.com';
        $apbct->sender_ip = '10.0.0.1';
    }

    private function getMail($name)
    {
        return new class ($name) {
            private $name;

            public function __construct($name)
            {
                $this->name = $name;
            }

            public function name()
            {
                return $this->name;
            }
        };
    }

    public function testMainMailGetsNoticeWithoutUserToken()
    {
        $component = array('body' => 'Original body', 'recipient' => 'admin@example.com');

        $result = apbct_form__contactForm7__changeMailNotification($component, null, $this->getMail('mail'));

        $this->assertStringContainsString('CleanTalk Anti-Spam: This message could be spam.', $result['body']);
        $this->assertStringContainsString('Original body', $result['body']);
        $this->assertStringNotContainsString('user_token', $result['body']);
        $this->assertStringNotContainsString('secretusertoken0123456789abcdef0', $result['body']);
    }

    public function testAutoresponderMailIsNotChanged()
    {
        $component = array('body' => 'Thank you', 'recipient' => 'someone@example.com');

        $result = apbct_form__contactForm7__changeMailNotification($component, null, $this->getMail('mail_2'));

        $this->assertSame($component, $result);
    }

    public function testMailToSenderIsNotChangedWithoutMailObject()
    {
        $component = array('body' => 'Thank you', 'recipient' => 'Visitor <visitor@example.com>');

        $result = apbct_form__contactForm7__changeMailNotification($component);

        $this->assertSame($component, $result);
    }
}
