<?php

use Cleantalk\ApbctWP\AdminBannersModule\AdminBannerTrialAndRenewFullpage;
use PHPUnit\Framework\TestCase;

class TestAdminBannerTrialAndRenewFullpage extends TestCase
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

    public function testConstructorDoesNotThrow()
    {
        $banner = new AdminBannerTrialAndRenewFullpage();
        $this->assertInstanceOf(AdminBannerTrialAndRenewFullpage::class, $banner);
    }

    public function testNeedToShowReturnsTrueWhenTrialIsOne()
    {
        global $apbct;
        $apbct->notice_trial = 1;

        $banner = new AdminBannerTrialAndRenewFullpage();
        $this->assertTrue($banner->needToShow());
    }

    public function testNeedToShowReturnsTrueWhenRenewIsOne()
    {
        global $apbct;
        $apbct->notice_renew = 1;

        $banner = new AdminBannerTrialAndRenewFullpage();
        $this->assertTrue($banner->needToShow());
    }

    public function testNeedToShowReturnsTrueWhenBothAreOne()
    {
        global $apbct;
        $apbct->notice_trial = 1;
        $apbct->notice_renew = 1;

        $banner = new AdminBannerTrialAndRenewFullpage();
        $this->assertTrue($banner->needToShow());
    }

    public function testNeedToShowReturnsFalseWhenBothAreZero()
    {
        $banner = new AdminBannerTrialAndRenewFullpage();
        $this->assertFalse($banner->needToShow());
    }

    public function testNeedToShowReturnsFalseWhenNoticeShowIsFalse()
    {
        global $apbct;
        $apbct->notice_show = false;
        $apbct->notice_trial = 1;

        $banner = new AdminBannerTrialAndRenewFullpage();
        $this->assertFalse($banner->needToShow());
    }

    public function testNeedToShowReturnsFalseWhenWhiteLabelEnabled()
    {
        global $apbct;
        $apbct->notice_trial = 1;
        $apbct->white_label = true;

        $banner = new AdminBannerTrialAndRenewFullpage();
        $this->assertFalse($banner->needToShow());
    }

    public function testDisplayContainsBannerId()
    {
        global $apbct;
        $apbct->notice_trial = 1;

        $banner = new AdminBannerTrialAndRenewFullpage();
        ob_start();
        $banner->display();
        $output = ob_get_clean();

        $this->assertStringContainsString('id="cleantalk_trial_fullpage"', $output);
    }

    public function testDisplayContainsFullpageClass()
    {
        global $apbct;
        $apbct->notice_trial = 1;

        $banner = new AdminBannerTrialAndRenewFullpage();
        ob_start();
        $banner->display();
        $output = ob_get_clean();

        $this->assertStringContainsString('apbct-trial-renew-fullpage', $output);
    }

    public function testDisplayContainsSessionStorageScript()
    {
        global $apbct;
        $apbct->notice_trial = 1;

        $banner = new AdminBannerTrialAndRenewFullpage();
        ob_start();
        $banner->display();
        $output = ob_get_clean();

        $this->assertStringContainsString('apbct_trial_fullpage_dismissed', $output);
        $this->assertStringContainsString('sessionStorage', $output);
    }

    public function testDisplayContainsLogoImage()
    {
        global $apbct;
        $apbct->notice_trial = 1;

        $banner = new AdminBannerTrialAndRenewFullpage();
        ob_start();
        $banner->display();
        $output = ob_get_clean();

        $this->assertStringContainsString('logo-cleantalk1.svg', $output);
    }

    public function testDisplayContainsUpgradeButton()
    {
        global $apbct;
        $apbct->notice_trial = 1;

        $banner = new AdminBannerTrialAndRenewFullpage();
        ob_start();
        $banner->display();
        $output = ob_get_clean();

        $this->assertStringContainsString('apbct-banner-button-red', $output);
        $this->assertStringContainsString('UPGRADE NOW', $output);
    }

    public function testDisplayContainsStatisticsBlock()
    {
        global $apbct;
        $apbct->notice_trial = 1;

        $banner = new AdminBannerTrialAndRenewFullpage();
        ob_start();
        $banner->display();
        $output = ob_get_clean();

        $this->assertStringContainsString('apbct-banner-stat-value', $output);
        $this->assertStringContainsString('apbct-banner-red-point', $output);
    }

    public function testDisplayContainsBenefitsBlock()
    {
        global $apbct;
        $apbct->notice_trial = 1;

        $banner = new AdminBannerTrialAndRenewFullpage();
        ob_start();
        $banner->display();
        $output = ob_get_clean();

        $this->assertStringContainsString('check.svg', $output);
        $this->assertStringContainsString('apbct-banner-background-container', $output);
    }

    public function testDisplayContainsFullpageImage()
    {
        global $apbct;
        $apbct->notice_trial = 1;

        $banner = new AdminBannerTrialAndRenewFullpage();
        ob_start();
        $banner->display();
        $output = ob_get_clean();

        $this->assertStringContainsString('img_fullpage_trial_banner.svg', $output);
    }

    public function testDisplayHidesWpFooter()
    {
        global $apbct;
        $apbct->notice_trial = 1;

        $banner = new AdminBannerTrialAndRenewFullpage();
        ob_start();
        $banner->display();
        $output = ob_get_clean();

        $this->assertStringContainsString('#wpfooter', $output);
        $this->assertStringContainsString('display: none', $output);
    }

    public function testIsDismissedAlwaysReturnsFalse()
    {
        $banner = new AdminBannerTrialAndRenewFullpage();
        $reflection = new \ReflectionMethod($banner, 'isDismissed');
        $reflection->setAccessible(true);

        $this->assertFalse($reflection->invoke($banner));
    }

    public function testBannerNameIsTrialFullpage()
    {
        $reflection = new \ReflectionClass(AdminBannerTrialAndRenewFullpage::class);
        $constant = $reflection->getConstant('NAME');
        $this->assertEquals('trial_fullpage', $constant);
    }
}
