<?php

namespace Cleantalk\Antispam\Integrations;

class OtterBlocksForm extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        $form_data_json = '';
        if (isset($argument['form_data'])) {
             $form_data_json = $argument['form_data'];
        } elseif (isset($_POST['form_data']) && is_string($_POST['form_data'])) {
            $form_data_json = stripslashes($_POST['form_data']);
        }

        $form_data_obj = json_decode($form_data_json);
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
                        if (is_array($input->value)) {
                            if (isset($input->value['message'])) {
                                $result['message'] = $input->value['message'];
                            } else {
                                $result['message'] = $input->value;
                            }
                        } else {
                            $result['message'] = $input->value;
                        }
                        break;
                }
            }
        }

        if (count($result) > 0) {
            $dto = ct_gfa_dto(
                apply_filters('apbct__filter_post', $result),
                isset($result['email']) ? $result['email'] : '',
                isset($result['name']) ? $result['name'] : ''
            )->getArray();
            if (isset($dto['message']) && is_array($dto['message']) && isset($dto['message']['message'])) {
                $dto['message'] = $dto['message']['message'];
            }
            return $dto;
        }
        return $argument;
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    public function doBlock($message)
    {
        echo json_encode(
            array(
                'apbct' => array(
                    'blocked'     => true,
                    'comment'     => $message,
                )
            )
        );
        die();
    }
}
