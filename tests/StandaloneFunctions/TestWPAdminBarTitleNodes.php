<?php
include 'inc/cleantalk-admin.php';

class TestWPAdminBarTitleNodes extends PHPUnit\Framework\TestCase
{

    public function testApbctTitleBar()
    {

        function testArrayContent($array, $test)
        {
            $test->assertIsArray($array);
            $test->assertArrayHasKey('parent', $array);
            $test->assertArrayHasKey('id', $array);
            $test->assertArrayHasKey('title', $array);
            $test->assertEquals('cleantalk_admin_bar__parent_node', $array['parent']);
            $test->assertEquals('apbct__parent_node', $array['id']);
        }

        global $apbct;
        //needs renew by notice_renew
        $apbct->notice_show = 1;
        $apbct->notice_renew = 1;
        $apbct->notice_trial = 0;
        $result = apbct__admin_bar__get_title_for_apbct($apbct);
        testArrayContent($result, $this);
        $title = $result['title'];
        $this->assertStringContainsString('Renew Anti-Spam', $title);
        $this->assertStringContainsString('product_id=1', $title);
        $this->assertStringContainsString('apbct-icon-attention-alt', $title);

        //needs renew by trial
        $apbct->notice_show = 1;
        $apbct->notice_renew = 0;
        $apbct->notice_trial = 1;
        $result = apbct__admin_bar__get_title_for_apbct($apbct);
        testArrayContent($result, $this);
        $title = $result['title'];
        $this->assertStringContainsString('Renew Anti-Spam', $title);
        $this->assertStringContainsString('product_id=1', $title);
        $this->assertStringContainsString('apbct-icon-attention-alt', $title);

        //needs renew but notice show false
        $apbct->notice_show = 0;
        $apbct->notice_renew = 1;
        $apbct->notice_trial = 0;
        $result = apbct__admin_bar__get_title_for_apbct($apbct);
        testArrayContent($result, $this);
        $title = $result['title'];
        $this->assertStringNotContainsString('Renew Anti-Spam', $title);
        $this->assertStringNotContainsString('apbct-icon-attention-alt', $title);

        //needs renew but notice show false
        $apbct->notice_show = 0;
        $apbct->notice_renew = 0;
        $apbct->notice_trial = 1;
        $result = apbct__admin_bar__get_title_for_apbct($apbct);
        testArrayContent($result, $this);
        $title = $result['title'];
        $this->assertStringNotContainsString('Renew Anti-Spam', $title);
        $this->assertStringNotContainsString('apbct-icon-attention-alt', $title);

        //is notice show - waits for mark only
        $apbct->notice_show = 1;
        $apbct->notice_renew = 0;
        $apbct->notice_trial = 0;
        $result = apbct__admin_bar__get_title_for_apbct($apbct);
        testArrayContent($result, $this);
        $title = $result['title'];
        $this->assertStringNotContainsString('Renew Anti-Spam', $title);
        $this->assertStringContainsString('apbct-icon-attention-alt', $title);

    }

    public function testSpbctTitleBar()
    {
        $spbc = new \stdClass();

        function testArrayContentSPBC($array, $test)
        {
            $test->assertIsArray($array);
            $test->assertArrayHasKey('parent', $array);
            $test->assertArrayHasKey('id', $array);
            $test->assertArrayHasKey('title', $array);
            $test->assertEquals('cleantalk_admin_bar__parent_node', $array['parent']);
            $test->assertEquals('spbc__parent_node', $array['id']);
        }

        //SPBC bar disabled
        $spbc->admin_bar_enabled = 0; //!!!
        $user_token = 1;
        $is_wl_mode = 0;
        $spbc->trial = 1;
        $spbc->notice_show = 1;
        $result = apbct__admin_bar__get_title_for_spbc($spbc, $user_token, $is_wl_mode);
        $this->assertFalse($result);

        //SPBC bar enabled but wl mode
        $spbc->admin_bar_enabled = 1;
        $user_token = 1;
        $is_wl_mode = 1; //!!!
        $spbc->trial = 1;
        $spbc->notice_show = 1;
        $result = apbct__admin_bar__get_title_for_spbc($spbc, $user_token, $is_wl_mode);
        $this->assertFalse($result);

        //SPBCT is not trial
        $spbc->admin_bar_enabled = 1;
        $user_token = 1;
        $is_wl_mode = 0;
        $spbc->trial = 0; //!!!
        $spbc->notice_show = 1;
        $result = apbct__admin_bar__get_title_for_spbc($spbc, $user_token, $is_wl_mode);
        testArrayContentSPBC($result, $this);
        $title = $result['title'];
        $this->assertStringNotContainsString('Renew Security', $title);
        $this->assertStringContainsString('Security', $title);

        //user token empty
        $spbc->admin_bar_enabled = 1;
        $user_token = 0; //!!!
        $is_wl_mode = 0;
        $spbc->trial = 1;
        $spbc->notice_show = 1;
        $result = apbct__admin_bar__get_title_for_spbc($spbc, $user_token, $is_wl_mode);
        testArrayContentSPBC($result, $this);
        $title = $result['title'];
        $this->assertStringNotContainsString('Renew Security', $title);
        $this->assertStringContainsString('Security', $title);

        //no spbct found
        $result = apbct__admin_bar__get_title_for_spbc(null, 1, 1);
        testArrayContentSPBC($result, $this);
        $title = $result['title'];
        $this->assertStringNotContainsString('Renew Security', $title);
        $this->assertStringContainsString('Security', $title);

        //spbc is trial
        $spbc->admin_bar_enabled = 1;
        $user_token = 1;
        $is_wl_mode = 0;
        $spbc->trial = 1; //!!!
        $spbc->notice_show = 1; //!!!
        $result = apbct__admin_bar__get_title_for_spbc($spbc, $user_token, $is_wl_mode);
        testArrayContentSPBC($result, $this);
        $title = $result['title'];
        $this->assertStringContainsString('Renew Security', $title);
        $this->assertStringContainsString('product_id=4', $title);
        $this->assertStringContainsString('apbct-icon-attention-alt', $title);

        //spbc is trial an notice show false
        $spbc->admin_bar_enabled = 1;
        $user_token = 1;
        $is_wl_mode = 0;
        $spbc->trial = 1; //!!!
        $spbc->notice_show = 0; //!!!
        $result = apbct__admin_bar__get_title_for_spbc($spbc, $user_token, $is_wl_mode);
        testArrayContentSPBC($result, $this);
        $title = $result['title'];
        $this->assertStringContainsString('Renew Security', $title);
        $this->assertStringContainsString('product_id=4', $title);
        $this->assertStringNotContainsString('apbct-icon-attention-alt', $title);
    }
}
