<?php

namespace Cleantalk\ApbctWP;

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
        $like = $wpdb->esc_like($find) . $wild;
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
        $wpdb->query('DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_sfw_logs`;');      // Deleting SFW logs
        $wpdb->query('DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_sfw__flood_logs`;');   // Deleting SFW logs
        $wpdb->query('DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_ac_log`;');      // Deleting SFW logs
        $wpdb->query('DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_sessions`;');      // Deleting session table
        $wpdb->query(
            'DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_spamscan_logs`;'
        ); // Deleting user/comments scan result table
        $wpdb->query('DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_ua_bl`;');         // Deleting AC UA black lists
        $wpdb->query('DROP TABLE IF EXISTS `' . $prefix . 'cleantalk_sfw_temp`;');      // Deleting temporary SFW data
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
    }
}
