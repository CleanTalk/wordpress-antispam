<?php


class ABPCTCommentsListTableScan extends ABPCTCommentsListTable
{

    function prepare_items() {

        $columns = $this->get_columns();
        $this->_column_headers = array( $columns, array(), array() );

        $per_page_option = get_current_screen()->get_option( 'per_page', 'option' );
        $per_page = get_user_meta( get_current_user_id(), $per_page_option, true );
        if( ! $per_page ) {
            $per_page = 10;
        }

        $scanned_comments = $this->getSpamNow();

        $this->set_pagination_args( array(
            'total_items' => count( $scanned_comments->get_comments() ),
            'per_page'    => $per_page,
        ) );

        $current_page = (int) $this->get_pagenum();

        $scanned_comments_to_show = array_slice( $scanned_comments->get_comments(), ( ( $current_page - 1 ) * $per_page ), $per_page );

        foreach( $scanned_comments_to_show as $comment ) {

            $this->items[] = array(
                'ct_id' => $comment->comment_ID,
                'ct_author'   => $comment->comment_author,
                'ct_comment'  => $comment,
                'ct_response_to' => $comment->comment_post_ID,
            );

        }

    }

}