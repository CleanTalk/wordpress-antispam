<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\Common\TT;

class OtterBlocksForm extends IntegrationBase
{
    /**
     * Form request object of the plugin, set on the anti-spam filter
     * @var object|null
     */
    private $form_request = null;

    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        // The plugin REST API submission
        if (current_filter() === 'otter_form_anti_spam_validation') {
            return $this->getDataFromFormRequest($argument);
        }

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
     * Get the fields from the form request object the plugin validates.
     *
     * @param object $form_request
     *
     * @return array|null
     */
    private function getDataFromFormRequest($form_request)
    {
        if (
            !is_object($form_request) ||
            !method_exists($form_request, 'get_fields') ||
            (method_exists($form_request, 'has_error') && $form_request->has_error())
        ) {
            return null;
        }

        $this->form_request = $form_request;

        $fields = array();
        $email = '';
        $nickname = '';
        foreach (TT::toArray($form_request->get_fields()) as $index => $input) {
            if (!is_array($input) || !isset($input['value'])) {
                continue;
            }
            $label = TT::getArrayValueAsString($input, 'label');
            $fields[$label . '_' . $index] = $input['value'];
            // The plugin takes the first email type field as the sender email
            if ($email === '' && TT::getArrayValueAsString($input, 'type') === 'email') {
                $email = TT::toString($input['value']);
            }
            if ($nickname === '' && mb_strtolower($label) === 'name') {
                $nickname = TT::toString($input['value']);
            }
        }

        return ct_gfa_dto(apply_filters('apbct__filter_post', $fields), $email, $nickname)->getArray();
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    public function doBlock($message)
    {
        if ($this->form_request && method_exists($this->form_request, 'set_error')) {
            // The plugin bot detection error
            $this->form_request->set_error('110', $message);
            return $this->form_request;
        }

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
