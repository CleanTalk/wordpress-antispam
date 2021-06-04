<?php


namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\Firewall\SFW;
use Cleantalk\Variables\Get;

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
            if ( Get::get( 'test' ) ){
                die('OK');
            }
            
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
                        if ( Get::get( 'delay' ) ){
		                    sleep( Get::get( 'delay' ) );
		                    $params = $_GET;
		                    unset( $params['delay'] );
                            return Helper::http__request__rc_to_host(
                                Get::get( 'spbc_remote_action' ),
                                $params,
                                array( 'async' ),
                                false
                            );
                        }

	                    $out = RemoteCalls::$action();
                        
                        // Every remote call action handler should implement output or
	                    // If out is empty(), the execution will continue
	                    
                    }else
                        $out = 'FAIL '.json_encode(array('error' => 'UNKNOWN_ACTION_METHOD'));
                }else
                    $out = 'FAIL '.json_encode(array('error' => 'WRONG_TOKEN'));
            }else
                $out = 'FAIL '.json_encode(array('error' => 'TOO_MANY_ATTEMPTS'));
        }else
            $out = 'FAIL '.json_encode(array('error' => 'UNKNOWN_ACTION'));
    
        if( $out )
            die( $out );
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
        $cron = new Cron();
	    $cron->updateTask( 'check_account_status', 'ct_account_status_check', 86400 );
        
        return 'OK';
    }
    
    /**
     * SFW update
     *
     * @return string
     */
    public static function action__sfw_update(){
        global $apbct;
        $result = apbct_sfw_update__init();
        $apbct->error_toggle( ! empty( $result['error'] ), 'sfw_update', $result);
        die(empty($result['error']) ? 'OK' : 'FAIL '.json_encode(array('error' => $result['error'])));
    }
    
    /**
     * SFW update
     *
     * @return string
     */
    public static function action__sfw_update__worker(){
        
        global $apbct;
        $result = apbct_sfw_update__worker();
        
        $apbct->error_toggle( ! empty( $result['error'] ), 'sfw_update', $result);
    
        if( ! empty( $result['error'] ) ){
    
            apbct_sfw_update__cleanData();
            
            die( 'FAIL ' . json_encode( array( 'error' => $result['error'] ) ) );
        }
    
        die( 'OK' );
    
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
    
    public static function action__debug(){
        
        global $apbct;
        
        $out['stats'] = $apbct->stats;
        $out['settings'] = $apbct->settings;
        $out['fw_stats'] = $apbct->fw_stats;
        $out['data'] = $apbct->data;
        $out['cron'] = $apbct->cron;
        $out['errors'] = $apbct->errors;
        
        array_walk( $out, function(&$val, $key){
            $val = (array) $val;
        });
        
        array_walk_recursive( $out, function(&$val, $key){
            if( is_int( $val ) && preg_match( '@^\d{9,11}$@', $val ) ){
                $val = date( 'Y-m-d H:i:s', $val );
            }
        });
        
        if( APBCT_WPMS ){
            $out['network_settings'] = $apbct->network_settings;
            $out['network_data'] = $apbct->network_data;
        }
        
        $out = print_r($out, true);
        $out = str_replace("\n", "<br>", $out);
        $out = preg_replace("/[^\S]{4}/", "&nbsp;&nbsp;&nbsp;&nbsp;", $out);
        
        die( $out );
        
    }
    
}
