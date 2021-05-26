<?php


namespace Cleantalk\Antispam\Integrations;


class HappyForm extends IntegrationBase {

	public function getDataForChecking( $argument ) {
		if( isset( $_POST['happyforms_form_id'] ) ) {
			$data = array();
			foreach( $_POST as $key => $value ) {
				if( strpos( $key, $_POST['happyforms_form_id'] ) !== false ) {
					$data[$key] = $value;
				}
			}
			return ! empty( $data ) ? ct_get_fields_any($data) : null;
		}
		return null;
	}

	public function doBlock( $message ) {

		add_filter( 'happyforms_validate_submission', function( $is_valid, $request, $form ){
			return false;
		}, 1, 3 );

	}
}