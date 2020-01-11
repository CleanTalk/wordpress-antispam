<?php


class ClassCleantalkFindSpamUsersChecker extends ClassCleantalkFindSpamChecker
{

    public function __construct() {

        parent::__construct();

        $this->page_title = esc_html__( 'Check users for spam', 'cleantalk' );
        $this->page_slug = 'users';
        $this->list_table = new ABPCTUsersListTable();

        add_action( 'wp_ajax_ajax_check_users', 'ct_ajax_check_users' );
        add_action( 'wp_ajax_ajax_info_users', 'ct_ajax_info_users' );
        add_action( 'wp_ajax_ajax_insert_users', 'ct_ajax_insert_users' );
        add_action( 'wp_ajax_ajax_delete_checked_users', 'ct_ajax_delete_checked_users' );
        add_action( 'wp_ajax_ajax_delete_all_users', 'ct_ajax_delete_all_users' );
        add_action( 'wp_ajax_ajax_clear_users', 'ct_ajax_clear_users' );
        add_action( 'wp_ajax_ajax_ct_approve_user', 'ct_usercheck_approve_user' );
        add_action( 'wp_ajax_ajax_ct_get_csv_file', 'ct_usercheck_get_csv_file' );

        // Preparing data
        $current_user = wp_get_current_user();
        if( ! empty( $_COOKIE['ct_paused_users_check'] ) )
            $prev_check = json_decode( stripslashes( $_COOKIE['ct_paused_users_check'] ), true );

        wp_enqueue_script( 'ct_users_checkspam',  plugins_url('/cleantalk-spam-protect/js/cleantalk-users-checkspam.min.js'), array( 'jquery', 'jquery-ui-core', 'jquery-ui-tabs' ), APBCT_VERSION );
        wp_localize_script( 'ct_users_checkspam', 'ctUsersCheck', array(
            'ct_ajax_nonce'               => wp_create_nonce('ct_secret_nonce'),
            'ct_prev_accurate'            => !empty($prev_check['accurate']) ? true                : false,
            'ct_prev_from'                => !empty($prev_check['from'])     ? $prev_check['from'] : false,
            'ct_prev_till'                => !empty($prev_check['till'])     ? $prev_check['till'] : false,
            'ct_timeout'                  => __('Failed from timeout. Going to check users again.', 'cleantalk'),
            'ct_timeout_delete'           => __('Failed from timeout. Going to run a new attempt to delete spam users.', 'cleantalk'),
            'ct_inserted'                 => __('Inserted', 'cleantalk'),
            'ct_deleted'                  => __('Deleted', 'cleantalk'),
            'ct_iusers'                   => __('users.', 'cleantalk'),
            'ct_confirm_deletion_all'     => __('Delete all spam users?', 'cleantalk'),
            'ct_confirm_deletion_checked' => __('Delete checked users?', 'cleantalk'),
            'ct_csv_filename'             => "user_check_by_".$current_user->user_login,
            'ct_bad_csv'                  => __("File doesn't exist. File will be generated while checking. Please, press \"Check for spam\"."),
            'ct_status_string'            => __("Total users %s, checked %s, found %s spam users and %s bad users (without IP or email)", 'cleantalk'),
            'ct_status_string_warning'    => "<p>".__("Please do backup of WordPress database before delete any accounts!", 'cleantalk')."</p>"
        ));

        wp_enqueue_style( 'cleantalk_admin_css_settings_page', plugins_url().'/cleantalk-spam-protect/css/cleantalk-spam-check.min.css', array(), APBCT_VERSION, 'all' );

    }

    public function get_current_scan_page() {
        $this->get_current_scan_panel();
    }

    public function get_total_spam_page(){

        echo '<form action="" method="POST">';
        $this->list_table->display();
        echo '</form>';

    }

    public function get_spam_logs_page(){

    }

    /**
     * Get date last checked user or date first registered user
     *
     * @return string   date "M j Y"
     */
    public function last_check_date() {

        // Checked users
        $params = array(
            'fields' => 'ID',
            'meta_key' => 'ct_checked',
            'count_total' => true,
            'orderby' => 'ct_checked'
        );
        $tmp = new WP_User_Query( $params );
        $cnt_checked = $tmp->get_total();

        if( $cnt_checked > 0 ) {

            // If we have checked users return last user reg date
            $users = $tmp->get_results();
            return $this->get_user_register( end( $users ) );

        } else {

            // If we have not any checked users return first user registered date
            $params = array(
                'fields' => 'ID',
                'number' => 1,
                'orderby' => 'user_registered'
            );
            $tmp = new WP_User_Query( $params );

            return $this->get_user_register( current( $tmp->get_results() ) );

        }

    }

    /**
     * Get date user registered
     *
     * @param $user_id
     * @return string Date format"M j Y"
     */
    private function get_user_register( $user_id ) {

        $user_data = get_userdata( $user_id );
        $registered = $user_data->user_registered;

        return date( "M j Y", strtotime( $registered ) );

    }

}