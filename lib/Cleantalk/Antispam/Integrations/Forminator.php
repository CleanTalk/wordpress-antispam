<?php


namespace Cleantalk\Antispam\Integrations;


class Forminator extends IntegrationBase {

	function getDataForChecking( $argument ) {
		if( isset( $_POST ) ) {
			return ct_get_fields_any( $_POST );
		}
		return null;
	}

	function doBlock( $message ) {
		wp_send_json_error(
			array(
				'message' => $message,
				'success' => false,
				'errors'  => array(),
				'behav'   => 'behaviour-thankyou',
			)
		);
	}
}