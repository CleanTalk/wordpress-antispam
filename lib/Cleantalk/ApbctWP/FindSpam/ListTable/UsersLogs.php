<?php

namespace Cleantalk\ApbctWP\FindSpam\ListTable;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\TT;

class UsersLogs extends Users
{
    // Set columns
    public function get_columns() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return array(
            'cb'         => '<input type="checkbox" />',
            'ct_start'   => esc_html__('Start time', 'cleantalk-spam-protect'),
            'ct_checked' => esc_html__('Checked', 'cleantalk-spam-protect'),
            'ct_spam'    => esc_html__('Found spam', 'cleantalk-spam-protect'),
            'ct_bad'     => esc_html__('Found bad', 'cleantalk-spam-protect'),
        );
    }

    public function prepare_items() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $logs = $this->getScansLogs();

        foreach ( $logs as $log ) {
            $this->items[] = array(
                'ct_id'      => $log['id'],
                'ct_start'   => $log['start_time'],
                'ct_checked' => $log['count_to_scan'],
                'ct_spam'    => $log['found_spam'],
                'ct_bad'     => $log['found_bad'],
            );
        }
    }

    public function get_bulk_actions() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return array(
            'delete' => 'Delete log'
        );
    }

    public function bulk_actions_handler() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ( empty(Post::get('spamids')) || empty(Post::get('_wpnonce')) ) {
            return;
        }

        if ( ! $this->current_action() ) {
            return;
        }

        $awaited_action = 'bulk-' . TT::getArrayValueAsString($this->_args, 'plural');
        if ( ! wp_verify_nonce(TT::toString(Post::get('_wpnonce')), $awaited_action)) {
            wp_die('nonce error');
        }

        $this->removeLogs(TT::toArray(Post::get('spamids')));
    }

    public function no_items() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        esc_html_e('No logs found.', 'cleantalk-spam-protect');
    }
}
