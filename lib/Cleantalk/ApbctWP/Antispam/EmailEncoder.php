<?php

namespace Cleantalk\ApbctWP\Antispam;

use Cleantalk\ApbctWP\API;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\Variables\Post;

class EmailEncoder extends \Cleantalk\Antispam\EmailEncoder
{
    /**
     * @var array|bool API response
     */
    private $api_response;
    
    /**
     * @var array|bool Comment from API response
     */
    private $comment;
    
    /**
     * Check if the decoding is allowed
     *
     * Set properties
     *      this->api_response
     *      this->comment
     *
     * @return bool
     */
    protected function checkRequest()
    {
        global $apbct;
        
        $this->api_response = API::methodCheckBot(
            $apbct->api_key,
            null,
            Post::get( 'event_javascript_data'),
            hash('sha256', Post::get( 'browser_signature_params')),
            Helper::ipGet(),
            'CONTACT_DECODING',
            $this->decoded_email
        );
        
        // Allow to see to the decoded contact if error occurred
        // Send error as comment in this case
        if( ! empty($this->api_response['error']) ){
            $this->comment = $this->api_response['error'];
            
            return true;
        }
        
        // Deny
        if( $this->api_response['allow'] === 0 ){
            $this->comment = $this->api_response['comment'];
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Compile the response to pass it further
     *
     * @param $decoded_email
     * @param $is_allowed
     *
     * @return array
     */
    protected function compileResponse( $decoded_email, $is_allowed )
    {
        return [
            'is_allowed'    => $is_allowed,
            'show_comment'  => $is_allowed,
            'comment'       => $this->comment,
            'decoded_email' => strip_tags( $decoded_email, '<a>' ),
        ];
    }
}
