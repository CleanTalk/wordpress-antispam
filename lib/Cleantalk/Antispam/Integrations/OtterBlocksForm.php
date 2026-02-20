<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Cookie;

class OtterBlocksForm extends IntegrationBase
{
    //private $form_data_request;

    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        // Try to decode the form_data JSON
        $form_data_json = isset($argument['form_data']) ? $argument['form_data'] : '';
        $form_data_obj = json_decode($form_data_json);
        file_put_contents(__DIR__."/umitest", print_r([__FILE__.' '.__LINE__, $_POST], true).PHP_EOL, FILE_APPEND | LOCK_EX);

        $result = [];
        if (
            is_object($form_data_obj) &&
            isset($form_data_obj->payload) &&
            isset($form_data_obj->payload->formInputsData) &&
            is_array($form_data_obj->payload->formInputsData)
        ) {
            foreach ($form_data_obj->payload->formInputsData as $input) {
                if (!isset($input->label) || !isset($input->value)) {
                    continue;
                }
                switch (mb_strtolower($input->label)) {
                    case 'name':
                        $result['name'] = $input->value;
                        break;
                    case 'email':
                        $result['email'] = $input->value;
                        break;
                    case 'message':
                        $result['message'] = $input->value;
                        break;
                }
            }
        }

        // Fallback: if not found, return original argument
        file_put_contents(__DIR__."/umitest", print_r([__FILE__.' '.__LINE__, ct_gfa_dto(
            apply_filters('apbct__filter_post', $result), 
            isset($result['email']) ? $result['email'] : '',
            isset($result['name']) ? $result['name'] : '',
        )->getArray()], true).PHP_EOL, FILE_APPEND | LOCK_EX);

        if (count($result) > 0) {
            return ct_gfa_dto(
                apply_filters('apbct__filter_post', $result), 
                isset($result['email']) ? $result['email'] : '',
                isset($result['name']) ? $result['name'] : '',
            )->getArray();
        }
        return $argument;
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    public function doBlock($message)
    {
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked'     => true,
                        'comment'     => $message,
                        'stop_script' => 1,
                        'integration' => 'OtterBlocksForm'
                    )
                )
            )
        );
        /* wp_send_json_error(
            array(
                'message' => $message
            )
        );
        die(); */
    }
}
