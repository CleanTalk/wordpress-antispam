<?php


class ABPCTCommentsListTable extends ABPCT_List_Table
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
            'cb'             => '<input type="checkbox" />',
            'ct_author'      => esc_html__( 'Author', 'cleantalk' ),
            'ct_comment'     => esc_html__( 'Comment', 'cleantalk' ),
            'ct_response_to' => esc_html__( ' 	In Response To', 'cleantalk' ),
        );
    }

    // CheckBox column
    function column_cb( $item ){
        echo '<input type="checkbox" name="spamids[]" id="cb-select-'. $item['ct_id'] .'" value="'. $item['ct_id'] .'" />';
    }

    // Author (first) column
    function column_ct_author( $item ) {

        $column_content = '';
        $email = $item['ct_comment']->comment_author_email;
        $ip = $item['ct_comment']->comment_author_IP;

        // Avatar, nickname
        $column_content .= '<strong>'. $item['ct_comment']->comment_author . '</strong>';
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
        if( ! empty( $ip ) ) {
            $column_content .= "<a href='edit-comments.php?s=$ip&mode=detail'>$ip</a>"
                .( ! $this->apbct->white_label
                    ?"<a href='https://cleantalk.org/blacklists/$ip ' target='_blank'>"
                    ."&nbsp;<img src='" . APBCT_URL_PATH . "/inc/images/new_window.gif' alt='Ico: open in new window' border='0' style='float:none' />"
                    ."</a>"
                    : '');
        }else
            $column_content .= esc_html__( 'No IP adress', 'cleantalk' );

        return $column_content;

    }

    function column_ct_comment( $item ){

        $id = $item['ct_id'];
        $column_content = '';

        $column_content .= '<div class="column-comment">';

        $column_content .= '<div class="submitted-on">';

        $column_content .= sprintf( __( 'Submitted on <a href="%1$s">%2$s at %3$s</a>' ), get_comment_link($id),
            get_comment_date( __( 'Y/m/d' ),$id ),
            get_comment_date( get_option( 'time_format' ),$id )
        );

        $column_content .= '</div>';

        $column_content .= '<p>' . $item['ct_comment']->comment_content . '</p>';

        $column_content .= '</div>';

        $actions = array(
            'approve'   => sprintf( '<span class="approve"><a href="?page=%s&action=%s&spam=%s">Approve</a></span>', $_REQUEST['page'],'approve', $id ),
            'delete'    => sprintf( '<a href="?page=%s&action=%s&spam=%s">Delete</a>', $_REQUEST['page'],'delete', $id ),
        );

        return sprintf( '%1$s %2$s', $column_content, $this->row_actions( $actions ) );

    }

    function column_ct_response_to( $item ) {
        $post_id = $item['ct_response_to'];
        ?>
        <div>
            <span>
                <a href="/wp-admin/post.php?post=<?php echo $post_id; ?>&action=edit"><?php print get_the_title( $post_id ); ?></a>
                <br/>
                <a href="/wp-admin/edit-comments.php?p=<?php echo $post_id; ?>" class="post-com-count">
                    <span class="comment-count"><?php
                        $p_cnt = wp_count_comments( $post_id );
                        echo $p_cnt->total_comments;
                        ?></span>
                </a>
            </span>
            <a href="<?php print get_permalink( $post_id ); ?>"><?php _e( 'View Post' );?></a>
        </div>
        <?php
    }

    // Rest of columns
    function column_default( $item, $column_name ) {
        switch( $column_name ) {
            case 'ct_author':
            case 'ct_comment':
            case 'ct_response_to':
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

        if( 'delete' == $action ) {
            $this->removeSpam( $_POST['spamids'] );
        }

    }

    function row_actions_handler() {

        if( empty($_GET['action']) ) return;

        if( $_GET['action'] == 'approve' ) {

            $id = filter_input( INPUT_GET, 'spam', FILTER_SANITIZE_NUMBER_INT );
            $this->approveSpam( $id );

        }

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

    function approveSpam( $id ) {

        $comment_meta = delete_comment_meta( $id, 'ct_marked_as_spam' );

        if( $comment_meta ) {

            $comment = get_comment($id, 'ARRAY_A');
            $comment['comment_approved'] = 1;

            wp_update_comment( $comment );
            apbct_comment__send_feedback( $id, 'approve', false, true );

        }

    }

    function removeSpam( $ids ) {

        $ids_string = implode( ', ', $ids );
        global $wpdb;

        $wpdb->query("DELETE FROM {$wpdb->comments} WHERE 
                comment_ID IN ($ids_string)");

    }

    public function getTotal() {

        $total_comments = new WP_Comment_Query();
        return $total_comments;

    }

    public function getChecked() {

        $params_spam = array(
            'meta_key' => 'ct_checked',
        );
        $spam_comments = new WP_Comment_Query($params_spam);
        return $spam_comments;

    }

    public function getCheckedNow() {

        $params_spam = array(
            'meta_key' => 'ct_checked_now',
        );
        $spam_comments = new WP_Comment_Query($params_spam);
        return $spam_comments;

    }

    public function getSpam() {

        $params_spam = array(
            'meta_key' => 'ct_marked_as_spam',
        );
        $spam_comments = new WP_Comment_Query($params_spam);
        return $spam_comments;

    }

    public function getSpamNow() {

        // Spam comments
        $params_spam = array(
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
            )
        );
        $spam_comments = new WP_Comment_Query($params_spam);
        return $spam_comments;

    }

    public function getBad() { // Without IP and EMAIL

        $params_bad = array(
            'meta_key' => 'ct_bad',
        );
        $bad_users = new WP_Comment_Query($params_bad);
        return $bad_users;

    }

    public function getScansLogs() {

        global $wpdb;
        $query = "SELECT * FROM " . APBCT_SPAMSCAN_LOGS . " WHERE scan_type = 'comments'";
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