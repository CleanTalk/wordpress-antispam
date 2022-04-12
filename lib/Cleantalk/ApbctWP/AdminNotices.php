<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\Variables\Get;
use Cleantalk\Variables\Post;

class AdminNotices
{
    /*
     * The time interval in which notifications hidden by the user are not displayed
     */
    const DAYS_INTERVAL_HIDING_NOTICE = 14;

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
        'notice_incompatibility'
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
                                   in_array((array)Get::get('page'), array('cleantalk', 'ct_check_spam', 'ct_check_users'));
        $this->user_token        = $this->apbct->user_token ? '&user_token=' . $this->apbct->user_token : '';

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
        if ( $this->apbct->notice_show && ! empty($this->apbct->errors['key_get']) && ! $this->apbct->white_label ) {
            $register_link =
                'https://cleantalk.org/register?platform=wordpress&email=' . urlencode(ct_get_admin_email()) .
                '&website=' . urlencode(get_option('home'));
            $content       =
                sprintf(
                    __("Unable to get Access key automatically: %s", 'cleantalk-spam-protect'),
                    end($this->apbct->errors['key_get'])['error']
                ) .
                '<a target="_blank" style="margin-left: 10px" href="' . $register_link . '">' .
                esc_html__('Get the Access key', 'cleantalk-spam-protect') .
                '</a>';
            $id            = 'cleantalk_' . __FUNCTION__;
            $this->generateNoticeHtml($content, $id);
        }
    }

    /**
     * Callback for the notice hook
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function notice_key_is_incorrect() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ( ( ! apbct_api_key__is_correct() && $this->apbct->moderate_ip == 0) && ! $this->apbct->white_label ) {
            $content = sprintf(
                __("Please enter the Access Key in %s plugin to enable spam protection!", 'cleantalk-spam-protect'),
                "<a href='{$this->settings_link}'>{$this->apbct->plugin_name}</a>"
            );
            $id      = 'cleantalk_' . __FUNCTION__;
            $this->generateNoticeHtml($content, $id);
            $this->apbct->notice_show = false;
        }
    }

    /**
     * Callback for the notice hook
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function notice_trial() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ( $this->apbct->notice_show && $this->apbct->notice_trial == 1 && $this->apbct->moderate_ip == 0 && ! $this->apbct->white_label ) {
            $content            = sprintf(
                __("%s trial period ends, please upgrade to %s!", 'cleantalk-spam-protect'),
                "<a href='{$this->settings_link}'>" . $this->apbct->plugin_name . "</a>",
                "<a href=\"https://cleantalk.org/my/bill/recharge?utm_source=wp-backend&utm_medium=cpc&utm_campaign=WP%20backend%20trial$this->user_token&cp_mode=antispam\" target=\"_blank\"><b>premium version</b></a>"
            );
            $additional_content =
                '<h4 style = "color: gray">' .
                esc_html__('Account status updates every 24 hours.', 'cleantalk-spam-protect') .
                '</h4>';
            $id                 = 'cleantalk_' . __FUNCTION__;
            $this->generateNoticeHtml($content, $id, $additional_content);
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
        if ( $this->apbct->notice_show && $this->apbct->notice_renew == 1 && $this->apbct->moderate_ip == 0 && ! $this->apbct->white_label ) {
            $renew_link  = "<a href=\"https://cleantalk.org/my/bill/recharge?utm_source=wp-backend&utm_medium=cpc&utm_campaign=WP%%20backend%%20renew$this->user_token&cp_mode=antispam\" target=\"_blank\">%s</a>";
            $button_html = sprintf(
                $renew_link,
                '<input type="button" class="button button-primary" value="' . __(
                    'RENEW ANTI-SPAM',
                    'cleantalk-spam-protect'
                ) . '"  />'
            );
            $link_html   = sprintf($renew_link, "<b>" . __('next year', 'cleantalk-spam-protect') . "</b>");

            $content            = sprintf(
                __("Please renew your Anti-Spam license for %s.", 'cleantalk-spam-protect'),
                $link_html
            );
            $additional_content =
                '<h4 style = "color: gray">' .
                esc_html__('Account status updates every 24 hours.', 'cleantalk-spam-protect') .
                '</h4>' .
                $button_html;
            $id                 = 'cleantalk_' . __FUNCTION__;
            $this->generateNoticeHtml($content, $id, $additional_content);
            $this->apbct->notice_show = false;
        }
    }

    /**
     * Callback for the notice hook
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function notice_incompatibility() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        global $apbct;
        if ( ! empty($this->apbct->data['notice_incompatibility']) && $this->is_cleantalk_page && $this->apbct->settings['sfw__enabled'] ) {
            foreach ( $this->apbct->data['notice_incompatibility'] as $notice ) {
                $this->generateNoticeHtml($notice);
            }
        } else {
            $apbct->data['notice_incompatibility'] = array();
            $apbct->saveData();
        }
    }

    /**
     * Generate and output the notice HTML
     *
     * @param string $content Any HTML allowed
     * @param string $id
     * @param string $additional_content
     */
    private function generateNoticeHtml($content, $id = '', $additional_content = '')
    {
        $notice_classes = $this->is_cleantalk_page ? 'apbct-notice notice notice-error' : 'apbct-notice notice notice-error is-dismissible';
        $notice_id      = ! empty($id) ? 'id="' . $id . '"' : '';

        echo '<div class="' . $notice_classes . '" ' . $notice_id . '>
				<h3>' . $content . '</h3>
				' . $additional_content . '
			  </div>';
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
        $notice_date_option = get_option('cleantalk_' . $notice_uid . '_dismissed');

        if ( $notice_date_option !== false && \Cleantalk\Common\Helper::dateValidate($notice_date_option) ) {
            $current_date = date_create();
            $notice_date  = date_create(get_option('cleantalk_' . $notice_uid . '_dismissed'));

            $diff = date_diff($current_date, $notice_date);

            if ( $diff->days <= self::DAYS_INTERVAL_HIDING_NOTICE ) {
                return true;
            }
        }

        return false;
    }

    public function setNoticeDismissed()
    {
        check_ajax_referer('ct_secret_nonce');

        if ( ! Post::get('notice_id') ) {
            wp_send_json_error(esc_html__('Wrong request.', 'cleantalk-spam-protect'));
        }

        $notice       = sanitize_text_field(Post::get('notice_id'));
        $uid          = get_current_user_id();
        $notice_uid   = $notice . '_' . $uid;
        $current_date = current_time('Y-m-d');

        if ( in_array(str_replace('cleantalk_', '', $notice), self::NOTICES, true) ) {
            if ( update_option($notice_uid . '_dismissed', $current_date) ) {
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
