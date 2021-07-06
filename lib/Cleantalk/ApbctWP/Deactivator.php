<?php


namespace Cleantalk\ApbctWP;


class Deactivator {

	public static function deactivation( $network_wide )
	{
		global $apbct, $wpdb;

		if( ! is_multisite() ){
			// Deactivation on standalone blog

			apbct_deactivation__delete_common_tables();
			delete_option('cleantalk_cron'); // Deleting cron entries

			if($apbct->settings['misc__complete_deactivation']) {
				apbct_deactivation__delete_all_options();
				apbct_deactivation__delete_meta();
			}

		} elseif( $network_wide ) {
			// Deactivation for network

			$initial_blog  = get_current_blog_id();
			$blogs = array_keys($wpdb->get_results('SELECT blog_id FROM '. $wpdb->blogs, OBJECT_K));
			foreach ($blogs as $blog) {
				switch_to_blog($blog);
				apbct_deactivation__delete_blog_tables();
				delete_option('cleantalk_cron'); // Deleting cron entries

				if($apbct->settings['misc__complete_deactivation']){
					apbct_deactivation__delete_all_options();
					apbct_deactivation__delete_meta();
					apbct_deactivation__delete_all_options__in_network();
				}

			}
			switch_to_blog($initial_blog);

		} else {
			// Deactivation for blog

			apbct_deactivation__delete_common_tables();
			delete_option('cleantalk_cron'); // Deleting cron entries

			if($apbct->settings['misc__complete_deactivation']) {
				apbct_deactivation__delete_all_options();
				apbct_deactivation__delete_meta();
			}

		}

	}
}