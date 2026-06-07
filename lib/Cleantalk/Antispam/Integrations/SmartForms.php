<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class SmartForms extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if (Post::getString('action') !== 'rednao_smart_forms_save_form_values') {
            return null;
        }

        $input_array = apply_filters('apbct__filter_post', $_POST);
        $email = '';

        if (isset($input_array['formString']) && is_string($input_array['formString'])) {
            $form_data = json_decode(stripslashes($input_array['formString']), true);
            if (is_array($form_data)) {
                foreach ($form_data as $field_value) {
                    $value = $this->extractFieldValue($field_value);
                    if ($value === null) {
                        continue;
                    }

                    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $email = $value;
                        break;
                    }
                }
            }
        }

        return ct_gfa_dto($input_array, $email)->getArray();
    }

    /**
     * @param mixed $field_value
     *
     * @return string|null
     */
    private function extractFieldValue($field_value)
    {
        if (is_scalar($field_value)) {
            return (string) $field_value;
        }

        if (is_array($field_value) && isset($field_value['value']) && is_scalar($field_value['value'])) {
            return (string) $field_value['value'];
        }

        return null;
    }

    /**
     * @param $message
     *
     * @return void
     */
    public function doBlock($message)
    {
        if (Post::getString('action') === 'rednao_smart_forms_save_form_values') {
            $result = array(
                'message'        => $message,
                'refreshCaptcha' => 'n',
                'success'        => 'n',
            );
            print json_encode($result);
            die();
        }
    }
}
