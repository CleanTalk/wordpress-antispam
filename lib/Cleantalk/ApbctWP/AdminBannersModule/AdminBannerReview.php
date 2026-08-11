<?php

namespace Cleantalk\ApbctWP\AdminBannersModule;

class AdminBannerReview extends AdminBannerAbstract
{
    /**
     * Hiding time in days
     */
    const HIDING_TIME = 365;

    /**
     * Simple Banner Name, must be unique
     */
    const NAME = 'notice_review';

    /**
     * @var \Cleantalk\ApbctWP\State
     */
    private $apbct;

    /**
     * Data for template
     *
     * @var array<string, string> $template_data
     */
    private $template_data;

    /**
     * @var string
     */
    private $settings_link;

    /**
     * @var string
     */
    private $images_url;

    public function __construct()
    {
        global $apbct;

        $this->apbct = $apbct;

        $this->banner_id = $this->prefix . $this::NAME;

        $this->images_url = rtrim(APBCT_IMG_ASSETS_PATH, '/');

        $this->settings_link = is_network_admin()
            ? rtrim(get_site_option('siteurl'), '/') . '/wp-admin/options-general.php?page=cleantalk'
            : 'options-general.php?page=cleantalk';

        $this->template_data = array(
            'title' => __(
                'Share your positive experience — leave a rating on WordPress',
                'cleantalk-spam-protect'
            ),
            'subtitle' => sprintf(
                __(
                    "You've been using %s — tell us about your experience. Your feedback helps other users find the right solution and helps us make the plugin even better.",
                    'cleantalk-spam-protect'
                ),
                $apbct->data['wl_brandname']
            ),
            'button_url' => 'https://wordpress.org/support/plugin/cleantalk-spam-protect/reviews/?filter=5',
            'button_text' => __('SHARE YOUR FEEDBACK', 'cleantalk-spam-protect'),
            'dismiss_text' => __('Already posted the review', 'cleantalk-spam-protect'),
        );
    }

    /**
     * Check if the banner needs to be shown
     *
     * @return bool
     */
    protected function needToShow()
    {
        if (
            $this->apbct->notice_review == 1 &&
            ! $this->apbct->white_label &&
            ! $this->isDismissed()
        ) {
            return true;
        }

        return false;
    }

    /**
     * Print HTML of banner
     * @psalm-suppress PossiblyUndefinedStringArrayOffset
     */
    protected function display()
    {
        $data = $this->template_data;
        $banner_id = $this->banner_id;
        $logo_url = $this->images_url . '/logo-cleantalk1.svg';
        $review_img_url = $this->images_url . '/review.svg';

        ?>
        <div class="apbct-notice notice apbct-banner-success is-dismissible" id="<?php echo esc_attr($banner_id); ?>">
            <div class="apbct-banner-content" style="display: block;margin-bottom: 16px;">
                <div style="display: flex;">
                    <img src="<?php echo esc_url($logo_url); ?>" width="111" height="24" alt="CleanTalk Logo">
                    <span style="height: 24px;margin: 0 16px;text-align: center;color: #AAAAAA;border-left: 1px solid;"></span>
                    <a href="<?php echo esc_url($this->settings_link); ?>" class="apbct-banner-link">
                        <?php esc_html_e('Anti-Spam', 'cleantalk-spam-protect'); ?>
                    </a>
                </div>
                <div class="apbct-banner-content-wrapper apbct-banner-text-wrapper">
                    <div style="display: flex; gap: 32px;">
                        <img src="<?php echo esc_url($review_img_url); ?>" width="92" height="81" alt="CleanTalk Review Logo" style="align-self: center;">
                        <div>
                            <div class="apbct-banner-title">
                                <?php echo esc_html($data['title']); ?>
                            </div>
                            <div class="apbct-banner-subtitle apbct-banner-big-subtitle" style="max-width: 745px;">
                                <?php echo esc_html($data['subtitle']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="apbct-banner-button-wrapper">
                        <a href="<?php echo esc_url($data['button_url']); ?>" target="_blank" rel="noopener noreferrer" class="apbct-banner-button apbct-banner-button-green"><?php echo esc_html($data['button_text']); ?></a>
                        <a href="#" class="notice-dismiss-link apbct-banner-dismiss-link" onclick="jQuery(this).closest('.apbct-notice').find('.notice-dismiss').click(); return false;"><?php echo esc_html($data['dismiss_text']); ?></a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
