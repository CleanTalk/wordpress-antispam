<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\TT;

class WcSpamOrdersListTable extends CleantalkListTable
{
    protected $apbct;

    protected $wc_active = false;
    protected $wc_spam_orders_count = 0;

    /**
     * Status links of the hosting page when the table is embedded into it.
     * Null means the table is rendered on its own page and builds the links itself.
     *
     * @var array|null
     */
    protected $embedded_views = null;

    /**
     * @param array|null $embedded_views Status links of the hosting page, see $embedded_views
     */
    public function __construct($embedded_views = null)
    {
        parent::__construct(array(
            'singular' => 'wc_spam_orders',
            'plural'   => 'wc_spam_orders'
        ));

        $this->embedded_views = is_array($embedded_views) ? $embedded_views : null;

        $this->bulk_actions_handler();

        $this->row_actions_handler();

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
        $sortable_columns      = $this->get_sortable_columns();
        $this->_column_headers = array($columns, array(), $sortable_columns);

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

        // Blocked orders are never put on hold, so the view is always empty
        if ( $this->getCurrentStatus() === 'on-hold' ) {
            $wc_spam_orders = array();
        }

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

        foreach ( $wc_spam_orders_to_show as $wc_spam_order ) {
            if (
                !is_string($wc_spam_order->order_details) ||
                !is_string($wc_spam_order->customer_details)
            ) {
                continue;
            }

            // The status has to be kept, the hosting page is chosen by it
            $current_status = Get::getString('status');
            $delete_url = admin_url('admin.php?page=' . Get::getString('page'));
            $delete_url = add_query_arg(
                array_filter(array(
                    'status' => $current_status,
                    'action' => 'delete',
                    'spam'   => $wc_spam_order->id,
                )),
                $delete_url
            );
            $delete_url = wp_nonce_url($delete_url, 'apbct_wc_spam_orders_row', '_wpnonce');
            $actions = array(
                'restore' => '<a class="apbct-restore-spam-order-button" data-spam-order-id="' . $wc_spam_order->id . '">' . esc_html__('Restore', 'cleantalk-spam-protect') . '</a>',
                'delete'  => '<a onclick="return confirm(\'' . esc_attr(esc_html__('Are you sure?', 'cleantalk-spam-protect')) . '\')" href="' . esc_url($delete_url) . '">Delete</a>',
                'details' => '<a class="apbct-details-spam-order-button" role="button" tabindex="0" data-spam-order-id="' . esc_attr($wc_spam_order->id) . '">' . esc_html__('See details', 'cleantalk-spam-protect') . '</a>',
            );

            $order_column = sprintf(
                '%1$s %2$s',
                $this->renderOrderColumn($wc_spam_order->id, $wc_spam_order->customer_details),
                $this->row_actions($actions)
            );

            $order_date_column = $this->renderOrderDateColumn($wc_spam_order->order_date);
            $status_column     = $this->renderStatusColumn();
            $total_column      = $this->renderTotalColumn($wc_spam_order->order_details);

            $this->items[] = array(
                'cb'            => $wc_spam_order->id,
                'ct_order'      => $order_column,
                'ct_order_date' => $order_date_column,
                'ct_status'     => $status_column,
                'ct_total'      => $total_column,
            );
        }
    }

    public function get_columns() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $columns = array(
            'cb'            => '<input type="checkbox" />',
            'ct_order'      => esc_html__('Order', 'cleantalk-spam-protect'),
            'ct_order_date' => esc_html__('Date', 'cleantalk-spam-protect'),
            'ct_status'     => esc_html__('Status', 'cleantalk-spam-protect'),
            'ct_total'      => esc_html__('Total', 'cleantalk-spam-protect'),
        );

