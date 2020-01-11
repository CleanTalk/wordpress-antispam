<?php


class ABPCTUsersListTable extends ABPCT_List_Table
{

    private $wpdb;

    private $apbct;

    private $spam_users = array();

    function __construct(){

        parent::__construct(array(
            'singular' => 'spam',
            'plural'   => 'spam',
            'ajax' => true,
        ));

        $this->bulk_action_handler();

        $this->prepare_items();

        global $wpdb, $apbct;
        $this->wpdb = $wpdb;
        $this->apbct = $apbct;

    }

    function prepare_items(){

        $per_page_option = get_current_screen()->get_option( 'per_page', 'option' );
        $per_page = get_user_meta( get_current_user_id(), $per_page_option, true );
        if( ! $per_page ) {
            $per_page = 10;
        }

        $this->set_pagination_args( array(
            'total_items' => $this->get_spam_users()->get_total(),
            'per_page'    => $per_page,
        ) );

        $current_page = (int) $this->get_pagenum();

        //$spam_users_to_show = $this->get_spam_users()->get_results();
        $spam_users_to_show = array_slice( $this->get_spam_users()->get_results(), ( ( $current_page - 1 ) * $per_page ), $per_page );

        foreach( $spam_users_to_show as $user_id ) {

            $user_obj = get_userdata( $user_id );

            $this->spam_users[] = array(
                'ct_id' => $user_obj->ID,
                'ct_username'   => $user_obj,
                'ct_name'  => $user_obj->display_name,
                'ct_email' => $user_obj->user_email,
                'ct_signed_up' => $user_obj->user_registered,
                'ct_role' => implode( ', ', $user_obj->roles ),
                'ct_posts' => count_user_posts( $user_id ),
            );

        }

        $this->items = $this->spam_users;

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
                return $item[ $column_name ];
            default:
                return print_r( $item, true ) ; //Мы отображаем целый массив во избежание проблем
        }
    }

    function get_bulk_actions() {
        $actions = array(
            'delete'    => 'Delete'
        );
        return $actions;
    }

    function no_items() {
        esc_html_e( 'No spam found.', 'cleantalk' );
    }

    //********************************************//
    //                 LOGIC                     //
    //*******************************************//

    private function get_total_users() {

        $params_total = array(
            'fields' => 'ID',
            'count'=>true,
            'orderby' => 'user_registered'
        );
        $total_users = new WP_User_Query($params_total);
        return $total_users;

    }

    private function get_spam_users() {

        $params_spam = array(
            'fields' => 'ID',
            'meta_key' => 'ct_marked_as_spam',
            'count_total' => true,
        );
        $spam_users = new WP_User_Query($params_spam);
        return $spam_users;

    }

    private function get_bad_users() { // Without IP and EMAIL

        $params_bad = array(
            'fields' => 'ID',
            'meta_key' => 'ct_bad',
            'count_total' => true,
        );
        $bad_users = new WP_User_Query($params_bad);
        return $bad_users;

    }

}