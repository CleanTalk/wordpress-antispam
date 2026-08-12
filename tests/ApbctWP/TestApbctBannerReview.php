<?php

use Cleantalk\ApbctWP\AdminBannersModule\AdminBannerReview;
use PHPUnit\Framework\TestCase;

class TestApbctBannerReview extends TestCase
{
    protected function setUp(): void
    {
        global $apbct;
        $apbct->notice_review = 1;
        $apbct->white_label = false;
        $apbct->data['wl_brandname'] = 'CleanTalk Anti-Spam';
    }

    protected function tearDown(): void
    {
        global $apbct;
        $apbct->notice_review = 0;
    }

    private function renderBanner()
    {
        $banner = new AdminBannerReview();
        ob_start();
        $banner->show();
        return ob_get_clean();
    }

    public function testConstructorDoesNotThrow()
    {
        $banner = new AdminBannerReview();
        $this->assertInstanceOf(AdminBannerReview::class, $banner);
    }

    public function testDisplayContainsBannerId()
    {
        $output = $this->renderBanner();
        $this->assertStringContainsString('id="cleantalk_notice_review"', $output);
    }

    public function testDisplayContainsApbctNoticeClass()
    {
        $output = $this->renderBanner();
        $this->assertStringContainsString('apbct-notice', $output);
        $this->assertStringContainsString('apbct-banner-success', $output);
        $this->assertStringContainsString('is-dismissible', $output);
    }

    public function testDisplayContainsLogoImage()
    {
        $output = $this->renderBanner();
        $this->assertStringContainsString('logo-cleantalk1.svg', $output);
        $this->assertStringContainsString('review.svg', $output);
    }

    public function testDisplayContainsSettingsLink()
    {
        $output = $this->renderBanner();
        $this->assertStringContainsString('options-general.php?page=cleantalk', $output);
        $this->assertStringContainsString('apbct-banner-link', $output);
    }

    public function testDisplayContainsReviewButton()
    {
        $output = $this->renderBanner();
        $this->assertStringContainsString(
            'https://wordpress.org/support/plugin/cleantalk-spam-protect/reviews/?filter=5',
            $output
        );
        $this->assertStringContainsString('apbct-banner-button-green', $output);
    }

    public function testDisplayContainsDismissLink()
    {
        $output = $this->renderBanner();
        $this->assertStringContainsString('notice-dismiss-link', $output);
        $this->assertStringContainsString('apbct-banner-dismiss-link', $output);
    }

    public function testDoesNotShowWhenReviewFlagIsZero()
    {
        global $apbct;
        $apbct->notice_review = 0;

        $output = $this->renderBanner();
        $this->assertEmpty($output);
    }

    public function testDoesNotShowWhenWhiteLabelEnabled()
    {
        global $apbct;
        $apbct->white_label = true;

        $output = $this->renderBanner();
        $this->assertEmpty($output);
    }

    public function testHidingTimeIs365Days()
    {
        $reflection = new \ReflectionClass(AdminBannerReview::class);
        $constant = $reflection->getConstant('HIDING_TIME');
        $this->assertEquals(365, $constant);
    }

    public function testDaysIntervalHidingReviewNotice()
    {
        $reflection = new \ReflectionClass(\Cleantalk\ApbctWP\AdminNotices::class);
        $constant = $reflection->getConstant('DAYS_INTERVAL_HIDING_REVIEW_NOTICE');
        $this->assertEquals(365, $constant);
    }

    public function testDaysIntervalHidingNoticeIsNotAffected()
    {
        $reflection = new \ReflectionClass(\Cleantalk\ApbctWP\AdminNotices::class);
        $constant = $reflection->getConstant('DAYS_INTERVAL_HIDING_NOTICE');
        $this->assertEquals(14, $constant);
    }
}
