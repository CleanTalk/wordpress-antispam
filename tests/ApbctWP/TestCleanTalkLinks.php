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
            'https://cleantalk.org/?pid=9000&utm_id=&utm_campaign=&utm_term=&utm_source=public&utm_medium=widget&utm_content=referal_link',
            $link);
    }

    public function testSettingsTopInfoLink()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'settings_top_info'
        );
        $this->assertIsString($link);
        $this->assertEquals(
            'https://cleantalk.org/?utm_id=&utm_campaign=&utm_term=&utm_source=admin_panel&utm_medium=apbct_options&utm_content=settings_top_info',
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
            'https://cleantalk.org/my/show_requests?user_token=9000&utm_id=&utm_campaign=&utm_term=&utm_source=admin_panel&utm_medium=dashboard_widget&utm_content=view_all',
            $link);
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
