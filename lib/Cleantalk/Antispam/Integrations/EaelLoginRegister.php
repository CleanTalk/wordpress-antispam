<?php


namespace Cleantalk\Antispam\Integrations;


class EaelLoginRegister extends IntegrationBase {

	public function getDataForChecking( $argument ) {
		$data = ct_get_fields_any( $_POST );
		$data['register'] = true;
		return $data;
	}

	public function doBlock( $message ) {
		global $ct_comment;
		$ct_comment = $message;
		ct_die( null, null );
	}
}