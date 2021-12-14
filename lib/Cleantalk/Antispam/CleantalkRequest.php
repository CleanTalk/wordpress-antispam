<?php

namespace Cleantalk\Antispam;

/**
 * Request class
 * @psalm-suppress PossiblyUnusedProperty
 */
class CleantalkRequest
{
    /**
     *  All http request headers
     * @var string
     */
    public $all_headers;

    /**
     *  IP address of connection
     * @var string
     */

    /**
     *  Last error number
     * @var integer
     */
    public $last_error_no;

    /**
     *  Last error time
     * @var integer
     */
    public $last_error_time;

    /**
     *  Last error text
     * @var string
     */
    public $last_error_text;

    /**
     * User message
     * @var string
     */
    public $message;

    /**
     * Post example with last comments
     * @var string
     */
    public $example;

    /**
     * Auth key
     * @var string
     */
    public $auth_key;

    /**
     * Engine
     * @var string
     */
    public $agent;

    /**
     * Is check for stoplist,
     * valid are 0|1
     * @var int
     */
    public $stoplist_check;

    /**
     * Language server response,
     * valid are 'en' or 'ru'
     * @var string
     */
    public $response_lang;

    /**
     * User IP
     * @var string
     */
    public $sender_ip;

    /**
     * User email
     * @var string
     */
    public $sender_email;

    /**
     * User nickname
     * @var string
     */
    public $sender_nickname;

    /**
     * Sender info JSON string
     * @var string
     */
    public $sender_info;

    /**
     * Post info JSON string
     * @var string
     */
    public $post_info;

    /**
     * Is allow links, email and icq,
     * valid are 1|0
     * @var int
     */
    public $allow_links;

    /**
     * Time form filling
     * @var int
     */
    public $submit_time;

    /**
     * @var string|null
     */
    public $x_forwarded_for = '';

    /**
     * @var string|null
     */
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
    public $js_on;

    /**
     * user time zone
     * @var string
     */
    public $tz;

    /**
     * Feedback string,
     * valid are 'requset_id:(1|0)'
     * @var string
     */
    public $feedback;

    /**
     * Phone number
     * @var string
     */
    public $phone;

    /**
     * Method name
     * @var string
     */
    public $method_name = 'check_message';

    /**
     * @var int|null
     */
    public $honeypot_field;

    /**
     * @var int|null
     */
    public $exception_action;

    /**
     * Fill params with constructor
     *
     * @param array $params
     *
     * @psalm-suppress PossiblyUnusedMethod
     * @psalm-suppress InvalidPropertyAssignmentValue
     */
    public function __construct($params = null)
    {
        // IPs
        $this->sender_ip       = isset($params['sender_ip']) ? (string)$params['sender_ip'] : null;
        $this->x_forwarded_for = isset($params['x_forwarded_for']) ? (string)$params['x_forwarded_for'] : null;
        $this->x_real_ip       = isset($params['x_real_ip']) ? (string)$params['x_real_ip'] : null;

        // Misc
        $this->agent        = isset($params['agent']) ? (string)$params['agent'] : null;
        $this->auth_key     = isset($params['auth_key']) ? (string)$params['auth_key'] : null;
        $this->sender_email = isset($params['sender_email']) ? (string)$params['sender_email'] : null;

        // crunch for "PHP Notice:  Array to string conversion". Error appears only on Gravity forms
        // @todo fix gat_fields_any
        if ( isset($params['sender_nickname']) && is_array($params['sender_nickname']) ) {
            $params['sender_nickname'] = current($params['sender_nickname']);
        }

        $this->sender_nickname = ! empty($params['sender_nickname']) ? (string)$params['sender_nickname'] : null;
        $this->phone           = ! empty($params['phone']) ? (string)$params['phone'] : null;
        $this->js_on           = isset($params['js_on']) ? (int)$params['js_on'] : null;
        $this->submit_time     = isset($params['submit_time']) ? (int)$params['submit_time'] : null;
        $this->post_info       = isset($params['post_info']) ? (string)json_encode($params['post_info']) : null;
        $this->sender_info     = isset($params['sender_info']) ? (string)json_encode($params['sender_info']) : null;
        $this->honeypot_field  = isset($params['honeypot_field']) ? (int)$params['honeypot_field'] : null;
        $this->exception_action  = isset($params['exception_action']) ? (int)$params['exception_action'] : null;

        $this->message = ! empty($params['message'])
            ? (! is_scalar($params['message'])
                ? serialize($params['message'])
                : $params['message'])
            : null;
        $this->example = ! empty($params['example'])
            ? (! is_scalar($params['example'])
                ? serialize($params['example'])
                : $params['example'])
            : null;

        // Feedback
        $this->feedback = ! empty($params['feedback']) ? $params['feedback'] : null;
    }
}
