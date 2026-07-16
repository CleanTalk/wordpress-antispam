<?php

namespace Cleantalk\ApbctWP\AdminBannersModule;

use Cleantalk\ApbctWP\LinkConstructor;

class AdminBannerTrialAndRenewFullpage extends AdminBannerAbstract
{
    /**
     * Simple Banner Name, must be unique
     */
    const NAME = 'trial_fullpage';

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

    public function __construct()
    {
        global $apbct;

        $this->apbct = $apbct;

        $this->banner_id = $this->prefix . $this::NAME;

        $user_token = $apbct->user_token ?: '';
        $renewal_link = LinkConstructor::buildRenewalLink(
            $user_token,
            'renew_notice_trial'
        );

        $settings_link = is_network_admin()
            ? rtrim(get_site_option('siteurl'), '/') . '/wp-admin/options-general.php?page=cleantalk'
            : 'options-general.php?page=cleantalk';

        $this->template_data = array(
            'renewal_link' => $renewal_link,
            'plugin_settings_link' => $settings_link,
            'title_main' => __(
                'Upgrade Your License to Keep Your Site Secure',
                'cleantalk-spam-protect'
            ),
            'subtitle' => __(
                'Trial period is now over, please upgrade to premium version to keep your site secure and safe!',
                'cleantalk-spam-protect'
            ),
            'network_statistics_title' => (
                __('Our Protection Network Statistics:', 'cleantalk-spam-protect')
            ),
            'network_statistics_files_scanned' => __(
                'files scanned for malicious code',
                'cleantalk-spam-protect'
            ),
            'network_statistics_trusted_sites' => __(
                'sites trust CleanTalk',
                'cleantalk-spam-protect'
            ),
            'network_statistics_protected_websites' => __(
                'security attacks successfully blocked across all protected websites',
                'cleantalk-spam-protect'
            ),
            'network_statistics_brute_force' => __(
                'brute-force attack attempts prevented',
                'cleantalk-spam-protect'
            ),
            'upgrade_benefits_title' => __(
                'What you get when you upgrade:',
                'cleantalk-spam-protect'
            ),
            'upgrade_benefits_top_rated' => __(
                'Top-rated spam and bot protection for WordPress. ',
                'cleantalk-spam-protect'
            ),
            'upgrade_benefits_protects_plugins' => __(
                'CleanTalk protects',
                'cleantalk-spam-protect'
            ),
            'upgrade_benefits_protects_plugins_bold' => __(
                '60+ WordPress',
                'cleantalk-spam-protect'
            ),
            'upgrade_benefits_protects_plugins_after' => __(
                'plugins for contact forms, bookings, events, surveys, subscriptions, and more.',
                'cleantalk-spam-protect'
            ),
            'upgrade_benefits_no_captchas' => __(
                'No CAPTCHAs, no puzzles, and',
                'cleantalk-spam-protect'
            ),
            'upgrade_benefits_spam_detection_network' => __(
                'Powered by a global spam detection network.',
                'cleantalk-spam-protect'
            ),
            'title_upd' => sprintf(
                __(
                    'Account status updates every hour or click Settings → Anti-Spam by CleanTalk → Synchronize with Cloud',
                    'cleantalk-spam-protect'
                ),
                $apbct->data["wl_brandname"] ? $apbct->data["wl_brandname"] : 'Anti-Spam by CleanTalk'
            ),
            'btn_upgrade' => esc_html__('UPGRADE NOW', 'cleantalk-spam-protect'),
        );
    }

    /**
     * Always return false so the banner is never considered persistently dismissed.
     * Closing the banner hides it only for the current page session (JS side).
     *
     * @return bool
     */
    protected function isDismissed()
    {
        return false;
    }

    /**
     * Check if fullpage trial banner needs to be shown.
     * Called from settings page to decide whether to render settings or this banner.
     *
     * @return bool
     */
    public function needToShow()
    {
        if (
            $this->apbct->notice_show &&
            ($this->apbct->notice_trial == 1 || $this->apbct->notice_renew == 1) &&
            $this->apbct->moderate_ip == 0 &&
            ! $this->apbct->white_label
        ) {
            return true;
        }

        return false;
    }

    /**
     * Render the fullpage banner (wizard-style card layout).
     * @psalm-suppress PossiblyUndefinedStringArrayOffset
     */
    public function display()
    {
        $data = $this->template_data;
        $banner_id = $this->banner_id;
        ?>
        <style>
            #wpfooter {
                display: none;
            }
        </style>
        <script>
            if (!document.referrer || document.referrer.indexOf('page=cleantalk') === -1) {
                sessionStorage.removeItem('apbct_trial_fullpage_dismissed');
            }
            if (sessionStorage.getItem('apbct_trial_fullpage_dismissed')) {
                document.write('<style>.apbct-trial-renew-fullpage{display:none !important;}</style>');
                document.addEventListener('DOMContentLoaded', function() {
                    var settingsWrap = document.getElementById('apbct-settings-page-wrap');
                    if (settingsWrap) settingsWrap.style.display = '';
                });
            }
        </script>
        <div class="apbct-trial-renew-fullpage">
            <div
                class="error um-admin-notice notice is-dismissible apbct-banner-error apbct-trial-renew-fullpage-notice"
                id="<?php echo esc_attr($banner_id); ?>"
            >
                <div class="apbct-banner-wrapper">
                    <div style="display: flex;">
                        <img src="<?php echo esc_url(APBCT_IMG_ASSETS_PATH . '/logo-cleantalk1.svg'); ?>" width="111px" height="24">
                        <span style="height: 24px;margin: 0 16px;text-align: center;color: #AAAAAA;border-left: 1px solid;"></span>
                        <a href="<?php echo esc_url($data['plugin_settings_link']); ?>" class="apbct-banner-link">
                            <?php esc_html_e('Anti-Spam', 'cleantalk-spam-protect'); ?>
                        </a>
                    </div>

