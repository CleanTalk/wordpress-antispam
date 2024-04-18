<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class WordpressFileUpload extends IntegrationBase
{
    private $return_argument;

    public function getDataForChecking($argument)
    {
        $this->return_argument = $argument;
        
        // 1) Get data from $_REQUEST['userdata'] like in CalculatedFieldsForm.php
        // 2) Get event_token from $_REQUEST but need to send it from frontend somehow. See here as example - https://github.com/CleanTalk/wordpress-antispam/blob/dev/js/src/apbct-public--2--public.js#L767
        // 3) Return prepared request data
    }

    public function doBlock($message)
    {
        return ["error_message" => $message];
    }
    
    public function allow()
    {
        return $this->return_argument;
    }
}
