<?php

use Cleantalk\ApbctWP\AdminNotices;

class TestAdminNotices extends \PHPUnit\Framework\TestCase
{
    public function testRenewalLink()
    {
        $token = 'test_token';
        $link = 'test_link';
        $product_id = 1;
        $utm_data = array(
            'utm_source' => 'test_source',
            'utm_medium' => 'test_medium',
            'utm_campaign' => 'test_campaign',
            //'utm_content' => 'test_content',
            //'utm_term' => 'test_term',
        );
        $result = AdminNotices::generateRenewalLinkHTML($token, $link, $product_id, $utm_data);
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
