<?php

namespace Cleantalk\Antispam\IntegrationsByClass;

use Cleantalk\Antispam\Cleantalk;
use Cleantalk\Antispam\CleantalkRequest;
use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\WcSpamOrdersFunctions;
use Cleantalk\ApbctWP\Variables\Request;
use Cleantalk\ApbctWP\Variables\Server;

/**
 * Set of WooCommerce actions for integration
 * 1) add to cart
 * 1.1) guest add to cart (ajax)
 * 1.2) auth add to cart (ajax)
 * 1.3) guest add to cart (rest)
 * 1.4) auth add to cart (rest)
 * 2) checkout
 * 2.1) guest checkout (ajax)
 * 2.2) auth checkout (ajax)
 * 2.3) guest checkout (rest)
 * 2.4) auth checkout (rest)
 * 3) registration
 * 3.1) guest registration (ajax)
 * 3.2) guest registration (rest)
 * 4) collect data from checkout
 * 4.1) guest checkout (ajax)
 * 4.2) auth checkout (rest)
 * 5) send feedback
 */
class Woocommerce extends IntegrationByClassBase
{
    private $event_token = null;

    /**
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function doPublicWork()
    {
        global $apbct;

        // honeypot
        add_filter('woocommerce_checkout_fields', [$this, 'addHoneypotField']);

        // add to cart
        if ( ! apbct_is_user_logged_in() && $apbct->settings['forms__wc_add_to_cart'] ) {
            add_filter('woocommerce_add_to_cart_validation', [$this, 'addToCartUnloggedUser'], 10, 6);
            add_filter('woocommerce_store_api_add_to_cart_data', [$this, 'storeApiAddToCartData'], 10, 2);
        }

        // checkout
        if ( $apbct->settings['forms__wc_checkout_test'] == 1 ) {
            $this->addActions();
        }

        // registration
        if ( !$apbct->settings['forms__wc_register_from_order'] && (Request::get('wc-ajax') === 'checkout' || Request::get('wc-ajax') === 'complete_order') ) {
            remove_filter('woocommerce_registration_errors', 'ct_registration_errors', 1);
        } else {
            add_filter('woocommerce_registration_errors', 'ct_registration_errors', 1, 3);
        }

        // collect data for spam orders
        add_filter('woocommerce_register_shop_order_post_statuses', [$this, 'addOrdersSpamStatus']);
        add_filter('wc_order_statuses', [$this, 'addOrdersSpamStatusSelect']);
        add_action('parse_query', [$this, 'addOrdersSpamStatusHideFromList']);
        add_filter('bulk_actions-edit-shop_order', [$this, 'addSpamActionToBulk']);
        add_filter('handle_bulk_actions-edit-shop_order', [$this, 'addSpamActionToBulkHandle'], 10, 3);
    }

    public function doAjaxWork()
    {
        global $apbct;

        // checkout
        if ( $apbct->settings['forms__wc_checkout_test'] == 1 ) {
            $this->addActions();
        }

        // Restore Spam Order
        add_action('wp_ajax_apbct_restore_spam_order', array(WcSpamOrdersFunctions::class, 'restoreOrderAction'));
    }

    public function doAdminWork()
    {
        add_action('admin_menu', function () {
            add_submenu_page(
                'woocommerce',
                __("WooCommerce spam orders", 'cleantalk-spam-protect'),
                __("WooCommerce spam orders", 'cleantalk-spam-protect'),
                'activate_plugins',
                'apbct_wc_spam_orders',
                function () {
                    ?>
                    <div class="wrap">
                        <form action="" method="POST">
                        <?php
                        $list_table = new \Cleantalk\ApbctWP\WcSpamOrdersListTable();
                        $list_table->display();
                        ?>
                        </form>
                    </div>
                    <?php
                }
            );
        });
    }

    public function addActions()
    {
        global $_cleantalk_hooked_actions;

        add_action('wp_ajax_nopriv_woocommerce_checkout', 'ct_ajax_hook', 1);
        add_action('wp_ajax_woocommerce_checkout', 'ct_ajax_hook', 1);
        $_cleantalk_hooked_actions[] = 'woocommerce_checkout';
        $_cleantalk_hooked_actions[] = 'wcfm_ajax_controller';
        add_action('woocommerce_after_checkout_validation', [$this, 'checkoutCheck'], 1, 2);
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'checkoutCheckFromRest'], 1, 1);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'addRequestIdToOrderMeta']);
        add_action('woocommerce_store_api_checkout_update_customer_from_request', [$this, 'storeApiCheckoutUpdateCustomerFromRequest'], 10, 2);
    }

    public function addHoneypotField($fields)
    {
        if (apbct_exclusions_check__url()) {
            return $fields;
        }

        global $apbct;

        if ( $apbct->settings['data__honeypot_field'] ) {
            $fields['billing']['wc_apbct_email_id'] = array(
                'id'            => 'wc_apbct_email_id',
                'type'          => 'text',
                'label'         => '',
                'placeholder'   => '',
                'required'      => false,
                'class'         => array('form-row-wide', 'wc_apbct_email_id'),
                'clear'         => true,
                'autocomplete'  => 'off'
            );
        }

        return $fields;
    }

    public static function getCompletedOrders()
    {
        global $wpdb;

        $wc_orders_sql = '';
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true)) {
            $wc_orders_sql = " AND NOT EXISTS (SELECT posts.* FROM {$wpdb->posts} AS posts"
                . " INNER JOIN {$wpdb->postmeta} AS postmeta"
                . " WHERE posts.post_type = 'shop_order'"
                . " AND posts.post_status = 'wc-completed'"
                . " AND posts.ID = postmeta.post_id"
                . " AND postmeta.meta_key = '_customer_user'"
                . " AND postmeta.meta_value = {$wpdb->users}.ID)";
        }

        return $wc_orders_sql;
    }

    public function checkoutCheck($_data, $errors)
    {
        global $apbct, $cleantalk_executed;

        if ( count($errors->errors) ) {
            return;
        }

        if ( $apbct->settings['data__protect_logged_in'] == 0 && is_user_logged_in() ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

            return;
        }

        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $_POST);

        //Getting request params
        $ct_temp_msg_data = ct_get_fields_any($input_array);

        $sender_email    = isset($ct_temp_msg_data['email']) ? $ct_temp_msg_data['email'] : '';
        $sender_emails_array = isset($ct_temp_msg_data['emails_array']) ? $ct_temp_msg_data['emails_array'] : null;
        $sender_nickname = isset($ct_temp_msg_data['nickname']) ? $ct_temp_msg_data['nickname'] : '';
        $subject         = isset($ct_temp_msg_data['subject']) ? $ct_temp_msg_data['subject'] : '';
        $message         = isset($ct_temp_msg_data['message']) ? $ct_temp_msg_data['message'] : array();

        if ( $subject != '' ) {
            $message = array_merge(array('subject' => $subject), $message);
        }

        $post_info = array();
        $post_info['comment_type'] = 'order';
        $post_info['post_url']     = Server::get('HTTP_REFERER');

        $base_call_data = array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => $post_info,
            'sender_info'     => array('sender_url' => null, 'sender_emails_array' => $sender_emails_array)
        );

        $base_call_result = apbct_base_call($base_call_data);

        if ( $apbct->settings['forms__wc_register_from_order'] ) {
            $cleantalk_executed = false;
        }

        if ( isset($base_call_result['ct_result']) ) {
            $ct_result = $base_call_result['ct_result'];

            // Get request_id and save to static $hash
            ct_hash($ct_result->id);

            if ( $ct_result->allow == 0 ) {
                if ( $apbct->settings['data__wc_store_blocked_orders'] ) {
                    $this->storeBlockedOrder();
                }
                wp_send_json(array(
                    'result'   => 'failure',
                    'messages' => "<ul class=\"woocommerce-error\"><li>" . $ct_result->comment . "</li></ul>",
                    'refresh'  => 'false',
                    'reload'   => 'false'
                ));
            }
        }
    }

    /**
     * @param $order
     * @return void
     * @psalm-suppress UndefinedClass
     */
    public function checkoutCheckFromRest($order)
    {
        global $apbct, $cleantalk_executed;

        if ( is_null($order) || ! ($order instanceof \WC_Order) ) {
            return;
        }

        $sender_email    = $order->get_billing_email();
        $sender_nickname = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $message         = $order->get_customer_note();

        $post_info = array();
        $post_info['comment_type'] = 'order';
        $post_info['post_url']     = Server::get('HTTP_REFERER');

        $base_call_data = array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => $post_info,
            'sender_info'     => array('sender_url' => null),
            'event_token'     => $this->event_token,
        );

