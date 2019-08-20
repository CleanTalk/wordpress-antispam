<?php

/**
 * Request class
 */
class CleantalkRequest {

     /**
     *  All http request headers
     * @var string
     */
     public $all_headers = null;
     
     /**
     *  IP address of connection
     * @var string
     */
     //public $remote_addr = null;
     
     /**
     *  Last error number
     * @var integer
     */
     public $last_error_no = null;
     
     /**
     *  Last error time
     * @var integer
     */
     public $last_error_time = null;
     
     /**
     *  Last error text
     * @var string
     */
     public $last_error_text = null;

    /**
     * User message
     * @var string
     */
    public $message = null;

    /**
     * Post example with last comments
     * @var string
     */
    public $example = null;

    /**
     * Auth key
     * @var string
     */
    public $auth_key = null;

    /**
     * Engine
     * @var string
     */
    public $agent = null;

    /**
     * Is check for stoplist,
     * valid are 0|1
     * @var int
     */
    public $stoplist_check = null;

    /**
     * Language server response,
     * valid are 'en' or 'ru'
     * @var string
     */
    public $response_lang = null;

    /**
     * User IP
     * @var strings
     */
    public $sender_ip = null;

    /**
     * User email
     * @var strings
     */
    public $sender_email = null;

    /**
     * User nickname
     * @var string
     */
    public $sender_nickname = null;

    /**
     * Sender info JSON string
     * @var string
     */
    public $sender_info = null;

    /**
     * Post info JSON string
     * @var string
     */
    public $post_info = null;

    /**
     * Is allow links, email and icq,
     * valid are 1|0
     * @var int
     */
    public $allow_links = null;

    /**
     * Time form filling
     * @var int
     */
    public $submit_time = null;
    
    public $x_forwarded_for = '';
    public $x_real_ip = '';

    /**
     * Is enable Java Script,
     * valid are 0|1|2
	 * Status:
	 *  null - JS html code not inserted into phpBB templates
	 *  0 - JS disabled at the client browser
	 *  1 - JS enabled at the client broswer
     * @var int
     */
    public $js_on = null;

    /**
     * user time zone
     * @var string
     */
    public $tz = null;

    /**
     * Feedback string,
     * valid are 'requset_id:(1|0)'
     * @var string
     */
    public $feedback = null;

    /**
     * Phone number
     * @var type 
     */
    public $phone = null;
    
    /**
    * Method name
    * @var string
    */
    public $method_name = 'check_message'; 

    /**
     * Fill params with constructor
     * @param type $params
     */
    public function __construct($params = null) {
		
		// IPs
		$this->sender_ip       = isset($params['sender_ip'])       ? (string)$params['sender_ip']       : null;
		$this->x_forwarded_for = isset($params['x_forwarded_for']) ? (string)$params['x_forwarded_for'] : null;
		$this->x_real_ip       = isset($params['x_real_ip'])       ? (string)$params['x_real_ip']       : null;

		// Misc
		$this->agent           = isset($params['agent'])            ? (string)$params['agent']                    : null;
		$this->auth_key        = isset($params['auth_key'])         ? (string)$params['auth_key']                 : null;
		$this->sender_email    = isset($params['sender_email'])     ? (string)$params['sender_email']             : null;
		$this->sender_nickname = !empty($params['sender_nickname']) ? (string)$params['sender_nickname']          : null;
		$this->phone           = !empty($params['phone'])           ? (string)$params['phone']                    : null;
		$this->js_on           = isset($params['js_on'])            ? (int)$params['js_on']                       : null;
		$this->submit_time     = isset($params['submit_time'])      ? (int)$params['submit_time']                 : null;
		$this->post_info       = isset($params['post_info'])        ? (string)json_encode($params['post_info'])   : null;
		$this->sender_info     = isset($params['sender_info'])      ? (string)json_encode($params['sender_info']) : null;
		
		// Message
		$this->message         = !empty($params['message']) ? serialize($params['message']) : null;
		$this->example         = !empty($params['example']) ? serialize($params['example']) : null;
		
		// Feedback
		$this->feedback        = !empty($params['feedback']) ? $params['feedback'] : null;
				
    }

}
