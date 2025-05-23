<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\UniversalBanner\BannerDataDto;
use Cleantalk\ApbctWP\ServerRequirementsChecker\ServerRequirementsChecker;
use Cleantalk\Common\TT;
use Cleantalk\ApbctWP\LinkConstructor;

class AdminNotices
{
    /*
     * The time interval in which notifications hidden by the user are not displayed
     */
    const DAYS_INTERVAL_HIDING_NOTICE = 14;

    /*
     * The time interval in which the review notification will be hidden for user
     */
    const DAYS_INTERVAL_HIDING_REVIEW_NOTICE = 90;

    /**
     * @var null|AdminNotices
     */
    private static $instance;

    /**
     * @var array
     */
    const NOTICES = array(
        'notice_key_is_incorrect',
        'notice_trial',
        'notice_renew',
        'notice_incompatibility',
        'notice_review',
        'notice_email_decoder_changed',
        'notice_server_requirements',
    );

    /**
     * @var State
     */
    private $apbct;

    /**
     * @var bool
     */
    private $is_cleantalk_page;

    /**
     * @var string
     */
    private $settings_link;

    /**
     * @var string
     */
    private $user_token;

    /**
     * AdminNotices constructor.
     */
    private function __construct()
    {
        global $apbct;
        $this->apbct             = $apbct;
        $this->is_cleantalk_page = Get::get('page') &&
                                   in_array(Get::get('page'), array('cleantalk', 'ct_check_spam', 'ct_check_users'));
        $this->user_token        = $this->apbct->user_token ?: '';

        $self_owned_key = $this->apbct->moderate_ip == 0 && ! defined('CLEANTALK_ACCESS_KEY');
        $is_dashboard   = is_network_admin() || is_admin();
        $is_admin       = current_user_can('activate_plugins');
        $uid            = get_current_user_id();

        if ( $self_owned_key && $is_dashboard && $is_admin ) {
            if ( defined('DOING_AJAX') ) {
                add_action('wp_ajax_cleantalk_dismiss_notice', array($this, 'setNoticeDismissed'));
            } else {
                foreach ( self::NOTICES as $notice ) {
                    $notice_uid = $notice . '_' . $uid;

                    // Notice "review" not need to show everytime in the plugin settings page
                    if ( $notice === 'notice_review' && $this->isDismissedNotice($notice_uid) ) {
                        continue;
                    }

                    if ( $this->is_cleantalk_page || ! $this->isDismissedNotice($notice_uid) ) {
                        add_action('admin_notices', array($this, $notice));
                        add_action('network_admin_notices', array($this, $notice));
                    }
                }

                add_filter('cleantalk_admin_bar__parent_node__after', array($this, 'addAttentionMark'), 20, 1);
            }
        }
    }

