<?php

define('APBCT_SEESION__LIVE_TIME', 86400);
define('APBCT_SEESION__CHANCE_TO_CLEAN', 5);

error_log(__FILE__ .':'.__LINE__ .': '.__FUNCTION__ ." \n".var_export('SESS!', true));
error_log(__FILE__ .':'.__LINE__ .': '.__FUNCTION__ ." \n".var_export($_POST, true));

if(isset($_POST['apbct_action'], $_POST['apbct_secret'])){
	
	if(apbct_check_secret($_POST['apbct_secret'])){
		
		define('SHORT_INIT', true);
		require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
				
		global $wpdb;
		
		// SET SESSION
		if($_POST['apbct_action'] == 'set_sessions'){
			
			apbct_sessions__remove_old();
			
			if($_POST['session_id']){
				
				if(preg_match('/^[a-z0-9]{32}$/', $_POST['session_id'], $matches)){
					
					$session = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * 
								FROM `".$wpdb->base_prefix."cleantalk_sessions`
								WHERE id = '%s'
								LIMIT 1;",
							$_POST['session_id']
						),
						OBJECT
					);
					
					if(!empty($session)){
						$data = json_encode($_POST['data']);
						$wpdb->update(
							$wpdb->base_prefix.'cleantalk_sessions',
							array('data' => $data, 'last_update' => date('Y-m-d H:i:s')),
							array('id' => $matches[0]),
							array('%s', '%s'),
							array('%s')
						);
						$out = array('result' => true, 'msg' => 'UPDATE_OK');
					}else{
						$data = json_encode($_POST['data']);
						$wpdb->insert(
							$wpdb->base_prefix.'cleantalk_sessions',
							array('id' => $matches[0], 'data' => $data, 'started' => date('Y-m-d H:i:s'), 'last_update' => date('Y-m-d H:i:s')),
							array('%s', '%s', '%s', '%s')
						);
						$out = array('result' => true, 'msg' => 'INSERT_OK');
					}
				}else
					$out = array('error' => true, 'err_msg' => 'SESS_ID_BAD');
			}else
				$out = array('error' => true, 'err_msg' => 'NO_SESS_ID_SET');
		
		// GET SESSION
		}elseif($_POST['apbct_action'] == 'get_sessions'){
			
			if($_POST['session_id']){
				
				$session = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * 
							FROM `".$wpdb->base_prefix."cleantalk_sessions`
							WHERE id = '%s'
							LIMIT 1;",
						$_POST['session_id']
					),
					OBJECT
				);
				
				if(!empty($session)){
					$out = array('result' => true, 'cookies' => json_decode($session[0]->data));
				}else
					$out = array('error' => true, 'err_msg' => 'NO_SESS_FOUND');
			}else
				$out = array('error' => true, 'err_msg' => 'NO_SESS_ID_SET');
			
		}else
			$out = array('error' => true, 'err_msg' => 'NO_ACTION_SET');
	}else
		$out = array('error' => true, 'err_msg' => 'SECRET_CHECK_FAIL');
	
	die(json_encode($out));
}

function apbct_check_secret($secret){
	if($secret == 'secret_nonce'){
		return true;
	}
	return false;
}

function apbct_sessions__remove_old(){
	if(rand(0, 1000) < APBCT_SEESION__CHANCE_TO_CLEAN){
		global $wpdb;
		$session = $wpdb->query(
			'DELETE
				FROM `'.$wpdb->base_prefix.'cleantalk_sessions`
				WHERE last_update < "'.date('Y-m-d H:i:s', time() - APBCT_SEESION_LIVE_TIME).'";'
		);
	}
}