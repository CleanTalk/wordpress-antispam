<?php

namespace Cleantalk\ApbctWP\State;

use Cleantalk\Common\State\Options;

class Data extends Options
{
    /**
     * @inheritDoc
     */
    protected function setDefaults()
    {
        return array(

            // Plugin data
            'plugin_version'                 => APBCT_VERSION,
            'js_keys'                        => array(), // Keys to do JavaScript antispam test
            'js_keys_store_days'             => 14, // JavaScript keys store days - 8 days now
            'js_key_lifetime'                => 86400, // JavaScript key life time in seconds - 1 day now
            'last_remote_call'               => 0, //Timestam of last remote call
            'current_settings_template_id'   => null,  // Loaded settings template id
            'current_settings_template_name' => null,  // Loaded settings template name

            // Antispam
            'spam_store_days'                => 15, // Days before delete comments from folder Spam
            'relevance_test'                 => 0, // Test comment for relevance
            'notice_api_errors'              => 0, // Send API error notices to WP admin

            // Account data
            'service_id'                     => 0,
            'moderate'                       => 0,
            'moderate_ip'                    => 0,
            'ip_license'                     => 0,
            'spam_count'                     => 0,
            'auto_update'                    => 0,
            'user_token'                     => '', // User token for auto login into spam statistics
            'license_trial'                  => 0,

            // Notices
            'notice_show'                    => 0,
            'notice_trial'                   => 0,
            'notice_renew'                   => 0,
            'notice_review'                  => 0,
            'notice_auto_update'             => 0,
            'notice_incompatibility'         => array(),

            // Brief data
            'brief_data'                     => array(
                'spam_stat'    => array(),
                'top5_spam_ip' => array(),
            ),

            'array_accepted'              => array(),
            'array_blocked'               => array(),
            'current_hour'                => '',
            'admin_bar__sfw_counter'      => array(
                'all'     => 0,
                'blocked' => 0,
            ),
            'admin_bar__all_time_counter' => array(
                'accepted' => 0,
                'blocked'  => 0,
            ),
            'user_counter'                => array(
                'accepted' => 0,
                'blocked'  => 0,
                // 'since' => date('d M'),
            ),
            'connection_reports'          => array(
                'success'         => 0,
                'negative'        => 0,
                'negative_report' => array(),
                // 'since'        => date('d M'),
            ),

            // A-B tests
            'ab_test'                     => array(
                'sfw_enabled' => false,
            ),

            // Misc
            'feedback_request'            => '',
            'key_is_ok'                   => 0,
            'salt'                        => '',

        );
    }
}
