<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Cookie;

class OtterBlocksForm extends IntegrationBase
{
    private $form_data_request;

    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        $this->form_data_request = $argument;
        Cookie::$force_alt_cookies_global = true;

        /**
         * @psalm-suppress UndefinedClass
         */
        if (
            class_exists('\ThemeIsle\GutenbergBlocks\Integration\Form_Data_Request') &&
            $argument instanceof \ThemeIsle\GutenbergBlocks\Integration\Form_Data_Request &&
            method_exists($this->form_data_request, 'get_fields')
        ) {
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
                    $event_token = Cookie::get('ct_bot_detector_event_token');
                    if ( $event_token ) {
                        $gfa_result['event_token'] = $event_token;
                    }
                    return $gfa_result;
                }
            }
        }
        return $argument;
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    public function doBlock($message)
    {
        if ( method_exists($this->form_data_request, 'set_error') ) {
            $this->form_data_request->set_error('110', $message);
        }
    }
}
