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

        $select = '<select'
                  . ' id="apbct_setting_' . $params['name'] . '"'
                  . " class='apbct_setting_{$params['type']} apbct_setting---{$params['name']}'"
                  . ' name="cleantalk_settings[' . $params['name'] . ']' . ($params['multiple'] ? '[]"' : '"')
                  . ($params['multiple'] ? ' size="' . count($params['options']) . '""' : '')
                  . ($params['multiple'] ? ' multiple="multiple"' : '')
                  . $disabled
                  . ($params['required'] ? ' required="required"' : '')
                  . ' style="margin-right: 5px;">';
        $params['custom_select_element'] = $select;

        if (isset($params['title'])) {
            $params['custom_select_title'] = '<label for="apbct_setting_' . $params['name'] . '" class="apbct_setting-field_title--' . $params['type'] . '">'
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