        $base_call_result = apbct_base_call($base_call_data);

        if ( $apbct->settings['forms__wc_register_from_order'] ) {
            $cleantalk_executed = false;
        }

        if ( isset($base_call_result['ct_result']) ) {
            $ct_result = $base_call_result['ct_result'];

            // Get request_id and save to static $hash
            ct_hash($ct_result->id);

            if ( $ct_result->allow == 0 ) {
                if ( $apbct->settings['data__wc_store_blocked_orders'] ) {
                    $this->storeBlockedOrder();
                }

                if ( $order->get_status() === 'checkout-draft' ) {
                    try {
                        $order->delete(true);
                    } catch (\Exception $e) {
                        error_log('Error deleting order: ' . $e->getMessage());
                    }
                }

                if ( class_exists('\Automattic\WooCommerce\StoreApi\Exceptions\RouteException') ) {
                    /** @psalm-suppress InvalidThrow */
                    throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                        'woocommerce_store_api_checkout_order_processed',
                        $ct_result->comment,
                        403
                    );
                }
            }
        }
    }

    /**
     * @return void
     * @psalm-suppress UndefinedFunction
     */
    public function storeBlockedOrder()
    {
        global $wpdb;

        $query = 'INSERT INTO ' . APBCT_TBL_WC_SPAM_ORDERS . ' (order_details, customer_details) 
            VALUES (%s, %s) 
            ON DUPLICATE KEY UPDATE order_details = %s, customer_details = %s';

        // store blocked order from ajax checkout
        if (!empty($_POST)) {
            $prepared_query = $wpdb->prepare($query, [
                json_encode(wc()->session->cart),
                json_encode($_POST),
                json_encode(wc()->session->cart),
                json_encode($_POST),
            ]);

            $wpdb->query($prepared_query);

            return;
        }

        // store blocked order from rest checkout
        $customer_data = [];
        if (isset(wc()->customer)) {
            $customer = wc()->customer;
            $customer_data['billing_first_name'] = $customer->get_billing_first_name();
            $customer_data['billing_last_name'] = $customer->get_billing_last_name();
            $customer_data['billing_company'] = $customer->get_billing_company();
            $customer_data['billing_address_1'] = $customer->get_billing_address_1();
            $customer_data['billing_address_2'] = $customer->get_billing_address_2();
            $customer_data['billing_city'] = $customer->get_billing_city();
            $customer_data['billing_state'] = $customer->get_billing_state();
            $customer_data['billing_postcode'] = $customer->get_billing_postcode();
            $customer_data['billing_country'] = $customer->get_billing_country();
            $customer_data['billing_email'] = $customer->get_billing_email();
            $customer_data['billing_phone'] = $customer->get_billing_phone();
            $customer_data['shipping_first_name'] = $customer->get_shipping_first_name();
            $customer_data['shipping_last_name'] = $customer->get_shipping_last_name();
            $customer_data['shipping_company'] = $customer->get_shipping_company();
            $customer_data['shipping_address_1'] = $customer->get_shipping_address_1();
            $customer_data['shipping_address_2'] = $customer->get_shipping_address_2();
            $customer_data['shipping_city'] = $customer->get_shipping_city();
            $customer_data['shipping_state'] = $customer->get_shipping_state();
            $customer_data['shipping_postcode'] = $customer->get_shipping_postcode();
            $customer_data['shipping_country'] = $customer->get_shipping_country();
        }

        $prepared_query = $wpdb->prepare($query, [
            json_encode(wc()->session->cart),
            json_encode($customer_data),
            json_encode(wc()->session->cart),
            json_encode($customer_data),
        ]);

        $wpdb->query($prepared_query);
    }

    public function addRequestIdToOrderMeta($order_id)
    {
        $request_id = ct_hash();

        if (!empty($request_id)) {
            update_post_meta($order_id, 'cleantalk_order_request_id', sanitize_key($request_id));
        }
    }

    /**
     * @return bool
     * @psalm-suppress UndefinedFunction, PossiblyUnusedReturnValue
     */
    public function addToCartUnloggedUser()
    {
        global $apbct;

        $data = Post::get('data');
        if (is_array($data) && isset($data['ct_bot_detector_event_token'])) {
            $event_token = $data['ct_bot_detector_event_token'];
        } elseif ( Get::get('ct_bot_detector_event_token') ) {
            $event_token = Get::get('ct_bot_detector_event_token');
        } else {
            $event_token = null;
        }

        $message = apply_filters('apbct__filter_post', $_POST);

        $post_info = array();
        $post_info['comment_type'] = 'order__add_to_cart';
        $post_info['post_url']     = Sanitize::cleanUrl(Server::get('HTTP_REFERER'));

        if ( ! $apbct->stats['no_cookie_data_taken'] ) {
            apbct_form__get_no_cookie_data();
        }

        $base_call_result = apbct_base_call(
            array(
                'message'     => $message,
                'post_info'   => $post_info,
                'js_on'       => apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true),
                'sender_info' => array('sender_url' => null),
                'exception_action' => false,
                'event_token' => $event_token,
            )
        );

        if ( isset($base_call_result['ct_result']) ) {
            $ct_result = $base_call_result['ct_result'];

            if ( $ct_result->allow == 0 && function_exists('wc_add_notice') ) {
                wc_add_notice($ct_result->comment, 'error');
                return false;
            }
        }

        return true;
    }

    /**
     * @param $add_to_cart_data
     * @param $request
     * @return bool
     * @psalm-suppress PossiblyUnusedReturnValue, UndefinedClass
     */
    public function storeApiAddToCartData($add_to_cart_data, $request)
    {
        global $apbct;

        if ( ! $apbct->stats['no_cookie_data_taken'] && $request->get_param('ct_no_cookie_hidden_field') ) {
            apbct_form__get_no_cookie_data(
                ['ct_no_cookie_hidden_field' => $request->get_param('ct_no_cookie_hidden_field')],
                false
            );
        }

        $event_token = $request->get_param('event_token');
        if ($event_token && $event_token !== 'undefined' && $event_token !== 'null') {
            $token = @json_decode($event_token, true);
            if (is_array($token) && isset($token['value'])) {
                $event_token = $token['value'];
            }
        }

        $message = apply_filters('apbct__filter_post', $_POST);
        $post_info = array();
        $post_info['comment_type'] = 'order__add_to_cart';
        $post_info['post_url']     = Sanitize::cleanUrl(Server::get('HTTP_REFERER'));
        $base_call_result = apbct_base_call(
            array(
                'message'     => $message,
                'post_info'   => $post_info,
                'js_on'       => apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true),
                'sender_info' => array('sender_url' => null),
                'exception_action' => false,
                'event_token' => $event_token,
            )
        );

        if ( isset($base_call_result['ct_result']) ) {
            $ct_result = $base_call_result['ct_result'];

            if ( $ct_result->allow == 0 && function_exists('wc_add_notice') ) {
                if ( class_exists('\Automattic\WooCommerce\StoreApi\Exceptions\RouteException') ) {
                    /** @psalm-suppress InvalidThrow */
                    throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                        'woocommerce_rest_cart_item_exists',
                        $ct_result->comment,
                        400
                    );
                }
            }
        }

        return $add_to_cart_data;
    }

    /**
     * @param $customer
     * @param $request
     * @return void
     * @psalm-suppress PossiblyUnusedParam
     */
    public function storeApiCheckoutUpdateCustomerFromRequest($customer, $request)
    {
        global $apbct;

        if ( ! $apbct->stats['no_cookie_data_taken'] ) {
            apbct_form__get_no_cookie_data(
                ['ct_no_cookie_hidden_field' => $request->get_param('ct_no_cookie_hidden_field')],
                false
            );
        }

        $event_token = $request->get_param('event_token');
        if ($event_token && $event_token !== 'undefined' && $event_token !== 'null') {
            $token = @json_decode($event_token, true);
            if (is_array($token) && isset($token['value'])) {
                $event_token = $token['value'];
            }
        }

        $this->event_token = $event_token;
    }

    /**
     * @param $order
     * @return void
     * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException
     * @psalm-suppress UndefinedClass, UnusedParam, InvalidThrow
     */
    public static function storeApiCheckoutOrderProcessed($order)
    {
        global $ct_registration_error_comment;

        if (class_exists('\Automattic\WooCommerce\StoreApi\Exceptions\RouteException')) {
            throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                'woocommerce_store_api_checkout_order_processed',
                $ct_registration_error_comment,
                403
            );
        }
    }

    /**
     * Register the new 'spam' status
     */
    public function addOrdersSpamStatus($order_statuses)
    {
        $order_statuses['wc-spamorder'] = array(
            'label' => 'Spam',
            'public' => false,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop(
                'Spam <span class="count">(%s)</span>',
                'Spam <span class="count">(%s)</span>',
                'cleantalk-spam-protect'
            ),
        );

        return $order_statuses;
    }

    /**
     * Enable orders filtering by 'spam' status
     */
    public function addOrdersSpamStatusSelect($order_statuses)
    {
        $order_statuses['wc-spamorder'] = 'Spam';
        return $order_statuses;
    }

    /**
     * Don't show orders marked 'spam' in the common order list
     */
    public function addOrdersSpamStatusHideFromList($query)
    {
        global $pagenow;

        $query_vars = &$query->query_vars;

        if ( $pagenow == 'edit.php'
            && isset($query_vars['post_type'])
            && $query_vars['post_type'] == 'shop_order'
            && isset($query_vars['post_status'])
            && is_array($query_vars['post_status'])
            && ( $key = array_search('wc-spamorder', $query_vars['post_status']) ) !== false
        ) {
            unset($query_vars['post_status'][$key]);
        }
    }

    /**
     * Add bulk actions: 'Mark as spam' and 'Unmark as spam'
     */
    public function addSpamActionToBulk($actions)
    {
        if ( get_query_var('post_status') === 'wc-spamorder' ) {
            $actions['unspamorder'] = __('Unmark as spam', 'cleantalk-spam-protect');
        } else {
            $actions['spamorder'] = __('Mark as spam', 'cleantalk-spam-protect');
        }

        return $actions;
    }

    /**
     * The bulk actions 'Mark as spam' and 'Unmark as spam' handler
     * @param $redirect
     * @param $action
     * @param $ids
     *
     * @return mixed|string
     * @psalm-suppress UndefinedClass, PossiblyUnusedReturnValue
     */
    public function addSpamActionToBulkHandle($redirect, $action, $ids)
    {
        if ( $action !== 'spamorder' &&  $action !== 'unspamorder' ) {
            return $redirect;
        }

        // spam orders
        $spam_ids = array();

        foreach ($ids as $order_id) {
            $order = new WC_Order((int)$order_id);
            if ( $action === 'unspamorder' ) {
                $order->update_status('wc-on-hold');
            } else {
                $spam_ids[] = $order_id;
                $order->update_status('wc-spamorder');
            }
        }

        // Send feedback to API
        if (!empty($spam_ids)) {
            $this->ordersSendFeedback($spam_ids);
        }

        return add_query_arg(
            array(
                'bulk_action' => 'marked_' . $action,
                'changed' => count($ids),
            ),
            $redirect
        );
    }

    /**
     * @param array $spam_ids
     * @param string $orders_status
     */
    public function ordersSendFeedback(array $spam_ids, $orders_status = '0')
    {
        if (empty($spam_ids)) {
            return;
        }

        global $apbct;
        $request_ids = array();
        foreach ($spam_ids as $spam_id) {
            $request_id = get_post_meta($spam_id, 'cleantalk_order_request_id', true);
            if ($request_id) {
                $request_ids[] = $request_id . ':' . $orders_status;
            }
        }

        if (!empty($request_ids)) {
            $feedback = implode(';', $request_ids);

            try {
                $ct_request = new CleantalkRequest(array(
                    // General
                    'auth_key' => $apbct->api_key,
                    // Additional
                    'feedback' => $feedback,
                ));

                $ct = new Cleantalk();

                // Server URL handling
                $config             = ct_get_server();
                $ct->server_url     = APBCT_MODERATE_URL;
                $ct->work_url       = isset($config['ct_work_url']) && preg_match('/http:\/\/.+/', $config['ct_work_url']) ? $config['ct_work_url'] : null;
                $ct->server_ttl     = isset($config['ct_server_ttl']) ? $config['ct_server_ttl'] : null;
                $ct->server_changed = isset($config['ct_server_changed']) ? $config['ct_server_changed'] : null;

                $ct->sendFeedback($ct_request);
            } catch (\Exception $e) {
            }
        }
    }
}