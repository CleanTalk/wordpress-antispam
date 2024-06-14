<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;

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
        'notice_email_decoder_changed'
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
        global $apbct;

        if (
                $this->apbct->notice_show &&
                $this->apbct->notice_trial == 1 && //the flag!
                $this->apbct->moderate_ip == 0 &&
                ! $this->apbct->white_label
        ) {
            /*
             * Generate main content
             */

            //prepare plugin settings link
            $plugin_settings_link = '<a href="' . $this->settings_link . '">' . $this->apbct->plugin_name . '</a>';

            //prepare renewal link
            $link_text = "<b>" . __('next year', 'cleantalk-spam-protect') . "</b>";
            $renew_link  = static::generateRenewalLinkHTML($this->user_token, $link_text, 1);

            //construct main content
            $content            = sprintf(
                __("%s trial period ends, please upgrade to %s!", 'cleantalk-spam-protect'),
                $plugin_settings_link,
                $renew_link
            );

            /*
             * Generate additional content.
             */

            $additional_content = static::generateUpdatingStatusContent($apbct->data['wl_brandname']);

            /*
             * Process layout
             */

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
        global $apbct;

        if (
                $this->apbct->notice_show &&
                $this->apbct->notice_renew == 1 && //the flag!
                $this->apbct->moderate_ip == 0 &&
                ! $this->apbct->white_label
        ) {
            /*
             * Generate main content
             */

            // Prepare the string-like renewal link for main content.
            $link_text = "<b>" . __('next year', 'cleantalk-spam-protect') . "</b>";
            $renew_link = static::generateRenewalLinkHTML($this->user_token, $link_text, 1);

            $content            = sprintf(
                __("Please renew your Anti-Spam license for %s.", 'cleantalk-spam-protect'),
                $renew_link
            );

            /*
             * Generate additional content.
             */

            // Prepare the renewal button - will be added to the bottom of notice
            $button_text = __('RENEW ANTI-SPAM', 'cleantalk-spam-protect');
            $button_html = '<input type="button" class="button button-primary" style="margin-bottom:20px" value="' . $button_text . '"  />';
            $button_html = static::generateRenewalLinkHTML($this->user_token, $button_html, 1);

            $additional_content = static::generateUpdatingStatusContent($apbct->data['wl_brandname_short']);
            // add the button to the additional content - todo:: bad pactice, we should have a special place for buttons
            $additional_content .= $button_html;

            /*
             * Process layout
             */
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
    public function notice_review() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        global $apbct;

        if ( $this->apbct->notice_review == 1 && ! $this->apbct->white_label ) {
            $review_link = "<a class='button' href='https://wordpress.org/support/plugin/cleantalk-spam-protect/reviews/?filter=5' target='_blank'>"
                                . __('SHARE YOUR FEEDBACK', 'cleantalk-spam-protect') .
                            "</a>";
            $support_link = '<a href="https://wordpress.org/support/plugin/cleantalk-spam-protect/" 
                            style="display:inline-block; margin-top: 10px;"  target="_blank">'
                            . __('Still have spam?', 'cleantalk-spam-protect')
                            . '</a>';
            $close_link = '<a href="#" class="notice-dismiss-link" onclick="return false;">'
                            . __('Already posted the review', 'cleantalk-spam-protect')
                            . '</a>';
            $notice_text = __('Help others to fight spam – leave your feedback!', 'cleantalk-spam-protect');
            $content = '<div class="apbct-notice notice notice-success is-dismissible" id="cleantalk_notice_review">
                            <div class="flex-row">
                                <h3>'
                                    . $notice_text .
                                '</h3>
                                <p class="caption">'
                                    . $apbct->data['wl_brandname'] .
                                '</p>
                            </div>'
                            . '<div id="cleantalk_notice_review">'
                            . $review_link . '&nbsp;&nbsp;'
                            . $support_link . '&nbsp;&nbsp;'
                            . $close_link . '&nbsp;'
                            . '</div>'
                        . '</div>';

            echo $content;
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
     * Callback for the notice hook
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function notice_email_decoder_changed() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        global $apbct;
        if ($apbct->data['notice_email_decoder_changed'] && $this->is_cleantalk_page && apbct_is_cache_plugins_exists()) { ?>
            <div class="apbct-notice um-admin-notice notice notice-info apbct-plugin-errors is-dismissible"
                 id="cleantalk_notice_email_decoder_changed" style="position: relative;">
            <h3>
                <?php echo esc_html__('Need to clear the cache', 'cleantalk-spam-protect'); ?>
            </h3>
            <h4 style="color: gray;">
                <?php echo esc_html__('You have changed the "Encode contact data" setting. If you use caching plugins, then you need to clear the cache.', 'cleantalk-spam-protect'); ?>
            </h4>
            </div>
        <?php }
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
        check_ajax_referer('ct_secret_nonce');

        if ( ! Post::get('notice_id') ) {
            wp_send_json_error(esc_html__('Wrong request.', 'cleantalk-spam-protect'));
        }

        global $apbct;
        $notice       = sanitize_text_field(Post::get('notice_id'));
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

    private static function generateUpdatingStatusContent($wl_brandname)
    {
        $additional_content =
            '<h4 style = "color: gray">' .
            esc_html__('Account status updates every 24 hours or click Settings -> ' . $wl_brandname . ' -> Synchronize with Cloud.', 'cleantalk-spam-protect') .
            '</h4>';
        return $additional_content;
    }

    public static function generateRenewalLinkHTML($user_token, $link_inner_html, $product_id, $utm_marks = array())
    {
        $domain = 'https://p.cleantalk.org';
        //prepare utm marks
        $utm_marks = array(
            'utm_source' => !empty($utm_marks['utm_source']) ? $utm_marks['utm_source'] : 'wp-backend',
            'utm_medium' => !empty($utm_marks['utm_medium']) ? $utm_marks['utm_medium'] : 'cpc',
            'utm_campaign' => !empty($utm_marks['utm_campaign']) ? $utm_marks['utm_campaign'] : 'WP%%20backend%%20trial_antispam',
        );
        //prepare query
        $query = http_build_query(array(
                'product_id' => $product_id,
                'featured' => '',
                'user_token' => Escape::escHtml($user_token),
                'utm_source' => $utm_marks['utm_source'],
                'utm_medium' => $utm_marks['utm_medium'],
                'utm_campaign' => $utm_marks['utm_campaign'],
        ));
        //prepare link
        $renewal_link  = '<a href="' . $domain . '/?' . $query . '" target="_blank">' . $link_inner_html . '</a>';
        return $renewal_link;
    }
}