    /**
     * Get singleton instance of AdminNotices
     *
     * @return AdminNotices
     */
    private static function getInstance()
    {
        if ( is_null(self::$instance) ) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * Initialize method
     */
    public static function showAdminNotices()
    {
        $admin_notices = self::getInstance();

        if ( is_network_admin() ) {
            $site_url                     = get_site_option('siteurl');
            $site_url                     = preg_match('/\/$/', $site_url) ? $site_url : $site_url . '/';
            $admin_notices->settings_link = $site_url . 'wp-admin/options-general.php?page=cleantalk';
        } else {
            $admin_notices->settings_link = 'options-general.php?page=cleantalk';
        }
    }

    /**
     * Callback for the notice hook
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function notice_get_key_error() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ($this->apbct->notice_show &&
            ! empty($this->apbct->errors['key_get']) &&
            ! $this->apbct->white_label
        ) {
            $banner_data = new BannerDataDto();
            $banner_data->text = __("Unable to get Access key automatically:", 'cleantalk-spam-protect');
            $banner_data->secondary_text = end($this->apbct->errors['key_get'])['error'];

            $banner_data->button_url = LinkConstructor::buildCleanTalkLink('get_access_key_link', 'wordpress-anti-spam-plugin') .
                '&platform=wordpress&email=' . urlencode(ct_get_admin_email()) .
                '&website=' . urlencode(get_option('home'));
            $banner_data->button_text = __('Get the Access key', 'cleantalk-spam-protect');

            $banner_data->level = 'error';
            $banner_data->is_dismissible = ! $this->is_cleantalk_page;

            $banner = new ApbctUniversalBanner($banner_data);
            $banner->echoBannerBody();
        }
    }

    /**
     * Callback for the notice hook
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function notice_key_is_incorrect() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ( ! $this->apbct->white_label &&
            ! apbct_api_key__is_correct() &&
            $this->apbct->moderate_ip == 0
        ) {
            $banner_data = new BannerDataDto();
            $banner_data->type = 'key_is_incorrect';

            $banner_data->text = sprintf(
                __("Please enter the Access Key in %s plugin to enable spam protection!", 'cleantalk-spam-protect'),
                $this->apbct->plugin_name
            );

            $banner_data->button_url = $this->settings_link;
            $banner_data->button_text = __('Settings', 'cleantalk-spam-protect');

            $banner_data->level = 'error';
            $banner_data->is_dismissible = ! $this->is_cleantalk_page;

            $banner = new ApbctUniversalBanner($banner_data);
            $banner->echoBannerBody();

            $this->apbct->notice_show = false;
        }
    }

    /**
     * Callback for the notice hook
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function notice_trial() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ($this->apbct->notice_show &&
            $this->apbct->notice_trial == 1 &&
            $this->apbct->moderate_ip == 0 &&
            ! $this->apbct->white_label
        ) {
            $banner_data = new BannerDataDto();
            $banner_data->type = 'trial';

            $banner_data->text = sprintf(
                __("%s trial period ends, please upgrade to next year!", 'cleantalk-spam-protect'),
                $this->apbct->plugin_name
            );

            $banner_data->button_url = LinkConstructor::buildRenewalLink($this->user_token, 'renew_notice_trial');
            $banner_data->button_text = __('Upgrade', 'cleantalk-spam-protect');

            $banner_data->additional_text = sprintf(
                __('Account status updates every 24 hours or click Settings -> %s -> Synchronize with Cloud.', 'cleantalk-spam-protect'),
                $this->apbct->data['wl_brandname']
            );

            $banner_data->level = isset($this->apbct->data['notice_trial_level']) ? $this->apbct->data['notice_trial_level'] : 'error';
            $banner_data->is_dismissible = ! $this->is_cleantalk_page;

            $banner = new ApbctUniversalBanner($banner_data);
            $banner->echoBannerBody();

            $this->apbct->notice_show = false;
        }
    }

    /**
     * Callback for the notice hook
     * @deprecated
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function notice_renew() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ($this->apbct->notice_show &&
            $this->apbct->notice_renew == 1 &&
            $this->apbct->moderate_ip == 0 &&
            ! $this->apbct->white_label
        ) {
            $banner_data = new BannerDataDto();
            $banner_data->type = 'renew';

            $banner_data->text = __("Please renew your Anti-Spam license for next year!", 'cleantalk-spam-protect');

            $banner_data->button_url = LinkConstructor::buildRenewalLink($this->user_token, 'renew_notice_renew');
            $banner_data->button_text = __('Upgrade', 'cleantalk-spam-protect');

            $banner_data->additional_text = sprintf(
                __('Account status updates every 24 hours or click Settings -> %s -> Synchronize with Cloud.', 'cleantalk-spam-protect'),
                $this->apbct->data['wl_brandname']
            );

            $banner_data->level = isset($this->apbct->data['notice_renew_level']) ? $this->apbct->data['notice_renew_level'] : 'error';
            $banner_data->is_dismissible = ! $this->is_cleantalk_page;

            $banner = new ApbctUniversalBanner($banner_data);
            $banner->echoBannerBody();

            $this->apbct->notice_show = false;
        }
    }

    /**
     * Callback for the notice hook
     * @deprecated
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function notice_review() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ($this->apbct->notice_review == 1 &&
            ! $this->apbct->white_label
        ) {
            $banner_data = new BannerDataDto();
            $banner_data->type = 'review';

            $banner_data->text = sprintf(
                __("Help others to fight spam with %s – leave your feedback!", 'cleantalk-spam-protect'),
                $this->apbct->data['wl_brandname']
            );

            $banner_data->button_url = 'https://wordpress.org/support/plugin/cleantalk-spam-protect/reviews/?filter=5';
            $banner_data->button_text = __('SHARE YOUR FEEDBACK', 'cleantalk-spam-protect');

            $support_link = '<a href="https://wordpress.org/support/plugin/cleantalk-spam-protect/" 
                            style="display:inline-block; margin-top: 10px;"  target="_blank">'
                            . __('Still have spam?', 'cleantalk-spam-protect')
                            . '</a>';
            $close_link = '<a href="#" class="notice-dismiss-link" onclick="return false;">'
                            . __('Already posted the review', 'cleantalk-spam-protect')
                            . '</a>';
            $banner_data->additional_text = $support_link . '&nbsp;&nbsp;' . $close_link;

            $banner_data->level = isset($this->apbct->data['notice_review_level']) ? $this->apbct->data['notice_review_level'] : 'success';

            $banner = new ApbctUniversalBanner($banner_data);
            $banner->echoBannerBody();
        }
    }

    /**
     * Callback for the notice hook
     * @psalm-suppress PossiblyUnusedMethod, PossiblyUndefinedStringArrayOffset
     */
    public function notice_incompatibility() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        global $apbct;

        if ( ! empty($this->apbct->data['notice_incompatibility']) &&
            $this->is_cleantalk_page &&
            $this->apbct->settings['sfw__enabled']
        ) {
            foreach ( $this->apbct->data['notice_incompatibility'] as $notice ) {
                $banner_data = new BannerDataDto();
                $banner_data->type = 'incompatibility';

                $banner_data->text = sprintf(
                    __("Some plugin is not compatible with %s – instruction in the article.", 'cleantalk-spam-protect'),
                    $this->apbct->data['wl_brandname']
                );

                switch ($notice) {
                    case (strpos($notice, 'W3 Total Cache') !== false):
                        $banner_data->button_url = 'https://cleantalk.org/help/cleantalk-and-w3-total-cache';
                        break;
                    default:
                        $banner_data->button_url = '';
                        break;
                }

                $banner_data->button_text = __('Read the article', 'cleantalk-spam-protect');

                $banner_data->additional_text = $notice;

                $banner_data->level = 'error';
                $banner_data->is_dismissible = ! $this->is_cleantalk_page;

                $banner = new ApbctUniversalBanner($banner_data);
                $banner->echoBannerBody();
            }
        } else {
            $apbct->data['notice_incompatibility'] = array();
            $apbct->saveData();
        }
    }