        return $columns;
    }

    protected function get_sortable_columns() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return array(
            'ct_order'      => array('id', false),
            'ct_order_date' => array('order_date', false),
            'ct_total'      => array('total', false),
        );
    }

    /**
     * Statuses row above the table, the same one as the WooCommerce orders list has.
     * Every stored order is a blocked spam one, so the "On hold" view is always empty.
     *
     * @return array
     */
    protected function get_views() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ( ! is_null($this->embedded_views) ) {
            return $this->embedded_views;
        }

        $current_status = $this->getCurrentStatus();

        $statuses = array(
            'all'     => array(esc_html__('All', 'cleantalk-spam-protect'), $this->wc_spam_orders_count),
            'on-hold' => array(esc_html__('On hold', 'cleantalk-spam-protect'), 0),
            'spam'    => array(esc_html__('Spam', 'cleantalk-spam-protect'), $this->wc_spam_orders_count),
        );

        $views = array();

        foreach ( $statuses as $status => $status_data ) {
            list($title, $count) = $status_data;

            $url = admin_url('admin.php?page=' . Get::getString('page'));
            if ( $status !== 'all' ) {
                $url = add_query_arg('status', $status, $url);
            }

            $views[$status] = sprintf(
                '<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
                esc_url($url),
                $status === $current_status ? ' class="current" aria-current="page"' : '',
                $title,
                $count
            );
        }

        return $views;
    }

    /**
     * Currently selected status view.
     *
     * @return string all|on-hold|spam
     */
    private function getCurrentStatus()
    {
        $status = Get::getString('status');

        return in_array($status, array('on-hold', 'spam'), true) ? $status : 'all';
    }

    /**
     * @inheritDoc
     */
    public function display() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $this->views();

        parent::display();
    }

    public function no_items() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        esc_html_e('No orders found.', 'cleantalk-spam-protect');
    }

    public function get_bulk_actions() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return array(
            'delete' => esc_html__('Delete', 'cleantalk-spam-protect')
        );
    }

    public function bulk_actions_handler() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ( empty(Post::get('spamorderids')) || empty(Post::get('_wpnonce')) ) {
            return;
        }

        if ( ! $action = $this->current_action() ) {
            return;
        }

        if ( ! wp_verify_nonce(Post::getString('_wpnonce'), 'bulk-' . TT::getArrayValueAsString($this->_args, 'plural')) ) {
            wp_die('nonce error');
        }

        $spam_ids = Post::get('spamorderids');

        if ( 'delete' === $action ) {
            $this->deleteFromDb($spam_ids);
        }
    }

    public function column_cb($item) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $cb = TT::getArrayValueAsString($item, 'cb');
        echo '<input type="checkbox" name="spamorderids[]" id="cb-select-' . $cb . '" value="' . $cb . '" />';
    }

    public function column_default($item, $column_name) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if (is_array($item) && array_key_exists($column_name, $item)) {
            return $item[$column_name];
        }

        if (is_object($item) && property_exists($item, $column_name)) {
            return $item->$column_name;
        }
        return '';
    }

    public function row_actions_handler() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ( empty(Get::get('action')) ) {
            return;
        }

        if ( ! wp_verify_nonce(Get::getString('_wpnonce'), 'apbct_wc_spam_orders_row') ) {
            wp_die(esc_html__('Security check failed. Please try again.', 'cleantalk-spam-protect'), 403);
        }

        if ( ! current_user_can('activate_plugins') ) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'cleantalk-spam-protect'), 403);
        }

        if ( Get::get('action') === 'delete' ) {
            $id = filter_input(INPUT_GET, 'spam', FILTER_SANITIZE_ENCODED, FILTER_FLAG_STRIP_HIGH);
            $this->removeSpam(array($id));
        }
    }

    /********************************************************/

    /**
     * @param string $order_details
     *
     * @return string Product - quantity. Error string on decoding error.
     *
     * @psalm-suppress UndefinedFunction
     */
    private function renderOrderDetailsColumn($order_details)
    {
        $order_details = json_decode($order_details, true);

        if (!is_array($order_details)) {
            return '<b>Product details decoding error.</b><br>';
        }

        $order_details = array_values($order_details);

        $result        = '';

        foreach ( $order_details as $order_detail ) {
            $product_title = 'Unavailable product';
            if (function_exists('wc_get_product') && class_exists('\WC_Product')) {
                $wc_product = wc_get_product($order_detail['product_id']);
                $wc_product_class = '\WC_Product';
                $product_title = $wc_product instanceof $wc_product_class ? $wc_product->get_title() : '';
            }
            $result .= "<b>" . esc_html($product_title) . "</b>";
            $result .= " - ";
            $result .= esc_html($order_detail['quantity']);
            $result .= "<br>";
        }

        return $result;
    }

    /**
     * @param string $customer_details
     * @return string
     */
    private function renderCustomerDetailsColumn($customer_details)
    {
        $customer_details = json_decode($customer_details, true);

        if (!is_array($customer_details)) {
            return '<b>Customer details decoding error.</b><br>';
        }

        $result           = '';

        $result .= "<b>" . esc_html($customer_details["billing_first_name"] ?? '') . "</b>";
        $result .= "<br>";
        $result .= "<b>" . esc_html($customer_details["billing_last_name"] ?? '') . "</b>";
        $result .= "<br>";
        $result .= "<b>" . esc_html($customer_details["billing_email"] ?? '') . "</b>";

        return $result;
    }

    /**
     * Order number and the customer name, the same way as WooCommerce orders list does it.
     *
     * @param int|string $spam_order_id
     * @param string $customer_details
     *
     * @return string
     */
    private function renderOrderColumn($spam_order_id, $customer_details)
    {
        $customer_details = json_decode($customer_details, true);

        $customer_name = '';

        if ( is_array($customer_details) ) {
            $customer_name = trim(
                TT::getArrayValueAsString($customer_details, 'billing_first_name')
                . ' '
                . TT::getArrayValueAsString($customer_details, 'billing_last_name')
            );

            if ( $customer_name === '' ) {
                $customer_name = TT::getArrayValueAsString($customer_details, 'billing_email');
            }
        }

        if ( $customer_name === '' ) {
            $customer_name = esc_html__('Guest', 'cleantalk-spam-protect');
        } else {
            $customer_name = esc_html($customer_name);
        }

        return sprintf(
            '<a class="apbct-details-spam-order-button apbct-order-view" role="button" tabindex="0" data-spam-order-id="%1$s"><strong>#%1$s %2$s</strong></a>',
            esc_attr(TT::toString($spam_order_id)),
            $customer_name
        );
    }

    private function renderOrderDateColumn($order_date)
    {
        if ( ! $order_date ) {
            return '-';
        }

        $timestamp = is_numeric($order_date) ? (int) $order_date : strtotime($order_date);

        if ( ! $timestamp ) {
            return '-';
        }

        // Fresh orders are shown as "5 minutes ago", the older ones as a date. Same as WooCommerce does.
        $diff = time() - $timestamp;
        if ( $diff >= 0 && $diff < DAY_IN_SECONDS ) {
            /* translators: %s: human-readable time difference */
            $show_date = sprintf(__('%s ago', 'cleantalk-spam-protect'), human_time_diff($timestamp, time()));
        } else {
            $show_date = date_i18n('M j, Y', $timestamp);                  // Feb 15, 2023
        }

        return sprintf(
            '<time datetime="%1$s" title="%2$s">%3$s</time>',
            esc_attr(date_i18n('c', $timestamp)),                    // 2023-02-15T20:25:06+00:00
            esc_attr(date_i18n('d.m.Y H:i', $timestamp)),            // 15.02.2023 20:25
            esc_html($show_date)
        );
    }

    /**
     * Every stored order is a blocked spam one, so the status is always the same.
     *
     * @return string
     */
    private function renderStatusColumn()
    {
        return '<mark class="apbct-order-status apbct-order-status--spam"><span>'
            . esc_html__('Spam', 'cleantalk-spam-protect')
            . '</span></mark>';
    }

    /**
     * @param string $order_details
     *
     * @return string Formatted order total or a dash if it can not be calculated.
     */
    private function renderTotalColumn($order_details)
    {
        $total = $this->calcOrderTotal($order_details);

        if ( is_null($total) ) {
            return '-';
        }

        /** @psalm-suppress UndefinedFunction */
        return function_exists('wc_price')
            ? wc_price($total)
            : esc_html(number_format_i18n($total, 2));
    }

    /**
     * Sums up the stored cart items. Cart data keeps the calculated line totals,
     * the product price is used as a fallback only.
     *
     * @param string $order_details
     *
     * @return float|null Null if the order details can not be decoded.
     *
     * @psalm-suppress UndefinedFunction
     */
    private function calcOrderTotal($order_details)
    {
        $order_details = json_decode($order_details, true);

        if ( ! is_array($order_details) ) {
            return null;
        }

        $total = 0;

        foreach ( $order_details as $order_detail ) {
            if ( ! is_array($order_detail) ) {
                continue;
            }

            if ( isset($order_detail['line_total']) ) {
                $total += (float) $order_detail['line_total'] + (float) ($order_detail['line_tax'] ?? 0);
                continue;
            }

            if ( isset($order_detail['product_id']) && function_exists('wc_get_product') && class_exists('\WC_Product') ) {
                $wc_product       = wc_get_product($order_detail['product_id']);
                $wc_product_class = '\WC_Product';
                if ( $wc_product instanceof $wc_product_class ) {
                    $total += (float) $wc_product->get_price() * (float) ($order_detail['quantity'] ?? 1);
                }
            }
        }

        return (float) $total;
    }

    /**
     * @return array
     */
    private function getWcSpamOrders()
    {
        global $wpdb;

        $orderby = $this->getSqlOrderBy();
        $order = Get::getString('order') === 'asc' ? 'ASC' : 'DESC';

        $sql = 'SELECT * FROM ' . APBCT_TBL_WC_SPAM_ORDERS;

        // The newest spam orders are shown first by default, the same way as WooCommerce orders list does it.
        $sql .= ' ORDER BY ' . ($orderby ? $orderby : 'order_date') . ' ' . $order;

        $result = $wpdb->get_results($sql, OBJECT);

        $result = is_array($result) ? $result : array();

        if ( Get::getString('orderby') === 'total' ) {
            $result = $this->sortByTotal($result, $order);
        }

        return $result;
    }

    /**
     * The order total is not stored as a column, so it has to be sorted after the fetch.
     *
     * @param array $wc_spam_orders
     * @param string $order ASC|DESC
     *
     * @return array
     */
    private function sortByTotal($wc_spam_orders, $order)
    {
        $totals = array();

        foreach ( $wc_spam_orders as $key => $wc_spam_order ) {
            $calculated_total = is_string($wc_spam_order->order_details)
                ? $this->calcOrderTotal($wc_spam_order->order_details)
                : null;

            if ( is_null($calculated_total) ) {
                // Keep undecodable totals consistently at the end for both ASC and DESC.
                $calculated_total = $order === 'DESC' ? -PHP_FLOAT_MAX : PHP_FLOAT_MAX;
            }

            $totals[$key] = (float) $calculated_total;
        }

        uasort($totals, static function ($a, $b) {
            if ( $a === $b ) {
                return 0;
            }
            return $a < $b ? -1 : 1;
        });

        if ( $order === 'DESC' ) {
            $totals = array_reverse($totals, true);
        }

        $sorted = array();
        foreach ( array_keys($totals) as $key ) {
            $sorted[] = $wc_spam_orders[$key];
        }

        return $sorted;
    }

    private function getSqlOrderBy()
    {
        $order_by = Get::getString('orderby');
        $allowed_order_by = array('id', 'order_date');
        return in_array($order_by, $allowed_order_by, true) ? $order_by : '';
    }

    private function removeSpam($ids)
    {
        global $wpdb;

        $ids_sql_prepare = [];
        foreach ( $ids as $id ) {
            $id                = sanitize_key($id);
            $ids_sql_prepare[] = "'$id'";
        }

        if ( empty($ids_sql_prepare) ) {
            return;
        }

        $ids_sql_prepare = implode(',', $ids_sql_prepare);

        $wpdb->query(
            "DELETE FROM " . APBCT_TBL_WC_SPAM_ORDERS . " WHERE `id` IN (" . $ids_sql_prepare . ");"
        );
    }

    /**
     * Notices shown above the table: the stored orders count and the warnings about the data.
     *
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function renderPageNotices()
    {
        if ( ! apbct_api_key__is_correct() ) {
            if ( 1 == $this->apbct->moderate_ip ) {
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
        <p><?php
            esc_html_e(
                'Please do backup of WordPress database before delete any orders!',
                'cleantalk-spam-protect'
            );
            echo ' ';
            esc_html_e(
                'Results are based on the decision of our spam checking system and do not give a complete guarantee that these orders are spam.',
                'cleantalk-spam-protect'
            ); ?></p>
        <?php
        if ( empty($this->apbct->settings['data__wc_store_blocked_orders']) ) {
            echo '<p style="color: red;">'
            . sprintf(
                esc_html__(
                    'To store Spam orders, enable the "Store blocked WooCommerce orders" option in %1$sCleanTalk settings%2$s.',
                    'cleantalk-spam-protect'
                ),
                '<a href="' . esc_url(TT::toString($this->apbct->settings_link)) . '">',
                '</a>'
            )
            . '</p>';
        }
    }

    private function deleteFromDb($spam_ids)
    {
        global $wpdb;

        $spam_ids_clean = array_map(static function ($item) {
            return (int)$item;
        }, $spam_ids);
        $spam_ids = implode(',', $spam_ids_clean);

        $wpdb->query("DELETE FROM "
            . APBCT_TBL_WC_SPAM_ORDERS
            . " WHERE id IN ("
            . $spam_ids
            . ");");
    }
}
