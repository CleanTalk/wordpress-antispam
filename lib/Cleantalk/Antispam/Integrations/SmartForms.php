<?php

namespace Cleantalk\Antispam\Integrations;

class SmartForms extends IntegrationBase
{

    public function getDataForChecking($argument)
    {
        $data = \Cleantalk\Variables\Post::get('formString');
        if( $data ) {

            $data = json_decode($data,true);

            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $data);

            return ct_gfa($input_array);
        }
    }

    public function doBlock($message)
    {
        echo json_encode(array(
            'message'=> $message,
            'success'=>'n',
        ));
        die();
    }
}