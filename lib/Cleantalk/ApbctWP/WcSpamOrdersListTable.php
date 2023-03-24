<?php

namespace Cleantalk\ApbctWP;

class WcSpamOrdersListTable extends CleantalkListTable
{
    protected $apbct;

    protected $wc_active = false;

    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'wc_spam_orders',
            'plural'   => 'wc_spam_orders'
        ));

        //$this->bulk_actions_handler();

        //$this->row_actions_handler();

        if ( in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) ) {
            $this->wc_active = true;
        }

        $this->prepare_items();

        global $apbct;
        $this->apbct = $apbct;
    }

    /**
     * @inheritDoc
     */
    public function prepare_items()  // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $columns               = $this->get_columns();
        $this->_column_headers = array($columns, array(), array());

        $per_page_option = ! is_null(get_current_screen()) ? get_current_screen()->get_option(
            'per_page',
            'option'
        ) : '10';
        $per_page        = get_user_meta(get_current_user_id(), $per_page_option, true);
        if ( ! $per_page ) {
            $per_page = 10;
        }

        // @ToDo implement per page dynamic option
        $per_page = 10;

        $wc_spam_orders = $this->getWcSpamOrders();

        $this->set_pagination_args(array(
            'total_items' => count($wc_spam_orders),
            'per_page'    => $per_page,
        ));

        $current_page = $this->get_pagenum();

        $wc_spam_orders_to_show = array_slice(
            $wc_spam_orders,
            (($current_page - 1) * $per_page),
            $per_page
        );

        foreach ( $wc_spam_orders_to_show as $wc_spam_order) {
            $this->items[] = array(
                'ct_order_id'          => $wc_spam_order->order_id,
                'ct_order_details'     => $wc_spam_order->order_details,
                'ct_currency'          => $wc_spam_order->currency,
                'ct_customer_details'  => $wc_spam_order->customer_details,
            );
        }
    }

    public function get_columns()
    {
        $columns = array(
            'cb'           => '<input type="checkbox" />',
            'ct_order_id'  => esc_html__('Order ID', 'cleantalk-spam-protect'),
            'ct_order_details'      => esc_html__('Order details', 'cleantalk-spam-protect'),
            'ct_currency'     => esc_html__('Currency', 'cleantalk-spam-protect'),
            'ct_customer_details' => esc_html__('Customer details', 'cleantalk-spam-protect'),
        );

        return $columns;
    }

    public function column_default($item, $column_name) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return print_r($item[$column_name], true);
    }

    /********************************************************/
    private function getWcSpamOrders()
    {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . APBCT_TBL_WC_SPAM_ORDERS, OBJECT);
    }
}