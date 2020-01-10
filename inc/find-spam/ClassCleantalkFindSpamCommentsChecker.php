<?php


class ClassCleantalkFindSpamCommentsChecker extends ClassCleantalkFindSpamChecker
{

    public function __construct() {

        parent::__construct();

        $this->page_title = esc_html__( 'Check comments for spam', 'cleantalk' );
        $this->page_slug = 'comments';

        add_action( 'wp_ajax_ajax_check_comments', 'ct_ajax_check_comments' );
        add_action( 'wp_ajax_ajax_info_comments', 'ct_ajax_info_comments' );
        add_action( 'wp_ajax_ajax_insert_comments', 'ct_ajax_insert_comments' );
        add_action( 'wp_ajax_ajax_delete_checked', 'ct_ajax_delete_checked' );
        add_action( 'wp_ajax_ajax_delete_all', 'ct_ajax_delete_all' );
        add_action( 'wp_ajax_ajax_clear_comments', 'ct_ajax_clear_comments' );
        add_action( 'wp_ajax_ajax_ct_approve_comment', 'ct_comment_check_approve_comment' );

        // Preparing data
        if(!empty($_COOKIE['ct_paused_comments_check']))
            $prev_check = json_decode(stripslashes($_COOKIE['ct_paused_comments_check']), true);

        wp_enqueue_script( 'ct_comments_checkspam',  plugins_url('/cleantalk-spam-protect/js/cleantalk-comments-checkspam.min.js'), array( 'jquery', 'jquery-ui-core', 'jquery-ui-tabs' ), APBCT_VERSION );
        wp_localize_script( 'jquery', 'ctCommentsCheck', array(
            'ct_ajax_nonce'               => wp_create_nonce('ct_secret_nonce'),
            'ct_prev_accurate'            => !empty($prev_check['accurate']) ? true                : false,
            'ct_prev_from'                => !empty($prev_check['from'])     ? $prev_check['from'] : false,
            'ct_prev_till'                => !empty($prev_check['till'])     ? $prev_check['till'] : false,
            'ct_timeout_confirm'          => __('Failed from timeout. Going to check comments again.', 'cleantalk'),
            'ct_comments_added'           => __('Added', 'cleantalk'),
            'ct_comments_deleted'         => __('Deleted', 'cleantalk'),
            'ct_comments_added_after'     => __('comments', 'cleantalk'),
            'ct_confirm_deletion_all'     => __('Delete all spam comments?', 'cleantalk'),
            'ct_confirm_deletion_checked' => __('Delete checked comments?', 'cleantalk'),
            'ct_status_string'            => __('Total comments %s. Checked %s. Found %s spam comments. %s bad comments (without IP or email).', 'cleantalk'),
            'ct_status_string_warning'    => '<p>'.__('Please do backup of WordPress database before delete any accounts!', 'cleantalk').'</p>',
            'start'                       => !empty($_COOKIE['ct_comments_start_check']) ? true : false,
        ));

    }

    public function get_current_scan_page() {
        $this->get_current_scan_panel();
    }

    public function get_total_spam_page(){

    }

    public function get_spam_logs_page(){

    }

    /**
     * Get date last checked comment or date of the first comment
     *
     * @return string   date "M j Y"
     */
    function last_check_date() {

        $params = array(
            'fields'   => 'ids',
            'meta_key' => 'ct_checked',
            'orderby'  => 'ct_checked',
            'order'    => 'ASC'
        );
        $checked_comments = get_comments( $params );

        if ( ! empty($checked_comments) ) {

            return get_comment_date( "M j Y", end( $checked_comments ) );

        } else {

            $params = array(
                'fields'   => 'ids',
                'orderby'  => 'comment_date_gmt',
                'order'    => 'ASC',
                'number'   => 1
            );
            $first_comment = get_comments( $params );

            return get_comment_date( "M j Y", current( $first_comment ) );

        }

    }

}