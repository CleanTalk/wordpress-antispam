<?php

namespace Cleantalk\ApbctWP\AdminBannersModule;

use Cleantalk\ApbctWP\LinkConstructor;
use Cleantalk\ApbctWP\Variables\Get;

class AdminBannerTrialAndRenew extends AdminBannerAbstract
{
    /**
     * Simple Banner Name, must be unique
     */
    const NAME = 'notice_trial';

    /**
     * Data for template
     *
     * @var array<string, string> $template_data
     */
    private $template_data;

    /**
     * @var \Cleantalk\ApbctWP\State
     */
    private $apbct;

    /**
     * Is the current page a cleantalk plugin settings page
     *
     * @var bool
     */
    private $is_settings_page;

    public function __construct()
    {
        global $apbct;

        $this->apbct = $apbct;

        $this->banner_id = $this->prefix . $this::NAME;

        $user_token = $apbct->user_token ?: '';
        $renewal_link = LinkConstructor::buildRenewalLink(
            $user_token,
            $apbct->notice_trial == 1 ? 'renew_notice_trial' : 'renew_notice_renew'
        );

        $settings_link = is_network_admin()
            ? rtrim(get_site_option('siteurl'), '/') . '/wp-admin/options-general.php?page=cleantalk'
            : 'options-general.php?page=cleantalk';

        $this->template_data = array(
            'renewal_link' => $renewal_link,
            'plugin_settings_link' => $settings_link,
            'title_main' => __(
                'Please upgrade your license to keep your site secure and protected!',
                'cleantalk-spam-protect'
            ),
            'title_numbers' => sprintf(
                __('Trusted by %s sites | %s attacks blocked | %s brute-force prevented', 'cleantalk-spam-protect'),
                '1,079,000+',
                '10.5M+',
                '1.4M+'
            ),
            'title_upd' => sprintf(
                __(
                    'Account status updates every hour or click Settings → %s → Synchronize with Cloud',
                    'cleantalk-spam-protect'
                ),
                $apbct->data["wl_brandname"] ?
                    $apbct->data["wl_brandname"] :
                    __('Anti-Spam by CleanTalk', 'cleantalk-spam-protect')
            ),
            'btn_upgrade' => esc_html__('UPGRADE NOW', 'cleantalk-spam-protect'),
        );

        $this->is_settings_page = in_array(
            Get::getString('page'),
            array('cleantalk', 'ct_check_spam', 'ct_check_users')
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
            $this->apbct->notice_show &&
            ($this->apbct->notice_trial == 1 || $this->apbct->notice_renew == 1) &&
            $this->apbct->moderate_ip == 0 &&
            ! $this->apbct->white_label &&
            (! $this->isDismissed() || $this->is_settings_page)
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
        $is_dismissible_text = $this->is_settings_page ? '' : 'is-dismissible';
        $div_head = '<div class="apbct-notice error um-admin-notice notice apbct-banner-error '
            . $is_dismissible_text
            . '" id="'
            . $this->banner_id
            . '">';
        echo $div_head;
        ?>
            <div class="apbct-banner-content">
                <div class="apbct-banner-wrapper">
                    <div style="display: flex;">
                        <img src="<?php echo esc_url(APBCT_IMG_ASSETS_PATH . '/logo-cleantalk1.svg'); ?>" width="111px" height="24">
                        <span style="height: 24px;margin: 0 16px;text-align: center;color: #AAAAAA;border-left: 1px solid;"></span>
                        <a href="<?php echo esc_url($data['plugin_settings_link']); ?>" class="apbct-banner-link">
                            <?php esc_html_e('Anti-Spam', 'cleantalk-spam-protect'); ?>
                        </a>
                    </div>

                    <div class="apbct-banner-text-wrapper">
                        <div class="apbct-banner-title">
                            <?php echo esc_html($data['title_main']); ?>
                        </div>
                        <div class="apbct-banner-subtitle">
                            <?php echo esc_html($data['title_numbers']); ?>
                        </div>
                        <div class="apbct-banner-subtitle apbct-banner-big-subtitle" style="margin-top: 8px;">
                            <?php echo esc_html($data['title_upd']); ?>
                        </div>
                    </div>
                </div>
                <div class="apbct-banner-button-wrapper">
                    <a href="<?php echo esc_url($data['renewal_link']); ?>" target="_blank" class="apbct-banner-button apbct-banner-button-red"><?php echo esc_html($data['btn_upgrade']); ?></a>
                </div>
            </div>

        <?php
        echo '</div>';
    }
}
