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
                // Take the value of the email field of the form
                foreach ($this->getEmailFieldsIds(Post::getInt('form_id')) as $field_id) {
                    $value = isset($form_data[$field_id]) ? $this->extractFieldValue($form_data[$field_id]) : null;
                    if ($value !== null && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $email = $value;
                        break;
                    }
                }
            }
        }

        return ct_gfa_dto($input_array, $email)->getArray();
    }

    /**
     * Get IDs of the email fields of the form
     *
     * @param int $form_id
     *
     * @return array
     */
    private function getEmailFieldsIds($form_id)
    {
        global $wpdb;

        $ids = array();
        $table = defined('SMART_FORMS_TABLE_NAME') ? SMART_FORMS_TABLE_NAME : $wpdb->prefix . 'rednao_smart_forms_table_name';
        $element_options = $wpdb->get_var(
            $wpdb->prepare('SELECT element_options FROM ' . $table . ' WHERE form_id = %d', $form_id)
        );
        $element_options = is_string($element_options) ? json_decode($element_options, true) : null;

        if (is_array($element_options)) {
            foreach ($element_options as $element) {
                if (isset($element['ClassName'], $element['Id']) && $element['ClassName'] === 'rednaoemail') {
                    $ids[] = (string)$element['Id'];
                }
            }
        }

        return $ids;
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
