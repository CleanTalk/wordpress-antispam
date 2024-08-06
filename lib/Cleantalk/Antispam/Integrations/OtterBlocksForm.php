<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Cookie;
use ThemeIsle\GutenbergBlocks\Integration\Form_Data_Request;
use ThemeIsle\GutenbergBlocks\Integration\Form_Data_Response;

class OtterBlocksForm extends IntegrationBase
{
    /**
     * @var Form_Data_Request
     */
    private $form_data_request;

    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        $this->form_data_request = $argument;

        Cookie::$force_alt_cookies_global = true;
        $fields = $this->form_data_request->get_fields();
        if (
            isset($fields) &&
            is_array($fields)
        ) {
            $form_data = [];
            foreach ( $fields as $input_info ) {
                if ( isset($input_info['id'], $input_info['value']) ) {
                    $form_data[] = [
                        $input_info['id'] => $input_info['value']
                    ];
                }
            }
            if ( count($form_data) ) {
                $gfa_result = ct_gfa($form_data);
                if ( $event_token = Cookie::get('ct_bot_detector_event_token') ) {
                    $gfa_result['event_token'] = $event_token;
                }
            }
            return $gfa_result;
        }
        return $argument;
    }
    
    /**
     * @inheritDoc
     * @throws \Exception
     */
    public function doBlock($message)
    {
        $this->form_data_request->set_error('110', $message);
    }
}
