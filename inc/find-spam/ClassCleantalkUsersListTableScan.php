<?php


class ABPCTUsersListTableScan extends ABPCTUsersListTable
{

    function prepare_items() {

        $columns = $this->get_columns();
        $this->_column_headers = array( $columns, array(), array() );

        $per_page_option = get_current_screen()->get_option( 'per_page', 'option' );
        $per_page = get_user_meta( get_current_user_id(), $per_page_option, true );
        if( ! $per_page ) {
            $per_page = 10;
        }

        $scanned_users = $this->getSpamNow();

        $this->set_pagination_args( array(
            'total_items' => $scanned_users->get_total(),
            'per_page'    => $per_page,
        ) );

        $current_page = (int) $this->get_pagenum();

        $scanned_users_to_show = array_slice( $scanned_users->get_results(), ( ( $current_page - 1 ) * $per_page ), $per_page );

        foreach( $scanned_users_to_show as $user_id ) {

            $user_obj = get_userdata( $user_id );

            $this->items[] = array(
                'ct_id' => $user_obj->ID,
                'ct_username'   => $user_obj,
                'ct_name'  => $user_obj->display_name,
                'ct_email' => $user_obj->user_email,
                'ct_signed_up' => $user_obj->user_registered,
                'ct_role' => implode( ', ', $user_obj->roles ),
                'ct_posts' => count_user_posts( $user_id ),
            );

        }

    }

}