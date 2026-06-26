<?php

use Cleantalk\ApbctWP\ApbctBannerReview;
use Cleantalk\Common\UniversalBanner\BannerDataDto;
use PHPUnit\Framework\TestCase;

class TestApbctBannerReview extends TestCase
{
    /**
     * @var BannerDataDto
     */
    private $banner_data;

    protected function setUp(): void
    {
        $this->banner_data = new BannerDataDto();
        $this->banner_data->type = 'review';
        $this->banner_data->text = 'Share your positive experience';
        $this->banner_data->secondary_text = 'You have been using CleanTalk Anti-Spam';
        $this->banner_data->button_url = 'https://wordpress.org/support/plugin/cleantalk-spam-protect/reviews/?filter=5';
        $this->banner_data->button_text = 'SHARE YOUR FEEDBACK';
        $this->banner_data->additional_text = 'Already posted the review';
        $this->banner_data->level = 'success';
    }

    public function testConstructorDoesNotThrow()
    {
        $banner = new ApbctBannerReview(
            $this->banner_data,
            'options-general.php?page=cleantalk',
            'https://example.com/wp-content/plugins/cleantalk-spam-protect/inc/images'
        );

        $this->assertInstanceOf(ApbctBannerReview::class, $banner);
    }

    public function testEchoBannerBodyContainsBannerId()
    {
        $banner = new ApbctBannerReview(
            $this->banner_data,
            'options-general.php?page=cleantalk',
            'https://example.com/inc/images'
        );

        ob_start();
        $banner->echoBannerBody();
        $output = ob_get_clean();

        $this->assertStringContainsString('id="cleantalk_notice_review"', $output);
    }

    public function testEchoBannerBodyContainsApbctNoticeClass()
    {
        $banner = new ApbctBannerReview(
            $this->banner_data,
            'options-general.php?page=cleantalk',
            'https://example.com/inc/images'
        );

        ob_start();
        $banner->echoBannerBody();
        $output = ob_get_clean();

        $this->assertStringContainsString('apbct-notice', $output);
        $this->assertStringContainsString('apbct-banner-success', $output);
        $this->assertStringContainsString('is-dismissible', $output);
    }

    public function testEchoBannerBodyContainsLogoImage()
    {
        $images_url = 'https://example.com/inc/images';
        $banner = new ApbctBannerReview(
            $this->banner_data,
            'options-general.php?page=cleantalk',
            $images_url
        );

        ob_start();
        $banner->echoBannerBody();
        $output = ob_get_clean();

        $this->assertStringContainsString('logo-cleantalk1.svg', $output);
        $this->assertStringContainsString('review.svg', $output);
    }

    public function testEchoBannerBodyContainsSettingsLink()
    {
        $settings_link = 'options-general.php?page=cleantalk';
        $banner = new ApbctBannerReview(
            $this->banner_data,
            $settings_link,
            'https://example.com/inc/images'
        );

        ob_start();
        $banner->echoBannerBody();
        $output = ob_get_clean();

        $this->assertStringContainsString($settings_link, $output);
        $this->assertStringContainsString('apbct-banner-link', $output);
    }

    public function testEchoBannerBodyContainsTitleAndSubtitle()
    {
        $banner = new ApbctBannerReview(
            $this->banner_data,
            'options-general.php?page=cleantalk',
            'https://example.com/inc/images'
        );

        ob_start();
        $banner->echoBannerBody();
        $output = ob_get_clean();

        $this->assertStringContainsString('Share your positive experience', $output);
        $this->assertStringContainsString('You have been using CleanTalk Anti-Spam', $output);
    }

    public function testEchoBannerBodyContainsReviewButton()
    {
        $banner = new ApbctBannerReview(
            $this->banner_data,
            'options-general.php?page=cleantalk',
            'https://example.com/inc/images'
        );

        ob_start();
        $banner->echoBannerBody();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'https://wordpress.org/support/plugin/cleantalk-spam-protect/reviews/?filter=5',
            $output
        );
        $this->assertStringContainsString('SHARE YOUR FEEDBACK', $output);
        $this->assertStringContainsString('apbct-banner-button-green', $output);
    }

    public function testEchoBannerBodyContainsDismissLink()
    {
        $banner = new ApbctBannerReview(
            $this->banner_data,
            'options-general.php?page=cleantalk',
            'https://example.com/inc/images'
        );

        ob_start();
        $banner->echoBannerBody();
        $output = ob_get_clean();

        $this->assertStringContainsString('Already posted the review', $output);
        $this->assertStringContainsString('notice-dismiss-link', $output);
        $this->assertStringContainsString('apbct-banner-dismiss-link', $output);
    }

    public function testEchoBannerBodyEscapesHtmlInText()
    {
        $this->banner_data->text = '<script>alert("xss")</script>';
        $this->banner_data->secondary_text = '<img onerror="alert(1)">';

        $banner = new ApbctBannerReview(
            $this->banner_data,
            'options-general.php?page=cleantalk',
            'https://example.com/inc/images'
        );

        ob_start();
        $banner->echoBannerBody();
        $output = ob_get_clean();

        // Raw HTML tags must not be present - they should be escaped
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringNotContainsString('<img onerror', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
        $this->assertStringContainsString('&lt;img', $output);
    }

    public function testEchoBannerBodyEscapesUrlInButtonHref()
    {
        $this->banner_data->button_url = 'javascript:alert(1)';

        $banner = new ApbctBannerReview(
            $this->banner_data,
            'options-general.php?page=cleantalk',
            'https://example.com/inc/images'
        );

        ob_start();
        $banner->echoBannerBody();
        $output = ob_get_clean();

        // esc_url should strip javascript: protocol
        $this->assertStringNotContainsString('javascript:', $output);
    }

    public function testEchoBannerBodyTrimsTrailingSlashFromImagesUrl()
    {
        $banner = new ApbctBannerReview(
            $this->banner_data,
            'options-general.php?page=cleantalk',
            'https://example.com/inc/images/'
        );

        ob_start();
        $banner->echoBannerBody();
        $output = ob_get_clean();

        // Should not contain double slashes in image paths
        $this->assertStringNotContainsString('images//logo', $output);
        $this->assertStringNotContainsString('images//review', $output);
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