    /**
     * Callback for the notice hook
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function notice_email_decoder_changed() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        global $apbct;

        if ($apbct->data['notice_email_decoder_changed'] &&
            $this->is_cleantalk_page && apbct_is_cache_plugins_exists()
        ) {
            $banner_data = new BannerDataDto();
            $banner_data->type = 'email_decoder_changed';

            $banner_data->text = __("Need to clear the cache", 'cleantalk-spam-protect');
            $banner_data->additional_text = __('You have changed the "Encode contact data" setting. If you use caching plugins, then you need to clear the cache.', 'cleantalk-spam-protect');

            $banner_data->level = 'info';
            $banner_data->is_show_button = false;

            $banner = new ApbctUniversalBanner($banner_data);
            $banner->echoBannerBody();
        }
    }

    /**
     * Show a banner if server requirements are not met
     * @psalm-suppress PossiblyUnusedMethod
     * @return void
     */
    public function notice_server_requirements() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ( ! $this->is_cleantalk_page ) {
            return;
        }

        $uid = get_current_user_id();
        $notice_uid = 'notice_server_requirements_' . $uid;
        if ($this->isDismissedNotice($notice_uid)) {
            return;
        }

        $server_checker = new ServerRequirementsChecker();
        $warnings       = $server_checker->checkRequirements();
        if (!empty($warnings)) {
            $link = LinkConstructor::buildCleanTalkLink(
                'notice_server_requirements',
                'help'
            );

            $body = __('Some server settings are incompatible', 'cleantalk-spam-protect') . ': ';
            $body .= '<ul style="margin-left:20px;list-style:disc inside;">';
            foreach ($warnings as $msg) {
                $body .= '<li style="margin-bottom:8px;">' . esc_html($msg) . '</li>';
            }
            $body .= '</ul>';
            $body .= sprintf(
                '<a href="%s">%s</a>',
                $link,
                __('Instructions for solving the compatibility issue', 'cleantalk-spam-protect')
            );

            $banner_data = new BannerDataDto();
            $banner_data->type  = 'server_requirements';
            $banner_data->level = 'error';
            $banner_data->text = __('CleanTalk Anti-Spam: Compatibility Issue Detected', 'cleantalk-spam-protect');
            $banner_data->additional_text  = $body;
            $banner_data->is_dismissible = true;

            $banner = new ApbctUniversalBanner($banner_data);
            $banner->echoBannerBody();
        }
    }

    /**
     * Check dismiss status of the notice
     *
     * @param string $notice_uid
     *
     * @return bool
     */
    private function isDismissedNotice($notice_uid)
    {
        $option_name = 'cleantalk_' . $notice_uid . '_dismissed';

        // Special for notice_review
        if (is_string($notice_uid) && strpos($notice_uid, 'notice_review')) {
            return $this->checkOptionExpired($option_name, self::DAYS_INTERVAL_HIDING_REVIEW_NOTICE);
        }

        return $this->checkOptionExpired($option_name, self::DAYS_INTERVAL_HIDING_NOTICE);
    }

    /**
     * Check option not expired
     *
     * @param string $option_name
     * @param int $expired_date
     *
     * @return bool
     */
    private function checkOptionExpired($option_name, $expired_date)
    {
        $notice_date_option = get_option($option_name);

        if ( $notice_date_option !== false && \Cleantalk\Common\Helper::dateValidate($notice_date_option) ) {
            $current_date = date_create();
            $notice_date  = date_create($notice_date_option);

            $diff = date_diff($current_date, $notice_date);

            if ( $diff->days <= $expired_date ) {
                return true;
            }
        }

        return false;
    }

    public function setNoticeDismissed()
    {
        AJAXService::checkAdminNonce();

        if ( ! Post::get('notice_id') ) {
            wp_send_json_error(esc_html__('Wrong request.', 'cleantalk-spam-protect'));
        }

        global $apbct;
        $notice       = sanitize_text_field(TT::toString(Post::get('notice_id')));
        $uid          = get_current_user_id();
        $notice_uid   = $notice . '_' . $uid;
        $current_date = current_time('Y-m-d H:i:s');

        if ( in_array(str_replace('cleantalk_', '', $notice), self::NOTICES, true) ) {
            if ( update_option($notice_uid . '_dismissed', $current_date) ) {
                if ($notice === 'cleantalk_notice_email_decoder_changed') {
                    $apbct->data['notice_email_decoder_changed'] = 0;
                    $apbct->save('data');
                }
                if ( strpos($notice, 'cleantalk_notice_review') !== false ) {
                    $api_update = API::methodUserDataUpdate($apbct->data['user_token'], json_encode(['show_review' => 0]));
                    if ( isset($api_update['error']) ) {
                        wp_send_json_error($api_update['error']);
                    }
                }
                wp_send_json_success();
            } else {
                wp_send_json_error(esc_html__('Notice status not updated.', 'cleantalk-spam-protect'));
            }
        } else {
            wp_send_json_error(esc_html__('Notice name is not allowed.', 'cleantalk-spam-protect'));
        }
    }

    /**
     * Callback for the admin-bar filtering hook
     *
     * @param string $after
     *
     * @return string
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function addAttentionMark($after)
    {
        if ( $this->apbct->notice_show ) {
            return $after . '<i class="apbct-icon-attention-alt"></i>';
        }

        return $after;
    }
}
