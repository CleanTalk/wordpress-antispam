<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\Common\TT;

class LinkConstructor extends \Cleantalk\Common\LinkConstructor
{
    //todo search unhadled links via comment //HANLDE LINK

    /**
     * @var string
     */
    public static $utm_campaign = 'apbct_links';

    /**
     * @var array[]
     * @see \Cleantalk\Common\LinkConstructor::$utm_presets
     */
    public static $utm_presets = array(
        /*
         * Settings
         */
        'settings_top_info' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'apbct_options',
            'utm_content' => 'settings_top_info',
        ),
        'anti_crawler_inactive' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'apbct_options',
            'utm_content' => 'anti_crawler_inactive',
        ),
        'dashboard_widget_all_data_link' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'dashboard_widget',
            'utm_content' => 'view_all',
        ),
        'dashboard_widget_go_to_cp' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'dashboard_widget',
            'utm_content' => 'go_to_cp',
        ),
        'help_wl_multisite' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'settings',
            'utm_content' => 'help_wl_multisite',
        ),
        /*
         * Public pages
         */
        'public_widget_referal_link' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'public',
            'utm_medium' => 'widget',
            'utm_content' => 'referal_link',
        ),
        'public_comments_page_go_to_cp' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'public',
            'utm_medium' => 'comments_page',
            'utm_content' => 'go_to_cp',
        ),
        /*
         * Emails
         */
        //https://cleantalk.org/my/?cp_mode=antispam&utm_source=newsletter&utm_medium=email&utm_campaign=wp_spam_registration_passed
        'email_wp_spam_registration_passed' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_content' => 'wp_spam_registration_passed',
        ),
        /*
         * Renewal links
         * todo All the renewal uses the same utm - make them unique
         */
        'renew_notice_trial' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'banner',
            'utm_content' => 'renew_notice_trial',
        ),
        'renew_notice_renew' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'banner',
            'utm_content' => 'renew_notice_renew',
        ),
        'renew_notice_renew_button' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'banner',
            'utm_content' => 'renew_notice_renew',
        ),
        'renew_checkers' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'badge',
            'utm_content' => 'renew_checkers',
        ),
        'renew_top_info' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'badge',
            'utm_content' => 'renew_notice_trial',
        ),
        'renew_plugins_listing' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'badge',
            'utm_content' => 'renew_plugins_listing',
        ),
        'renew_admin_bar_apbct' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'admin_bar',
            'utm_content' => 'renew_admin_bar',
        ),
        'renew_admin_bar_spbct' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'admin_bar',
            'utm_content' => 'renew_admin_bar_spbct',
        ),
        'exclusion_by_form_signs' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'settings',
            'utm_content' => 'apbct_hint_exclusions__form_signs',
        ),
        'faq_admin_bar_apbct' => array(
            'utm_id' => '',
            'utm_campaign' => 'help',
            'utm_term' => 'faq',
            'utm_source' => 'cleantalk.org',
            'utm_medium' => 'dashboard',
            'utm_content' => 'top_menu_link',
        ),
        'footer_trusted_link' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'cleantalk.org',
            'utm_medium' => 'footer',
            'utm_content' => 'footer_trusted_link',
        ),
        'get_access_key_link' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'settings',
            'utm_content' => 'get_access_key_link',
        ),
        'trp_learn_more_link' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'admin_side',
            'utm_medium' => 'trp_badge',
            'utm_content' => 'trp_badge_link_click',
        ),
        'blog_email_encoder_common_post' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'apbct_hint_data__email_decoder',
            'utm_medium' => 'WordPress',
            'utm_content' => '',
            'utm_campaign' => 'ABPCT_Settings',
        ),
        'settings_footer__spbct_link' => array(
            'utm_id' => '',
            'utm_term' => 'cleantalk security for websites',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'wordpress',
            'utm_content' => 'footer',
            'utm_campaign' => 'antispam',
        ),
        'settings_footer__uptime_monitoring_link' => array(
            'utm_id' => '',
             'utm_term' => 'uptime monitoring',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'wordpress',
            'utm_content' => 'footer',
            'utm_campaign' => 'antispam',
        ),
        'settings_footer__doboard_link' => array(
            'utm_id' => '',
             'utm_term' => 'doboard - online project management',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'wordpress',
            'utm_content' => 'footer',
            'utm_campaign' => 'antispam',
        ),
        'notice_server_requirements' => array(
            'utm_id' => '',
            'utm_term' => '',
            'utm_source' => 'admin_panel',
            'utm_medium' => 'settings',
            'utm_content' => 'antispam',
        ),
    );

    public static function buildCleanTalkLink($utm_preset, $uri = '', $get_params = array(), $domain = 'https://cleantalk.org')
    {
        return parent::buildCleanTalkLink($utm_preset, $uri, $get_params, $domain);
    }

    public static function buildRenewalLinkATag($user_token, $link_inner_html, $product_id, $utm_preset)
    {
        return parent::buildRenewalLinkATag($user_token, $link_inner_html, $product_id, $utm_preset);
    }

    public static function buildRenewalLink($user_token, $utm_content)
    {
        return 'https://p.cleantalk.org/?product_id=1&featured=&user_token='
            . $user_token
            . '&utm_id=&utm_term=payment&utm_source=admin_panel&utm_medium=wordpress&utm_content='
            . $utm_content
            . '&utm_campaign=apbct_links';
    }

    /**
     * @param $href
     * @param $link_word
     *
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function buildSimpleHref($href, $link_word)
    {
        return '<a href="' . esc_html(TT::toString($href)) . '" target="_blank">' . esc_html(TT::toString($link_word)) . '</a>';
    }
}
