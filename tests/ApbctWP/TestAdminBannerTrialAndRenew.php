<?php

use Cleantalk\ApbctWP\AdminBannersModule\AdminBannerTrialAndRenew;
use PHPUnit\Framework\TestCase;

class TestAdminBannerTrialAndRenew extends TestCase
{
    protected function setUp(): void
    {
        global $apbct;
        $apbct->notice_show = true;
        $apbct->notice_trial = 0;
        $apbct->notice_renew = 0;
        $apbct->moderate_ip = 0;
        $apbct->white_label = false;
        $apbct->data['wl_brandname'] = 'Anti-Spam by CleanTalk';
        $apbct->user_token = 'test_token';
    }

    protected function tearDown(): void
    {
        global $apbct;
        $apbct->notice_show = false;
        $apbct->notice_trial = 0;
        $apbct->notice_renew = 0;
    }

    private function renderBanner()
    {
        $banner = new AdminBannerTrialAndRenew();
        ob_start();
        $banner->show();
        return ob_get_clean();
    }

    public function testConstructorDoesNotThrow()
    {
        $banner = new AdminBannerTrialAndRenew();
        $this->assertInstanceOf(AdminBannerTrialAndRenew::class, $banner);
    }

    public function testShowsWhenTrialIsOne()
    {
        global $apbct;
        $apbct->notice_trial = 1;

        $output = $this->renderBanner();
        $this->assertNotEmpty($output);
    }

    public function testShowsWhenRenewIsOne()
    {
        global $apbct;
        $apbct->notice_renew = 1;

        $output = $this->renderBanner();
        $this->assertNotEmpty($output);
    }

    public function testShowsWhenBothTrialAndRenewAreOne()
    {
        global $apbct;
        $apbct->notice_trial = 1;
        $apbct->notice_renew = 1;

        $output = $this->renderBanner();
        $this->assertNotEmpty($output);
    }

    public function testDoesNotShowWhenBothTrialAndRenewAreZero()
    {
        $output = $this->renderBanner();
        $this->assertEmpty($output);
    }

    public function testDoesNotShowWhenNoticeShowIsFalse()
    {
        global $apbct;
        $apbct->notice_show = false;
        $apbct->notice_trial = 1;

        $output = $this->renderBanner();
        $this->assertEmpty($output);
    }

    public function testDoesNotShowWhenWhiteLabelEnabled()
    {
        global $apbct;
        $apbct->notice_trial = 1;
        $apbct->white_label = true;

        $output = $this->renderBanner();
        $this->assertEmpty($output);
    }

    public function testDisplayContainsBannerId()
    {
        global $apbct;
        $apbct->notice_trial = 1;

        $output = $this->renderBanner();
        $this->assertStringContainsString('id="cleantalk_notice_trial"', $output);
    }

    public function testDisplayContainsApbctNoticeClass()
    {
        global $apbct;
        $apbct->notice_trial = 1;

        $output = $this->renderBanner();
        $this->assertStringContainsString('apbct-notice', $output);
        $this->assertStringContainsString('apbct-banner-error', $output);
    }

    public function testDisplayContainsLogoImage()
    {
        global $apbct;
        $apbct->notice_trial = 1;

        $output = $this->renderBanner();
        $this->assertStringContainsString('logo-cleantalk1.svg', $output);
    }

    public function testDisplayContainsUpgradeButton()
    {
        global $apbct;
        $apbct->notice_trial = 1;

        $output = $this->renderBanner();
        $this->assertStringContainsString('apbct-banner-button-red', $output);
        $this->assertStringContainsString('UPGRADE NOW', $output);
    }

    public function testDisplayContainsRenewalLink()
    {
        global $apbct;
        $apbct->notice_trial = 1;

        $output = $this->renderBanner();
        $this->assertStringContainsString('p.cleantalk.org', $output);
        $this->assertStringContainsString('test_token', $output);
    }

    public function testDisplayContainsSettingsLink()
    {
        global $apbct;
        $apbct->notice_trial = 1;

        $output = $this->renderBanner();
        $this->assertStringContainsString('options-general.php?page=cleantalk', $output);
        $this->assertStringContainsString('apbct-banner-link', $output);
    }

    public function testHidingTimeIs14Days()
    {
        $reflection = new \ReflectionClass(AdminBannerTrialAndRenew::class);
        $constant = $reflection->getConstant('HIDING_TIME');
        $this->assertEquals(14, $constant);
    }

    public function testBannerNameIsNoticeTrial()
    {
        $reflection = new \ReflectionClass(AdminBannerTrialAndRenew::class);
        $constant = $reflection->getConstant('NAME');
        $this->assertEquals('notice_trial', $constant);
    }
}
