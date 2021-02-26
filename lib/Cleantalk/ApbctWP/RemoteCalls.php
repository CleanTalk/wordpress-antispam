<?php


namespace Cleantalk\ApbctWP;

use Cleantalk\Variables\Get;
use Cleantalk\ApbctWP\Cron;

class RemoteCalls
{
    
    const COOLDOWN = 10;
    
    /**
     * Checking if the current request is the Remote Call
     *
     * @return bool
     */
    public static function check() {
        
        return
            Get::get('spbc_remote_call_token') &&
            Get::get('spbc_remote_call_action') &&
            Get::get('plugin_name') &&
            in_array(Get::get('plugin_name'), array('antispam','anti-spam', 'apbct') );
    }
    
    /**
     * Execute corresponding method of RemoteCalls if exists
     *
     * @return void|string
     */
    public static function perform(){
        
        global $apbct;
        
        $action = strtolower( Get::get( 'spbc_remote_call_action' ) );
        $token  = strtolower( Get::get( 'spbc_remote_call_token' ) );
        
        if(isset($apbct->remote_calls[$action])){
            
            $cooldown = isset($apbct->remote_calls[$action]['cooldown']) ? $apbct->remote_calls[$action]['cooldown'] : self::COOLDOWN;
            
            // Return OK for test remote calls
            if ( Get::get( 'test' ) )
                die('OK');
            
            if( time() - $apbct->remote_calls[ $action ]['last_call'] >= $cooldown || ( $action === 'sfw_update' && isset($_GET['file_urls'] ) ) ){
                
                $apbct->remote_calls[$action]['last_call'] = time();
                $apbct->save('remote_calls');
                
                // Check API key
                if($token == strtolower(md5($apbct->api_key)) ){
                    
                    // Flag to let plugin know that Remote Call is running.
                    $apbct->rc_running = true;
                    
                    $action = 'action__'.$action;
                    
                    if( method_exists( 'Cleantalk\ApbctWP\RemoteCalls', $action ) ){

	                    // Delay before perform action;
	                    if ( Get::get( 'delay' ) )
		                    sleep( Get::get( 'delay' ) );

	                    $action_result = RemoteCalls::$action();
	                    $response = empty( $action_result['error'] )
		                    ? 'OK'
		                    : 'FAIL ' . json_encode( array( 'error' => $action_result['error'] ) );

	                    if( ! Get::get( 'continue_execution' ) ){
		                    die( $response );
	                    }

	                    return $response;

                    }else
                        $out = 'FAIL '.json_encode(array('error' => 'UNKNOWN_ACTION_METHOD'));
                }else
                    $out = 'FAIL '.json_encode(array('error' => 'WRONG_TOKEN'));
            }else
                $out = 'FAIL '.json_encode(array('error' => 'TOO_MANY_ATTEMPTS'));
        }else
            $out = 'FAIL '.json_encode(array('error' => 'UNKNOWN_ACTION'));
        
        die($out);
    }
    
    /**
     * Close renew banner
     *
     * @return string
     */
    public static function action__close_renew_banner(){
        
        global $apbct;
        
        $apbct->data['notice_trial'] = 0;
        $apbct->data['notice_renew'] = 0;
        $apbct->saveData();
        Cron::updateTask( 'check_account_status', 'ct_account_status_check', 86400 );
        
        return 'OK';
    }
    
    /**
     * SFW update
     *
     * @return string
     */
    public static function action__sfw_update(){
        
        global $apbct;
        
        $result = ct_sfw_update( $apbct->api_key, true );
        $apbct->error_toggle( ! empty( $result['error'] ), 'sfw_update', $result);
        
        return $result;
    }
    
    /**
     * SFW send logs
     *
     * @return string
     */
    public static function action__sfw_send_logs(){
    
        return ct_sfw_send_logs();
    }
    
    /**
     * Update plugin
     */
    public static function action__update_plugin(){
        add_action( 'wp', 'apbct_rc__update', 1 );
    }
    
    /**
     * Install plugin
     */
    public static function action__install_plugin(){
        add_action( 'wp', 'apbct_rc__install_plugin', 1 );
    }
    
    /**
     * Activate plugin
     */
    public static function action__activate_plugin(){
        return apbct_rc__activate_plugin( $_GET['plugin'] );
    }
    
    /**
     * Insert API key
     */
    public static function action__insert_auth_key(){
        return apbct_rc__insert_auth_key( $_GET['auth_key'], $_GET['plugin'] );
    }
    
    /**
     * Update settins
     */
    public static function action__update_settings(){
        return apbct_rc__update_settings( $_GET );
    }
    
    /**
     * Deactivate plugin
     */
    public static function action__deactivate_plugin(){
        add_action( 'plugins_loaded', 'apbct_rc__deactivate_plugin', 1 );
    }
    
    /**
     * Uninstall plugin
     */
    public static function action__uninstall_plugin(){
        add_action( 'plugins_loaded', 'apbct_rc__uninstall_plugin', 1 );
    }
    
}
