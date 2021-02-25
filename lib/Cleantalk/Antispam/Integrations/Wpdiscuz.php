<?php


namespace Cleantalk\Antispam\Integrations;


class Wpdiscuz extends IntegrationBase {

	function getDataForChecking( $argument ) {

		return ct_get_fields_any( $_POST );

	}

	function doBlock( $message ) {

		wp_send_json_error( 'wc_error_email_text' );

	}
}