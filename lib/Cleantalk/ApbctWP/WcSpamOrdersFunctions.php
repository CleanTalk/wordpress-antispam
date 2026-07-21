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
            wp_send_json_error(esc_html__('Error: Order is not found.', 'cleantalk-spam-protect'));
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

    /**
     * Handler for 'wp_ajax_apbct_details_spam_order' ajax call.
     * @return void wp_send_json
     */
    public static function detailsOrderAction()
    {
        AJAXService::checkNonceRestrictingNonAdmins();

        $order_id = Post::getInt('order_id');

        $response_data = self::prepareDetailsOrderResponse(
            $order_id,
            ['class' => self::class, 'method' => 'getOrderDataById']
        );

        if (empty($response_data['error'])) {
            wp_send_json_success($response_data);
        } else {
            wp_send_json_error($response_data);
        }
    }

    public static function prepareDetailsOrderResponse($order_id = null, $search_method = ['class' => null, 'method' => null])
    {
        $response_data = array(
            'order_details' => null,
            'customer_details' => null,
            'error' => null,
        );

        try {
            if (!is_numeric($order_id) || (int)$order_id <= 0) {
                throw new \Exception(esc_html__('Order ID is not valid.', 'cleantalk-spam-protect'));
            }
            $order_id = (int)$order_id;

            if (
                empty($search_method['class']) ||
                empty($search_method['method']) ||
                !is_string($search_method['class']) ||
                !is_string($search_method['method']) ||
                !class_exists($search_method['class']) ||
                !is_callable([$search_method['class'], $search_method['method']])
            ) {
                throw new \Exception(esc_html__('Search method undefined.', 'cleantalk-spam-protect'));
            }

            // Get order data from table with spam orders
            $order_data = $search_method['class']::{$search_method['method']}($order_id);

            if (is_null($order_data)) {
                throw new \Exception(esc_html__('Order is not found.', 'cleantalk-spam-protect'));
            }

            $response_data['order_details'] = json_decode($order_data->order_details, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception(esc_html__('Order details JSON is invalid.', 'cleantalk-spam-protect'));
            }
            if (is_array($response_data['order_details'])) {
                foreach ($response_data['order_details'] as $_item => &$details) {
                    $product_name = __('Unknown product name', 'cleantalk-spam-protect');
                    if (isset($details['product_id']) && function_exists('wc_get_product')) {
                        $product_details = wc_get_product($details['product_id']);
                        if ($product_details && method_exists($product_details, 'get_name')) {
                            $product_name_got = $product_details->get_name();
                            if (is_string($product_name_got) && !empty($product_name_got)) {
                                $product_name = $product_name_got;
                            }
                        }
                    }
                    $details['product_name'] = $product_name;
                }
            }

            $response_data['customer_details'] = json_decode($order_data->customer_details, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception(esc_html__('Customer details JSON is invalid.', 'cleantalk-spam-protect'));
            }
        } catch (\Exception $e) {
            $response_data['error'] = sprintf(
                esc_html__('Error: %s', 'cleantalk-spam-protect'),
                $e->getMessage()
            );
        }

        return $response_data;
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
