<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\Firewall\SFWUpdateHelper;

class Deactivator
{
    public static function deactivation($network_wide)
    {
        global $apbct, $wpdb;
        if ( ! is_multisite() ) {
            // Deactivation on standalone blog

            self::deleteTables($wpdb->prefix);
            delete_option('cleantalk_cron'); // Deleting cron entries

            if ( $apbct->settings['misc__complete_deactivation'] ) {
                self::deleteAllOptions();
                self::deleteMeta();
                self::deleteSFWUpdateFolder();
            }
        } elseif ( $network_wide ) {
            // Deactivation for network

            $initial_blog = get_current_blog_id();
            $blogs        = array_keys($wpdb->get_results('SELECT blog_id FROM ' . $wpdb->blogs, OBJECT_K));
            foreach ( $blogs as $blog ) {
                switch_to_blog($blog);
                self::deleteTables($wpdb->get_blog_prefix($blog));
                delete_option('cleantalk_cron'); // Deleting cron entries

                if ( $apbct->settings['misc__complete_deactivation'] ) {
                    self::deleteAllOptions();
                    self::deleteMeta();
                    self::deleteAllOptionsInNetwork();
                    self::deleteSFWUpdateFolder();
                }
            }
            switch_to_blog($initial_blog);
        } else {
            // Deactivation for blog

            self::deleteTables($wpdb->prefix);
            delete_option('cleantalk_cron'); // Deleting cron entries

            if ( $apbct->settings['misc__complete_deactivation'] ) {
                self::deleteAllOptions();
                self::deleteMeta();
                self::deleteSFWUpdateFolder();
            }
        }
    }

    /**
     * Delete all cleantalk_* entries from _options table
     */
    public static function deleteAllOptions()
    {
        global $wpdb;

        $wild = '%';
        $find = 'cleantalk';
        $like = $wild . $wpdb->esc_like($find) . $wild;
        $sql  = $wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name LIKE %s", $like);

        $wpdb->query($sql);
    }

    /**
     * Delete all cleantalk_* entries from _sitemeta table
     */
    public static function deleteAllOptionsInNetwork()
    {
        delete_site_option('cleantalk_network_settings');
        delete_site_option('cleantalk_network_data');
    }

    /**
     * Delete tables from DB
     */
    public static function deleteTables($prefix)
    {
        global $wpdb;
        $wpdb->query('DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_sfw`;');           // Deleting SFW data
        $wpdb->query('DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_sfw_personal`;');           // Deleting SFW data
        $wpdb->query('DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_sfw_logs`;');      // Deleting SFW logs
        $wpdb->query('DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_sfw__flood_logs`;');   // Deleting SFW logs
        $wpdb->query('DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_ac_log`;');      // Deleting SFW logs
        $wpdb->query('DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_sessions`;');      // Deleting session table
        $wpdb->query(
            'DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_spamscan_logs`;'
        ); // Deleting user/comments scan result table
        $wpdb->query('DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_ua_bl`;');         // Deleting AC UA black lists
        $wpdb->query('DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_sfw_temp`;');      // Deleting temporary SFW data
        $wpdb->query('DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_sfw_personal_temp`;');      // Deleting temporary SFW data
        $wpdb->query('DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_connection_reports`;');      // Deleting connection_reports
        $wpdb->query('DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_wc_spam_orders`;');
    }

    /**
     * Clear all meta
     */
    public static function deleteMeta()
    {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM $wpdb->usermeta WHERE meta_key IN ('ct_bad', 'ct_checked', 'ct_checked_now', 'ct_marked_as_spam', 'ct_hash');"
        );
        $wpdb->query(
            "DELETE FROM $wpdb->commentmeta WHERE meta_key IN ('ct_hash', 'ct_real_user_badge_hash');"
        );
        delete_post_meta_by_key('cleantalk_order_request_id');
        //old checker way trace
        delete_post_meta_by_key('ct_checked');
    }

    private static function deleteSFWUpdateFolder()
    {
        $current_blog_id = get_current_blog_id();
        $wp_upload_dir = wp_upload_dir();
        if (isset($wp_upload_dir['basedir'])) {
            $update_folder = $wp_upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'cleantalk_fw_files_for_blog_' . $current_blog_id . DIRECTORY_SEPARATOR;
            SFWUpdateHelper::removeUpdFolder($update_folder);
        }
    }
}