                    <div class="apbct-trial-renew-fullpage-body">
                        <div class="apbct-banner-title">
                            <?php echo esc_html($data['title_main']); ?>
                        </div>
                        <div class="apbct-banner-subtitle">
                            <?php echo esc_html($data['subtitle']); ?>
                        </div>
                        <div class="apbct-banner-desc-blocks-content">
                            <div class="apbct-banner-desc-blocks">
                                <div class="apbct-banner-background-container">
                                    <div class="apbct-banner-desc-block-title">
                                        <?php echo esc_html($data['network_statistics_title']); ?>
                                    </div>
                                    <div class="apbct-banner-desc-block-row">
                                        <span class="apbct-banner-red-point"></span>
                                        <span class="apbct-banner-desc-block-title apbct-banner-stat-value">1,079,000+</span>
                                        <span class="apbct-banner-subtitle apbct-banner-desc-block-text">
                                            <?php echo esc_html($data['network_statistics_trusted_sites']); ?>
                                        </span>
                                    </div>
                                    <div class="apbct-banner-desc-block-row">
                                        <span class="apbct-banner-red-point"></span>
                                        <span class="apbct-banner-desc-block-title apbct-banner-stat-value">10,450,238</span>
                                        <span class="apbct-banner-subtitle apbct-banner-desc-block-text">
                                            <?php echo esc_html($data['network_statistics_protected_websites']); ?>
                                        </span>
                                    </div>
                                    <div class="apbct-banner-desc-block-row">
                                        <span class="apbct-banner-red-point"></span>
                                        <span class="apbct-banner-desc-block-title apbct-banner-stat-value">1,399,842</span>
                                        <span class="apbct-banner-subtitle apbct-banner-desc-block-text">
                                            <?php echo esc_html($data['network_statistics_brute_force']); ?>
                                        </span>
                                    </div>
                                    <div class="apbct-banner-desc-block-row">
                                        <span class="apbct-banner-red-point"></span>
                                        <span class="apbct-banner-desc-block-title apbct-banner-stat-value">60,645,183</span>
                                        <span class="apbct-banner-subtitle apbct-banner-desc-block-text">
                                            <?php echo esc_html($data['network_statistics_files_scanned']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="apbct-banner-background-container">
                                    <div class="apbct-banner-desc-block-title">
                                        <?php echo esc_html($data['upgrade_benefits_title']); ?>
                                    </div>
                                    <div class="apbct-banner-desc-block-row" style="gap: 12px;">
                                        <img src="<?php echo esc_url(APBCT_IMG_ASSETS_PATH . '/check.svg'); ?>" width="20" height="20">
                                        <span class="apbct-banner-subtitle" style="margin-top: 0px;"><?php echo esc_html($data['upgrade_benefits_top_rated']); ?></span>
                                    </div>
                                    <div class="apbct-banner-desc-block-row" style="gap: 12px;">
                                        <img src="<?php echo esc_url(APBCT_IMG_ASSETS_PATH . '/check.svg'); ?>" width="20" height="20">
                                        <span class="apbct-banner-subtitle" style="margin-top: 0px;">
                                            <?php echo esc_html($data['upgrade_benefits_protects_plugins']); ?>
                                            <span style="font-weight:bold;"><?php echo esc_html($data['upgrade_benefits_protects_plugins_bold']); ?></span>
                                            <?php echo esc_html($data['upgrade_benefits_protects_plugins_after']); ?>
                                        </span>
                                    </div>
                                    <div class="apbct-banner-desc-block-row" style="gap: 12px;">
                                        <img src="<?php echo esc_url(APBCT_IMG_ASSETS_PATH . '/check.svg'); ?>" width="20" height="20">
                                        <span class="apbct-banner-subtitle" style="margin-top: 0px;">
                                            <?php echo esc_html($data['upgrade_benefits_no_captchas']); ?>
                                            <span style="font-weight:bold;">
                                                <?php echo __('no visitor friction.', 'cleantalk-spam-protect'); ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="apbct-banner-desc-block-row" style="gap: 12px;">
                                        <img src="<?php echo esc_url(APBCT_IMG_ASSETS_PATH . '/check.svg'); ?>" width="20" height="20">
                                        <span class="apbct-banner-subtitle" style="margin-top: 0px;"><?php echo esc_html($data['upgrade_benefits_spam_detection_network']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <img class="apbct-trial-renew-fullpage-image" src="<?php echo esc_url(APBCT_IMG_ASSETS_PATH . '/img_fullpage_trial_banner.svg'); ?>">
                        </div>
                        <div class="apbct-banner-subtitle">
                            <?php echo esc_html($data['title_upd']); ?>
                        </div>
                        <div class="apbct-banner-button-wrapper" style="display: flex;margin-top: 32px;margin-left: 0;">
                            <a href="<?php echo esc_url($data['renewal_link']); ?>" target="_blank" class="apbct-banner-button apbct-banner-button-red"><?php echo esc_html($data['btn_upgrade']); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
