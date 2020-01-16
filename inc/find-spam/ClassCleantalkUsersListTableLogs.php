<?php


class ABPCTUsersListTableLogs extends ABPCTUsersListTable
{

    // Set columns
    function get_columns(){
        return array(
            /*'cb'            => '<input type="checkbox" />',*/
            'ct_start'      => esc_html__( 'Start time', 'cleantalk' ),
            'ct_checked'          => esc_html__( 'Checked', 'cleantalk' ),
            'ct_spam'         => esc_html__( 'Found spam', 'cleantalk' ),
            'ct_bad'     => esc_html__( 'Found bad', 'cleantalk' ),
        );
    }

    function prepare_items(){

        $columns = $this->get_columns();
        $this->_column_headers = array( $columns, array(), array() );

        $logs = $this->getScansLogs();

        foreach( $logs as $log ) {

            $this->items[] = array(
                /*'ct_id' => $log['id'],*/
                'ct_start'  => $log['start_time'],
                'ct_checked' => $log['count_to_scan'],
                'ct_spam' => $log['found_spam'],
                'ct_bad' => $log['found_bad'],
            );

        }

    }

    function get_bulk_actions() {
        $actions = array();
        return $actions;
    }

    function no_items() {
        esc_html_e( 'No logs found.', 'cleantalk' );
    }

}