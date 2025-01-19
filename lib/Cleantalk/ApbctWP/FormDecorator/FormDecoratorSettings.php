<?php

namespace Cleantalk\ApbctWP\FormDecorator;

class FormDecoratorSettings
{
    /**
     * @param array $params
     * @param string $disabled
     * @param string $popup
     *
     * @return array
     */
    public static function filterSelectorParams($params, $disabled, $popup)
    {
        $params['nest_label_and_description_after_select'] = true;

        $data = array(
            'name' => isset($params['name']) ? $params['name'] : '',
            'type' => isset($params['type']) ? $params['type'] : '',
            'multiple' => isset($params['multiple']) && $params['multiple'],
            'size' => isset($params['multiple'], $params['options']) && $params['multiple'] ? 'size="' . count($params['options']) . '"' : '',
            'multiple_attr' => isset($params['multiple']) && $params['multiple'] ? ' multiple="multiple"' : '',
            'required_attr' => isset($params['required']) && $params['required'] ? ' required="required"' : '',
            'disabled' => $disabled,
        );

        $select_layout = '<select id="apbct_setting_{{name}}" class="apbct_setting_{{type}} apbct_setting---{{name}}"
                name="cleantalk_settings[{{name}}]{{multiple}}" {{size}} {{multiple_attr}} {{disabled}} {{required_attr}}
                style="margin-right: 5px;">';

        $select = $select_layout;
        foreach ($data as $key => $value) {
            $select = str_replace('{{' . $key . '}}', (string) $value, $select);
        }
        $params['custom_select_element'] = $select;

        if (isset($params['title'])) {
            $params['custom_select_title'] = '<label for="apbct_setting_' . $data['name'] . '" class="apbct_setting-field_title--' . $data['type'] . '">'
                . $params['title'] . $popup
                . '</label>';
        }

        if (isset($params['description'])) {
            $params['custom_select_description'] = '<div class="apbct_settings-field_description">'
                . $params['description']
                . '</div>';
        }

        return $params;
    }
}
