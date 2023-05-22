<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\Variables\Get;

class WcSpamOrdersListTable extends CleantalkListTable
{
    protected $apbct;

    protected $wc_active = false;
    protected $page_title = '';
    protected $wc_spam_orders_count = 0;

    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'wc_spam_orders',
            'plural'   => 'wc_spam_orders'
        ));

        //$this->bulk_actions_handler();

        $this->row_actions_handler();

        if ( in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) ) {
            $this->wc_active = true;
        }

        $this->prepare_items();

        global $apbct;
        $this->apbct      = $apbct;
        $this->page_title = 'WooCommerce spam orders';

        $this->generatePageHeader();
    }

    /**
     * @inheritDoc
     */
    public function prepare_items()  // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $columns               = $this->get_columns();
        $this->_column_headers = array($columns, array(), array());

        // @ToDo implement per page dynamic option
        /*$per_page_option = ! is_null(get_current_screen()) ? get_current_screen()->get_option(
            'per_page',
            'option'
        ) : '10';
        $per_page        = get_user_meta(get_current_user_id(), $per_page_option, true);
        if ( ! $per_page ) {
            $per_page = 10;
        }*/

        $per_page = 10;

        $wc_spam_orders             = $this->getWcSpamOrders();
        $this->wc_spam_orders_count = count($wc_spam_orders);

        $this->set_pagination_args(array(
            'total_items' => $this->wc_spam_orders_count,
            'per_page'    => $per_page,
        ));

        $current_page = $this->get_pagenum();

        $wc_spam_orders_to_show = array_slice(
            $wc_spam_orders,
            (($current_page - 1) * $per_page),
            $per_page
        );

        foreach ( $wc_spam_orders_to_show as $wc_spam_order ) {
            $actions = array(
                'delete'  => sprintf(
                    '<a href="?page=%s&action=%s&spam=%s">Delete</a>',
                    htmlspecialchars(addslashes(Get::get('page'))),
                    'delete',
                    $wc_spam_order->order_id
                ),
                /*'approve' => sprintf(
                    '<a href="?page=%s&action=%s&spam=%s">Approve</a>',
                    htmlspecialchars(addslashes(Get::get('page'))),
                    'approve',
                    $wc_spam_order->order_id
                )*/
            );

            $order_id_column = sprintf('%1$s %2$s', $wc_spam_order->order_id, $this->row_actions($actions));

            $order_details_column    = $this->renderOrderDetailsColumn($wc_spam_order->order_details);
            $customer_details_column = $this->renderCustomerDetailsColumn($wc_spam_order->customer_details);

            $this->items[] = array(
                'ct_order_id'         => $order_id_column,
                'ct_order_details'    => $order_details_column,
                'ct_currency'         => $wc_spam_order->currency,
                'ct_customer_details' => $customer_details_column,
            );
        }
    }

    public function get_columns() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $columns = array(
            'cb'                  => '<input type="checkbox" />',
            'ct_order_id'         => esc_html__('Order ID', 'cleantalk-spam-protect'),
            'ct_order_details'    => esc_html__('Order details', 'cleantalk-spam-protect'),
            'ct_currency'         => esc_html__('Currency', 'cleantalk-spam-protect'),
            'ct_customer_details' => esc_html__('Customer details', 'cleantalk-spam-protect'),
        );

        return $columns;
    }

    public function column_default($item, $column_name) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return print_r($item[$column_name], true);
    }

    public function row_actions_handler() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ( empty(Get::get('action')) ) {
            return;
        }

        if ( Get::get('action') === 'delete' ) {
            $id = filter_input(INPUT_GET, 'spam', FILTER_SANITIZE_ENCODED, FILTER_FLAG_STRIP_HIGH);
            $this->removeSpam(array($id));
        }

        /** Not implemented yet */
        /*if ( Get::get('action') === 'approve' ) {
            $id    = filter_input(INPUT_GET, 'spam', FILTER_SANITIZE_ENCODED, FILTER_FLAG_STRIP_HIGH);
            $order = $this->getWcSpamOrder($id);


            $result = $this->sendWcSpamOrderAsApproved($order);
        }*/
    }

    /********************************************************/

    /**
     * @param $order_details
     *
     * @return string
     *
     * @psalm-suppress UndefinedFunction
     */
    private function renderOrderDetailsColumn($order_details)
    {
        $order_details = array_values(json_decode($order_details, true));
        $result        = '';

        foreach ( $order_details as $order_detail ) {
            $result .= "<b>" . wc_get_product($order_detail['product_id'])->get_title() . "</b>";
            $result .= " - ";
            $result .= $order_detail['quantity'];
            $result .= "<br>";
        }

        return $result;
    }

    private function renderCustomerDetailsColumn($customer_details)
    {
        $customer_details = json_decode($customer_details, true);
        $result           = '';

        $result .= "<b>" . $customer_details["billing_first_name"] . "</b>";
        $result .= "<br>";
        $result .= "<b>" . $customer_details["billing_last_name"] . "</b>";
        $result .= "<br>";
        $result .= "<b>" . $customer_details["billing_email"] . "</b>";

        return $result;
    }

    /**
     * @param $order
     *
     * @return string
     *
     * @psalm-suppress UnusedFunction
     */
    private function sendWcSpamOrderAsApproved($order)
    {
        $response = wp_remote_post(site_url('/?wc-ajax=checkout'), array(
                'method'  => 'POST',
                'timeout' => 45,
                // 'redirection' => 5,
                // 'httpversion' => '1.0',
                // 'blocking' => true,
                'headers' => array(),
                'body'    => $order->customer_details,
                'cookies' => array()
            ));

        $result = '';

        if ( is_wp_error($response) ) {
            $error_message = $response->get_error_message();
            echo "Something went wrong: $error_message";
        } else {
            echo 'Response:<pre>';
            print_r($response);
            echo '</pre>';
        }

        return $result;
    }

    private function getWcSpamOrders()
    {
        global $wpdb;

        return $wpdb->get_results('SELECT * FROM ' . APBCT_TBL_WC_SPAM_ORDERS, OBJECT);
    }

    private function getWcSpamOrder($id)
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM " . APBCT_TBL_WC_SPAM_ORDERS . " WHERE order_id = '$id' LIMIT 1",
            OBJECT
        );
    }

    private function removeSpam($ids)
    {
        global $wpdb;

        $ids_sql_prepare = [];

        foreach ( $ids as $id ) {
            $id                = sanitize_key($id);
            $ids_sql_prepare[] = "'$id'";
        }

        $ids_sql_prepare = implode(',', $ids_sql_prepare);

        $wpdb->query(
            "DELETE FROM " . APBCT_TBL_WC_SPAM_ORDERS . " WHERE `order_id` IN (" . $ids_sql_prepare . ");"
        );
    }

    private function generatePageHeader()
    {
        if ( ! apbct_api_key__is_correct() ) {
            if ( 1 == $this->spam_checker->getApbct()->moderate_ip ) {
                echo '<h3>'
                     . sprintf(
                         __(
                             'Anti-Spam hosting tariff does not allow you to use this feature. To do so, you need to enter an Access Key in the %splugin settings%s.',
                             'cleantalk-spam-protect'
                         ),
                         '<a href="' . (is_network_admin(
                         ) ? 'settings.php?page=cleantalk' : 'options-general.php?page=cleantalk') . '">',
                         '</a>'
                     )
                     . '</h3>';
            }

            return;
        }

        ?>
        <div class="wrap">
            <h2><img src="<?php
                echo $this->apbct->logo__small__colored ?>" alt="CleanTalk logo"/> <?php
                echo $this->apbct->plugin_name; ?></h2>
            <a style="color: gray; margin-left: 23px;" href="<?php
            echo $this->apbct->settings_link; ?>"><?php
                _e('Plugin Settings', 'cleantalk-spam-protect'); ?></a>
            <br/>
            <h3><?php
                echo $this->page_title; ?></h3>
            <p>Total count of spam orders: <?php
                echo $this->wc_spam_orders_count ?></p>
            <p>Please do backup of WordPress database before delete any orders!</p>
            <p>Results are based on the decision of our spam checking system and do not give a complete guarantee that
                these orders are spam.</p>
        </div>
        <?php
    }
}
