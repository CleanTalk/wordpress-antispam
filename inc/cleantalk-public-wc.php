<?php
/**
 * Triggered when adding an item to the shopping cart
 * for un-logged users
 */
add_action( 'woocommerce_add_to_cart', 'apbct_wc__add_to_cart_unlogged_user', 10, 6 );

function apbct_wc__add_to_cart_unlogged_user( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ){
	/**
	 * Getting request params
	 * POST contains an array of product information
	 * Array
	 *(
	 *    [product_sku] => woo-beanie
	 *    [product_id] => 15
	 *    [quantity] => 1
	 *)
	 */
	$message = (isset($_POST) ? $_POST  : array());

	$post_info['comment_type'] = 'add_to_cart';
	$post_info['post_url'] = apbct_get_server_variable( 'HTTP_REFERER' );

	//Making a call
	$base_call_result = apbct_base_call(
		array(
			'message'         => $message,
			'post_info'       => $post_info,
			'js_on'           => apbct_js_test('ct_checkjs', $_COOKIE),
			'sender_info'     => array('sender_url' => null),
		)
	);

	$ct_result = $base_call_result['ct_result'];

	if ($ct_result->allow == 0) {
		wp_send_json(array(
			'result' => 'failure',
			'messages' => "<ul class=\"woocommerce-error\"><li>".$ct_result->comment."</li></ul>",
			'refresh' => 'false',
			'reload' => 'false',
			'response_type' => 'wc_add_to_cart_block'
		));
	}
}