<?php

use Cleantalk\ApbctWP\LinkConstructor;

class TestCleanTalkLinks extends \PHPUnit\Framework\TestCase
{
    public function testWidgetLink()
    {
        $instance['refid'] = 9000;
        $get_params = ! empty($instance['refid'])
            ? array('pid' => $instance['refid'])
            : array();
        $link = LinkConstructor::buildCleanTalkLink(
            'public_widget_referal_link',
            '',
            $get_params
        );
        $this->assertIsString($link);
        $this->assertEquals(
            'https://cleantalk.org/?pid=9000&utm_id=&utm_term=&utm_source=public&utm_medium=widget&utm_content=referal_link&utm_campaign=apbct_links',
            $link);
    }

    public function testSettingsTopInfoLink()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'settings_top_info'
        );
        $this->assertIsString($link);
        $this->assertEquals(
            'https://cleantalk.org/?utm_id=&utm_term=&utm_source=admin_panel&utm_medium=apbct_options&utm_content=settings_top_info&utm_campaign=apbct_links',
            $link);
    }

    public function testDashboardWidgetLink()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'dashboard_widget_all_data_link',
            'my/show_requests',
            array(
                'user_token' => 9000
            )
        );
        $this->assertIsString($link);
        $this->assertEquals(
            'https://cleantalk.org/my/show_requests?user_token=9000&utm_id=&utm_term=&utm_source=admin_panel&utm_medium=dashboard_widget&utm_content=view_all&utm_campaign=apbct_links',
            $link);
    }

    public function testAdminBlacklistsAvatarLink()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'admin_blacklists_avatar_link',
            'blacklists/{TARGET}'
        );
        $this->assertIsString($link);
        $this->assertEquals(
            'https://cleantalk.org/blacklists/{TARGET}?utm_source=admin_side&utm_medium=comments&utm_content=avatar&utm_campaign=apbct_links',
            $link
        );
    }

    public function testAdminBlacklistsAvatarLinkPresetExists()
    {
        $this->assertArrayHasKey('admin_blacklists_avatar_link', LinkConstructor::$utm_presets);
        $preset = LinkConstructor::$utm_presets['admin_blacklists_avatar_link'];
        $this->assertSame('admin_side', $preset['utm_source']);
        $this->assertSame('comments', $preset['utm_medium']);
        $this->assertSame('avatar', $preset['utm_content']);
        $this->assertSame('apbct_links', $preset['utm_campaign']);
    }

    public function testEmailBlacklistsCommentPassedEmailPresetExists()
    {
        $this->assertArrayHasKey('email_blacklists_comment_passed_email', LinkConstructor::$utm_presets);
        $preset = LinkConstructor::$utm_presets['email_blacklists_comment_passed_email'];
        $this->assertSame('newsletter', $preset['utm_source']);
        $this->assertSame('email', $preset['utm_medium']);
        $this->assertSame('blacklists_check', $preset['utm_content']);
        $this->assertSame('wp_spam_comment_passed', $preset['utm_campaign']);
    }

    public function testEmailBlacklistsCommentPassedIpPresetExists()
    {
        $this->assertArrayHasKey('email_blacklists_comment_passed_ip', LinkConstructor::$utm_presets);
        $preset = LinkConstructor::$utm_presets['email_blacklists_comment_passed_ip'];
        $this->assertSame('newsletter', $preset['utm_source']);
        $this->assertSame('ip', $preset['utm_medium']);
        $this->assertSame('blacklists_check', $preset['utm_content']);
        $this->assertSame('wp_spam_comment_passed', $preset['utm_campaign']);
    }

    public function testEmailBlacklistsCommentActivateAntispamEmailPresetExists()
    {
        $this->assertArrayHasKey('email_blacklists_comment_activate_antispam_email', LinkConstructor::$utm_presets);
        $preset = LinkConstructor::$utm_presets['email_blacklists_comment_activate_antispam_email'];
        $this->assertSame('newsletter', $preset['utm_source']);
        $this->assertSame('email', $preset['utm_medium']);
        $this->assertSame('blacklists_check', $preset['utm_content']);
        $this->assertSame('wp_spam_comment_activate_antispam', $preset['utm_campaign']);
    }

    public function testEmailBlacklistsCommentActivateAntispamIpPresetExists()
    {
        $this->assertArrayHasKey('email_blacklists_comment_activate_antispam_ip', LinkConstructor::$utm_presets);
        $preset = LinkConstructor::$utm_presets['email_blacklists_comment_activate_antispam_ip'];
        $this->assertSame('newsletter', $preset['utm_source']);
        $this->assertSame('ip', $preset['utm_medium']);
        $this->assertSame('blacklists_check', $preset['utm_content']);
        $this->assertSame('wp_spam_comment_activate_antispam', $preset['utm_campaign']);
    }

    public function testEmailBlacklistsCommentPassedEmailLink()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'email_blacklists_comment_passed_email',
            'blacklists/test@example.com'
        );
        $this->assertIsString($link);
        $this->assertEquals(
            'https://cleantalk.org/blacklists/test@example.com?utm_id=&utm_term=&utm_source=newsletter&utm_medium=email&utm_content=blacklists_check&utm_campaign=wp_spam_comment_passed',
            $link
        );
    }

    public function testEmailBlacklistsCommentPassedIpLink()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'email_blacklists_comment_passed_ip',
            'blacklists/1.2.3.4'
        );
        $this->assertIsString($link);
        $this->assertEquals(
            'https://cleantalk.org/blacklists/1.2.3.4?utm_id=&utm_term=&utm_source=newsletter&utm_medium=ip&utm_content=blacklists_check&utm_campaign=wp_spam_comment_passed',
            $link
        );
    }

    public function testEmailBlacklistsCommentActivateAntispamEmailLink()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'email_blacklists_comment_activate_antispam_email',
            'blacklists/test@example.com'
        );
        $this->assertIsString($link);
        $this->assertEquals(
            'https://cleantalk.org/blacklists/test@example.com?utm_id=&utm_term=&utm_source=newsletter&utm_medium=email&utm_content=blacklists_check&utm_campaign=wp_spam_comment_activate_antispam',
            $link
        );
    }

    public function testEmailBlacklistsCommentActivateAntispamIpLink()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'email_blacklists_comment_activate_antispam_ip',
            'blacklists/1.2.3.4'
        );
        $this->assertIsString($link);
        $this->assertEquals(
            'https://cleantalk.org/blacklists/1.2.3.4?utm_id=&utm_term=&utm_source=newsletter&utm_medium=ip&utm_content=blacklists_check&utm_campaign=wp_spam_comment_activate_antispam',
            $link
        );
    }

    public function testEmailAndIpPresetsHaveDifferentMedium()
    {
        $email_preset = LinkConstructor::$utm_presets['email_blacklists_comment_passed_email'];
        $ip_preset    = LinkConstructor::$utm_presets['email_blacklists_comment_passed_ip'];
        $this->assertNotSame($email_preset['utm_medium'], $ip_preset['utm_medium']);
        $this->assertSame('email', $email_preset['utm_medium']);
        $this->assertSame('ip', $ip_preset['utm_medium']);
    }

    public function testRenewalLink()
    {
        $token = 'test_token';
        $link = 'test_link';
        $product_id = 1;
        $utm_data = array(
            'utm_source' => 'admin_panel',
            'utm_medium' => 'banner',
            'utm_content' => 'renew_notice_trial',
            //'utm_campaign' => 'renew_notice_trial',
            //'utm_term' => 'test_term',
        );
        $result = LinkConstructor::buildRenewalLinkATag($token, $link, $product_id, 'renew_notice_trial');
        $this->assertIsString($result);
        $this->assertStringContainsString($token, $result);
        $this->assertStringContainsString($link, $result);
        $this->assertStringContainsString('product_id=' . $product_id, $result);
        $this->assertStringContainsString('featured=&', $result);
        $this->assertStringContainsString('https://p.cleantalk.org', $result);
        $this->assertStringContainsString('>' . $link . '<', $result);
        foreach ($utm_data as $key => $value) {
            $this->assertStringContainsString($key . '=' . $value, $result);
        }
    }
}
