<?php


namespace Cleantalk\ApbctWP;


class Ajax {

	/**
	 * string
	 */
	private $table_prefix;

	public function __construct()
	{
		define( 'DOING_AJAX', true );
		define( 'SHORTINIT', true );

		require_once( $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php' );
		require_once( $_SERVER['DOCUMENT_ROOT'] . '/wp-includes/pluggable.php' );

		$this->checkRequest();

		$this->setHeaders();

		$this->handleRequest( $_REQUEST );
	}

	private function checkRequest()
	{
		if ( empty( $_REQUEST['action'] ) ) {
			http_response_code( 400 );
			die( '0' );
		}

		$this->check_ajax_referer( 'ct_secret_stuff' );
	}

	private function setHeaders()
	{
		header( 'Content-Type: text/html;' );
		header( 'X-Robots-Tag: noindex' );
		send_nosniff_header();
		nocache_headers();
	}

	private function handleRequest( $request )
	{
		require_once( __DIR__ . '/../../../inc/cleantalk-ajax-handlers.php' );

		global $apbct;

		switch( $request['action'] ) {
			case 'apbct_js_keys__get' :
				apbct_js_keys__get();
				break;
			case 'apbct_email_check_before_post' :
				if ( $apbct->settings['data__email_check_before_post'] ) {
					apbct_email_check_before_post();
				}
				break;
			case 'apbct_alt_session__save__AJAX':
				// Using alternative sessions with ajax
				if( $apbct->settings['data__set_cookies'] == 2 && $apbct->settings['data__set_cookies__alt_sessions_type'] == 2 ){
					apbct_alt_session__save__AJAX();
				}
				break;
			case 'apbct_alt_session__get__AJAX' :
				// Using alternative sessions with ajax
				if( $apbct->settings['data__set_cookies'] == 2 && $apbct->settings['data__set_cookies__alt_sessions_type'] == 2 ){
					apbct_alt_session__get__AJAX();
				}
				break;
			default :
				return;
		}

	}


	/**
	 * Verifies the Ajax request to prevent processing requests external of the blog.
	 * @inheritDoc check_ajax_referer()
	 */
	private function check_ajax_referer( $action, $query_arg = false )
	{
		$nonce = '';

		if ( $query_arg && isset( $_REQUEST[ $query_arg ] ) ) {
			$nonce = $_REQUEST[ $query_arg ];
		} elseif ( isset( $_REQUEST['_ajax_nonce'] ) ) {
			$nonce = $_REQUEST['_ajax_nonce'];
		} elseif ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = $_REQUEST['_wpnonce'];
		}

		$result = $this->wp_verify_nonce( $nonce, $action );

		if ( false === $result ) {
			http_response_code( 403 );
			die( -1 );
		}

		return $result;

	}

	/**
	 * Verifies that a correct security nonce was used with time limit.
	 * @inheritDoc wp_verify_nonce()
	 */
	private function wp_verify_nonce( $nonce, $action )
	{
		$nonce = (string) $nonce;
		$uid   = apply_filters( 'nonce_user_logged_out', 0, $action );

		if ( empty( $nonce ) ) {
			return false;
		}

		$token = '';
		$i     = $this->wp_nonce_tick();

		// Nonce generated 0-12 hours ago.
		$expected = substr( wp_hash( $i . '|' . $action . '|' . $uid . '|' . $token, 'nonce' ), -12, 10 );
		if ( hash_equals( $expected, $nonce ) ) {
			return 1;
		}

		// Nonce generated 12-24 hours ago.
		$expected = substr( wp_hash( ( $i - 1 ) . '|' . $action . '|' . $uid . '|' . $token, 'nonce' ), -12, 10 );
		if ( hash_equals( $expected, $nonce ) ) {
			return 2;
		}

		// Invalid nonce.
		return false;

	}

	/**
	 * Returns the time-dependent variable for nonce creation.
	 * @inheritDoc wp_nonce_tick()
	 */
	private function wp_nonce_tick()
	{
		$nonce_life = apply_filters( 'nonce_life', DAY_IN_SECONDS );

		return ceil( time() / ( $nonce_life / 2 ) );
	}

}

new Ajax();
