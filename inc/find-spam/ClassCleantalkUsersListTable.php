<?php


class ABPCTUsersListTable extends ABPCT_List_Table
{

    protected $apbct;

    function __construct(){

        parent::__construct(array(
            'singular' => 'spam',
            'plural'   => 'spam'
        ));

        $this->bulk_actions_handler();

        $this->row_actions_handler();

        $this->prepare_items();

        global $apbct;
        $this->apbct = $apbct;

    }

    // Set columns
    function get_columns(){
        return array(
            'cb'            => '<input type="checkbox" />',
            'ct_username'      => esc_html__( 'Username', 'cleantalk' ),
            'ct_name'          => esc_html__( 'Name', 'cleantalk' ),
            'ct_email'         => esc_html__( 'E-mail', 'cleantalk' ),
            'ct_signed_up'     => esc_html__( 'Signed up', 'cleantalk' ),
            'ct_role'          => esc_html__( 'Role', 'cleantalk' ),
            'ct_posts'         => esc_html__( 'Posts', 'cleantalk' ),
        );
    }

    // CheckBox column
    function column_cb( $item ){
        echo '<input type="checkbox" name="spamids[]" id="cb-select-'. $item['ct_id'] .'" value="'. $item['ct_id'] .'" />';
    }

    // Username (first) column
    function column_ct_username( $item ) {
        $user_obj = $item['ct_username'];
        $email  = $user_obj->user_email;
        $column_content = '';

        // Avatar, nickname
        $column_content .= '<strong>' . get_avatar( $user_obj->ID , 32) . '&nbsp;' . $user_obj->user_login . '</strong>';
        $column_content .= '<br /><br />';

        // Email
        if( ! empty( $email ) ){
            $column_content .= "<a href='mailto:$email'>$email</a>"
                .( ! $this->apbct->white_label
                    ? "<a href='https://cleantalk.org/blacklists/$email' target='_blank'>"
                    ."&nbsp;<img src='" . APBCT_URL_PATH . "/inc/images/new_window.gif' alt='Ico: open in new window' border='0' style='float:none' />"
                    ."</a>"
                    : '');
        } else {
            $column_content .= esc_html__( 'No email', 'cleantalk' );
        }
        $column_content .= '<br/>';

        // IP
        $user_meta = get_user_meta( $user_obj->ID, 'session_tokens', true );
        if( ! empty( $user_meta ) && is_array( $user_meta ) ){
            $user_meta = array_values( $user_meta );
            if( ! empty( $user_meta[0]['ip'] ) ) {
                $ip = $user_meta[0]['ip'];
                $column_content .= "<a href='user-edit.php?user_id=$user_obj->ID'>$ip</a>"
                    .( ! $this->apbct->white_label
                        ?"<a href='https://cleantalk.org/blacklists/$ip ' target='_blank'>"
                        ."&nbsp;<img src='" . APBCT_URL_PATH . "/inc/images/new_window.gif' alt='Ico: open in new window' border='0' style='float:none' />"
                        ."</a>"
                        : '');
            }else
                $column_content .= esc_html__( 'No IP adress', 'cleantalk' );
        }else
            $column_content .= esc_html__( 'No IP adress', 'cleantalk' );

        $actions = array(
            'delete'    => sprintf( '<a href="?page=%s&action=%s&spam=%s">Delete</a>', $_REQUEST['page'],'delete', $user_obj->ID ),
        );

        return sprintf( '%1$s %2$s', $column_content, $this->row_actions( $actions ) );

    }

    // Rest of columns
    function column_default( $item, $column_name ) {
        switch( $column_name ) {
            case 'ct_name':
            case 'ct_email':
            case 'ct_signed_up':
            case 'ct_role':
            case 'ct_posts':
            case 'ct_start':
            case 'ct_checked':
            case 'ct_spam':
            case 'ct_bad':
                return $item[ $column_name ];
            default:
                return print_r( $item, true ) ;
        }
    }

    function get_bulk_actions() {
        $actions = array(
            'delete'    => 'Delete'
        );
        return $actions;
    }

    function bulk_actions_handler() {

        if( empty($_POST['spamids']) || empty($_POST['_wpnonce']) ) return;

        if ( ! $action = $this->current_action() ) return;

        if( ! wp_verify_nonce( $_POST['_wpnonce'], 'bulk-' . $this->_args['plural'] ) )
            wp_die('nonce error');

        $this->removeSpam( $_POST['spamids'] );

    }

    function row_actions_handler() {

        if( empty($_GET['action']) ) return;

        if( $_GET['action'] == 'delete' ) {

            $id = filter_input( INPUT_GET, 'spam', FILTER_SANITIZE_NUMBER_INT );
            $this->removeSpam( array( $id ) );

        }

    }

    function no_items() {
        esc_html_e( 'No spam found.', 'cleantalk' );
    }

    //********************************************//
    //                 LOGIC                     //
    //*******************************************//

    function removeSpam( $ids ) {

        $ids_string = implode( ', ', $ids );
        global $wpdb;

        $wpdb->query("DELETE FROM {$wpdb->users} WHERE 
                ID IN ($ids_string)");

    }

    public function getTotal() {

        $params_total = array(
            'fields' => 'ID',
            'count'=>true,
            'orderby' => 'user_registered'
        );
        $total_users = new WP_User_Query($params_total);
        return $total_users;

    }

    public function getChecked() {

        $params_spam = array(
            'fields' => 'ID',
            'meta_key' => 'ct_checked',
            'count_total' => true,
        );
        $spam_users = new WP_User_Query($params_spam);
        return $spam_users;

    }

    public function getCheckedNow() {

        $params_spam = array(
            'fields' => 'ID',
            'meta_key' => 'ct_checked_now',
            'count_total' => true,
        );
        $spam_users = new WP_User_Query($params_spam);
        return $spam_users;

    }

    public function getSpam() {

        $params_spam = array(
            'fields' => 'ID',
            'meta_key' => 'ct_marked_as_spam',
            'count_total' => true,
        );
        $spam_users = new WP_User_Query($params_spam);
        return $spam_users;

    }

    public function getSpamNow() {

        $params_spam = array(
            'fields' => 'ID',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'ct_marked_as_spam',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'ct_checked_now',
                    'compare' => 'EXISTS'
                ),
            ),
            'count_total' => true,
        );
        $spam_users = new WP_User_Query($params_spam);
        return $spam_users;

    }

    public function getBad() { // Without IP and EMAIL

        $params_bad = array(
            'fields' => 'ID',
            'meta_key' => 'ct_bad',
            'count_total' => true,
        );
        $bad_users = new WP_User_Query($params_bad);
        return $bad_users;

    }

    public function getScansLogs() {

        global $wpdb;
        $query = "SELECT * FROM " . APBCT_SPAMSCAN_LOGS . " WHERE scan_type = 'users'";
        $res = $wpdb->get_results( $query, ARRAY_A );
        return $res;

    }

    protected function removeLogs( $ids ) {

        $ids_string = implode( ', ', $ids );
        global $wpdb;

        $wpdb->query("DELETE FROM " . APBCT_SPAMSCAN_LOGS . " WHERE 
                ID IN ($ids_string)");

    }

}