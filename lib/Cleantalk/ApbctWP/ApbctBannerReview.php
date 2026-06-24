<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\Common\UniversalBanner\BannerDataDto;

class ApbctBannerReview extends ApbctUniversalBanner
{
    /**
     * @var string
     */
    private $settings_link;

    /**
     * @var string
     */
    private $images_url;

    /**
     * @param BannerDataDto $banner_data
     * @param string $settings_link
     * @param string $images_url
     */
    public function __construct(BannerDataDto $banner_data, $settings_link, $images_url)
    {
        $this->settings_link = $settings_link;
        $this->images_url = rtrim($images_url, '/');
        $this->banner_type = $banner_data->type;

        // Store data without calling parent constructor (no template loading needed)
        $this->banner_data = $banner_data;
    }

    /**
     * @var BannerDataDto
     */
    private $banner_data;

    /**
     * Echoing the banner body
     * @return void
     */
    public function echoBannerBody()
    {
        $data = $this->banner_data;

        $banner_id = 'cleantalk_notice_' . $data->type;
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
                                <?php echo esc_html($data->text); ?>
                            </div>
                            <div class="apbct-banner-subtitle apbct-banner-big-subtitle" style="max-width: 745px;">
                                <?php echo esc_html($data->secondary_text); ?>
                            </div>
                        </div>
                    </div>
                    <div class="apbct-banner-button-wrapper">
                        <a href="<?php echo esc_url($data->button_url); ?>" target="_blank" rel="noopener noreferrer" class="apbct-banner-button apbct-banner-button-green"><?php echo esc_html($data->button_text); ?></a>
                        <a href="#" class="notice-dismiss-link apbct-banner-dismiss-link" onclick="jQuery(this).closest('.apbct-notice').find('.notice-dismiss').click(); return false;"><?php echo esc_html($data->additional_text); ?></a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
