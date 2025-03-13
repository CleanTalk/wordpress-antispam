<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\Variables\Post;

class WcSpamOrdersFunctions
{
    public static function restoreOrderAction()
    {
        AJAXService::checkNonceRestrictingNonAdmins();

        $order_id = Post::get('order_id', 'int');

        if (!$order_id) {
            wp_send_json_error(esc_html__('Error: Order ID is not valid.', 'cleantalk-spam-protect'));
        }

        // Get order data from table with spam orders
        $order_data = self::getOrderDataById($order_id);

        if (is_null($order_data)) {
            wp_send_json_error(esc_html__('Error: Order is not founded.', 'cleantalk-spam-protect'));
        }

        try {
            $order_details = json_decode($order_data->order_details);
            $customer_details = json_decode($order_data->customer_details);

            self::createOrder($order_details, $customer_details);

            self::deleteSpamOrderData($order_id);
        } catch (\Exception $e) {
            wp_send_json_error(esc_html__('Error: ' . $e->getMessage(), 'cleantalk-spam-protect'));
        }

        wp_send_json_success();
    }

    private static function getOrderDataById($order_id)
    {
        global $wpdb;

        return $wpdb->get_row(
            "SELECT * FROM "
            . APBCT_TBL_WC_SPAM_ORDERS
            . " WHERE id = '" . $order_id . "';"
        );
    }

    private static function createOrder($order_details, $customer_details)
    {
        /** @psalm-suppress UndefinedFunction */
        $order = wc_create_order();

        // Add Products
        foreach ($order_details as $product) {
            /** @psalm-suppress UndefinedFunction */
            $order->add_product(wc_get_product($product->product_id), $product->quantity);
        }

        // Add Shipping and Billing Addresses
        $billing_address = array(
            'first_name' => $customer_details->billing_first_name,
            'last_name'  => $customer_details->billing_last_name,
            'company'    => isset($customer_details->billing_company) ? $customer_details->billing_company : '',
            'email'      => $customer_details->billing_email,
            'phone'      => $customer_details->billing_phone,
            'address_1'  => $customer_details->billing_address_1,
            'address_2'  => $customer_details->billing_address_2,
            'city'       => $customer_details->billing_city,
            'state'      => $customer_details->billing_state,
            'postcode'   => $customer_details->billing_postcode,
            'country'    => $customer_details->billing_country,
        );

        $order->set_address($billing_address);

        $shipping_address = array(
            'first_name' => $customer_details->shipping_first_name,
            'last_name'  => $customer_details->shipping_last_name,
            'company'    => isset($customer_details->shipping_company) ? $customer_details->shipping_company : '',
            'address_1'  => $customer_details->shipping_address_1,
            'address_2'  => $customer_details->shipping_address_2,
            'city'       => $customer_details->shipping_city,
            'state'      => $customer_details->shipping_state,
            'postcode'   => $customer_details->shipping_postcode,
            'country'    => $customer_details->shipping_country,
        );

        $order->set_address($shipping_address, 'shipping');

        $order->calculate_totals();
        $order->set_status('wc-pending');
        $order->save();
    }

    private static function deleteSpamOrderData($order_id)
    {
        global $wpdb;

        return $wpdb->query(
            "DELETE FROM "
            . APBCT_TBL_WC_SPAM_ORDERS
            . " WHERE id = '" . $order_id . "';"
        );
    }
}
