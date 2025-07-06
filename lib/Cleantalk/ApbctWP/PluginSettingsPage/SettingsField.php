<?php

namespace Cleantalk\ApbctWP\PluginSettingsPage;

use Cleantalk\ApbctWP\Antispam\EmailEncoder;
use Cleantalk\Common\TT;

class SettingsField
{
    /**
     * @var string
     */
    private $field_layout = '';
    /**
     * @var array
     */
    private $params;
    /**
     * @var string
     */
    private $disabled_string;
    /**
     * @var string[]|string
     */
    private $value;
    /**
     * @var string
     */
    private $children_string;
    /**
     * @var string
     */
    private $hide_string;
    /**
     * @var string
     */
    private $description_popup = '';

    /**
     * @param array $params
     */
    public function __construct($params)
    {
        $this->disabled_string = '';
        $this->value = '';
        $this->children_string = '';
        $this->hide_string = '';
        $this->description_popup = '';

        $this->params = $params;
        $this->prepareElementData();
        $this->prepareElementHTML();
    }

    private function prepareElementData()
    {
        global $apbct;

        // value
        if (isset($this->params['type']) && $this->params['type'] === 'custom_html') {
            // ignore value on custom html
            $this->value = '';
        } else {
            if (isset($this->params['network'], $this->params['name']) && $this->params['network']) {
                $this->value = $apbct->network_settings[$this->params['name']];
            } elseif (isset($this->params['name'])) {
                $this->value = $apbct->settings[$this->params['name']];
            }
        }

        // parent
        $value_parent = false;
        if (isset($this->params['parent']) && $this->params['parent']) {
            if (isset($this->params['network']) && $this->params['network']) {
                $value_parent = $apbct->network_settings[$this->params['parent']];
            } else {
                $value_parent = $apbct->settings[$this->params['parent']];
            }
        }

        // disabled
        $this->disabled_string = '';
        if (isset($this->params['parent']) && $this->params['parent'] && ! $value_parent) {
            $this->disabled_string = ' disabled="disabled"';
        }

        // disabled by reverse trigger
        if (isset($this->params['reverse_trigger']) && $this->params['reverse_trigger'] && ! $value_parent) {
            $this->disabled_string = '';
        }

        // disabled by reverse trigger and parent
        if (isset($this->params['parent'], $this->params['reverse_trigger']) &&
            $this->params['parent'] && $this->params['reverse_trigger'] && ! $value_parent
        ) {
            $this->disabled_string = '';
        }

        // disabled by direct (from params)
        if (isset($this->params['disabled']) && $this->params['disabled']) {
            $this->disabled_string = ' disabled="disabled"';
        }

        // disabled by super admin on sub-sites
        if (! is_main_site() &&
            $apbct->network_settings &&
            ( ! $apbct->network_settings['multisite__allow_custom_settings'] || $apbct->network_settings['multisite__work_mode'] == 2 )
        ) {
            $this->disabled_string = ' disabled="disabled"';
        }

        // children string
        $this->children_string = '';
        if (isset($this->params['childrens']) && $this->params['childrens']) {
            $this->children_string = 'apbct_setting---' . implode(",apbct_setting---", $this->params['childrens']);
        }

        // hide string
        $this->hide_string = '';
        if (isset($this->params['hide']) && $this->params['hide']) {
            $this->hide_string = implode(",", $this->params['hide']);
        }

        // description popup
        if (isset($this->params['long_description'], $this->params['name'])) {
            $this->description_popup = '<i setting="' . $this->params['name'] . '" class="apbct_settings-long_description---show apbct-icon-help-circled"></i>';
        }
    }

    /**
     * @return void
     */
    private function prepareElementHTML()
    {
        $def_class = isset($this->params['def_class']) ? $this->params['def_class'] : '';
        $class = isset($this->params['class']) ? $this->params['class'] : '';
        $this->field_layout .= '<div class="' . $def_class . ' ' . $class . '">';

        if (isset($this->params['type'])) {
            switch ( $this->params['type'] ) {
                // Checkbox type
                case 'checkbox':
                    $this->field_layout .= $this->getInputCheckBox();
                    break;

                // Radio type
                case 'radio':
                    $this->field_layout .= $this->getInputRadio();
                    break;

                // Dropdown list type
                case 'select':
                    $this->field_layout .= $this->getInputSelect();
                    break;

                // Text type
                case 'text':
                    $this->field_layout .= $this->getInputText();
                    break;

                // Text type
                case 'affiliate_shortcode':
                    $this->field_layout .= $this->getAffiliateShortCode();
                    break;

                // Textarea type
                case 'textarea':
                    $this->field_layout .= $this->getInputTextarea();
                    break;
                // Color type
                case 'color':
                    $this->field_layout .= $this->getInputColor();
                    break;
                case 'custom_html':
                    $this->field_layout .= $this->getCustomHTML();
                    break;
            }
        }

        $this->field_layout .= '</div>';
    }

    /**
     * @return void
     */
    public function draw()
    {
        echo $this->field_layout;
    }

    /**
     * @return string
     */
    private function getInputCheckBox()
    {
        if (isset($this->params['description'])) {
            $href = '<a href="https://cleantalk.org/my/partners" target="_blank">' . __('CleanTalk Affiliate Program are here', 'cleantalk-spam-protect') . '</a>';
            $this->params['description'] = str_replace('{CT_AFFILIATE_TERMS}', $href, $this->params['description']);
        }

        $data = [
            'name' => isset($this->params['name']) ? $this->params['name'] : '',
            'type' => isset($this->params['type']) ? $this->params['type'] : '',
            'value' => $this->value,
            'disabled' => $this->disabled_string,
            'required' => isset($this->params['required']) && $this->params['required'] ? 'required="required"' : '',
            'childrens' => isset($this->params['childrens']) && $this->params['childrens'] ? ' onchange="apbctSettingsDependencies(\'' . $this->children_string . '\')"' : '',
            'childrens_string' => isset($this->params['childrens']) && $this->params['childrens'] ? ' apbct_children="' . $this->children_string . '"' : '',
            'title' => isset($this->params['title']) ? $this->params['title'] : '',
            'popup_description' => isset($this->description_popup) ? $this->description_popup : '',
            'description' => isset($this->params['description']) ? $this->params['description'] : '',
            'checked' => $this->value == '1' ? ' checked' : '',
            'hide' => isset($this->params['hide']) && $this->params['hide'] ? ' apbctShowHideElem(\'' . $this->hide_string . '\');' : '',
        ];

        $layout = '<input type="checkbox" name="cleantalk_settings[{{name}}]" id="apbct_setting_{{name}}" value="1"
                class="apbct_setting_{{type}} apbct_setting---{{name}}" {{checked}} {{disabled}} {{required}}
                {{childrens_string}} {{childrens}} {{hide}} />
                <label for="apbct_setting_{{name}}" class="apbct_setting-field_title--{{type}}">{{title}}</label>
                {{popup_description}}
                <div class="apbct_settings-field_description">{{description}}</div>';

        $out = $layout;
        foreach ($data as $key => $value) {
            $out = str_replace('{{' . $key . '}}', $value, $out);
        }

        return $out;
    }

    /**
     * @return string
     */
    private function getInputRadio()
    {
        $data = [
            'title' => isset($this->params['title']) ? $this->params['title'] : '',
            'type' => isset($this->params['type']) ? $this->params['type'] : '',
            'popup_description' => isset($this->description_popup) ? $this->description_popup : '',
        ];

        $out = isset($this->params['title'])
            ? '<h4 class="apbct_settings-field_title apbct_settings-field_title--{{type}}">{{title}} {{popup_description}}</h4>'
            : '';

        $out .= '<div class="apbct_settings-field_content apbct_settings-field_content--{{type}}">';

        $out .= '<div class="apbct_switchers" style="direction: ltr">';

        if (isset($this->params['options'])) {
            foreach ( $this->params['options'] as $option ) {
                $option_data = [
                    'type' => isset($this->params['type']) ? $this->params['type'] : '',
                    'name' => isset($this->params['name']) ? $this->params['name'] : '',
                    'name_id' => isset($this->params['name'], $option['label']) ? $this->params['name'] . '__' . $option['label'] : '',
                    'value' => $option['val'],
                    'disabled' => $this->disabled_string,
                    'childrens' => isset($this->params['childrens'], $option['childrens_enable']) ? ' onchange="apbctSettingsDependencies(\'' . $this->children_string . '\', ' . $option['childrens_enable'] . ')"' : '',
                    'required' => isset($this->params['required']) && $this->params['required'] ? 'required="required"' : '',
                    'checked' => $this->value == $option['val'] ? ' checked' : '',
                    'label' => isset($option['label']) ? $option['label'] : '',
                ];

                $option_layout = '<input type="radio" class="apbct_setting_{{type}} apbct_setting---{{name}}" id="apbct_setting_{{name_id}}"
                        name="cleantalk_settings[{{name}}]" value="{{value}}" {{disabled}} {{childrens}} {{required}} {{checked}} />
                        <label for="apbct_setting_{{name_id}}"> {{label}}</label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';

                $option = $option_layout;
                foreach ($option_data as $key => $value) {
                    $option = str_replace('{{' . $key . '}}', $value, $option);
                }

                $out .= $option;
            }
        }

        $out .= '</div>';

        $out .= isset($this->params['description'])
            ? '<div class="apbct_settings-field_description">' . $this->params['description'] . '</div>'
            : '';

        $out .= '</div>';

        $output = $out;
        foreach ($data as $key => $value) {
            $output = str_replace('{{' . $key . '}}', $value, $output);
        }

        return $output;
    }

    /**
     * @return string
     */
    private function getInputSelect()
    {
        $data = [
            'name' => isset($this->params['name']) ? $this->params['name'] : '',
            'type' => isset($this->params['type']) ? $this->params['type'] : '',
            'title' => isset($this->params['title']) ? $this->params['title'] : '',
            'popup_description' => isset($this->description_popup) ? $this->description_popup : '',
            'description' => isset($this->params['description']) ? $this->params['description'] : '',
            'multiple' => isset($this->params['multiple']) && $this->params['multiple'] ? '[]' : '',
            'multiple_bool' => isset($this->params['multiple']) ? 'multiple="multiple"' : '',
            'size' => isset($this->params['multiple'], $this->params['options']) && $this->params['multiple'] ? ' size="' . count($this->params['options']) . '"' : '',
            'childrens' => isset($this->params['childrens']) ? ' onchange="apbctSettingsDependencies(\'' . $this->children_string . '\', jQuery(this).find(\'option:selected\').data(\'children_enable\'))"' : '',
            'disabled' => $this->disabled_string,
            'required' => isset($this->params['required']) && $this->params['required'] ? 'required="required"' : '',
        ];

        // title
        if (isset($this->params['custom_select_title'])) {
            $title = $this->params['custom_select_title'];
        } else {
            $title = '<h4 class="apbct_settings-field_title apbct_settings-field_title--{{type}}">{{title}} {{popup_description}}</h4>';
        }

        // description
        if (isset($this->params['custom_select_description'])) {
            $description = $this->params['custom_select_description'];
        } else {
            $description = '<div class="apbct_settings-field_description">{{description}}</div>';
        }

        // select
        if (isset($this->params['custom_select_element'])) {
            $select = $this->params['custom_select_element'];
        } else {
            $select = '<select id="apbct_setting_{{name}}" class="apbct_setting_{{type}} apbct_setting---{{name}}"
                      name="cleantalk_settings[{{name}}]{{multiple}}"
                      {{size}} {{multiple_bool}} {{disabled}} {{required}} {{childrens}} >';
        }

        // options
        if (isset($this->params['options'])) {
            foreach ( $this->params['options'] as $option ) {
                $option_data = [
                    'value' => $option['val'],
                    'label' => $option['label'],
                    'children_enable' => isset($option['children_enable']) ? ' data-children_enable=' . $option['children_enable'] : '',
                    'selected' => '',
                ];

                if (isset($this->params['multiple']) &&
                    ! empty($this->value) &&
                    is_array($this->value) &&
                    in_array($option['val'], $this->value)
                ) {
                    $option_data['selected'] = 'selected="selected"';
                } elseif ($this->value == $option['val']) {
                    $option_data['selected'] = 'selected="selected"';
                }

                $option_layout = '<option value="{{value}}" {{children_enable}} {{selected}}> {{label}} </option>';
                foreach ($option_data as $key => $value) {
                    $option_layout = str_replace('{{' . $key . '}}', $value, $option_layout);
                }
                $select .= $option_layout;
            }
        }

        $select .= '</select>';

        if (isset($this->params['nest_label_and_description_after_select'])) {
            $output = $select . $title . $description;
        } else {
            $output = $title . $description . $select;
        }

        $out = $output;
        foreach ($data as $key => $value) {
            $out = str_replace('{{' . $key . '}}', $value, $out);
        }

        return $out;
    }

    /**
     * @return string
     */
    private function getInputText()
    {
        $data = [
            'name' => isset($this->params['name']) ? $this->params['name'] : '',
            'type' => isset($this->params['type']) ? $this->params['type'] : '',
            'value' => $this->value,
            'placeholder' => isset($this->params['placeholder']) ? 'placeholder="' . $this->params['placeholder'] . '"' : '',
            'disabled' => $this->disabled_string,
            'required' => isset($this->params['required']) && $this->params['required'] ? 'required="required"' : '',
            'childrens' => isset($this->params['childrens']) ? 'onchange="apbctSettingsDependencies(\'' . $this->children_string . '\')"' : '',
            'title' => isset($this->params['title']) ? $this->params['title'] : '',
            'popup_description' => isset($this->description_popup) ? $this->description_popup : '',
            'description' => isset($this->params['description']) ? $this->params['description'] : '',
        ];

        $layout = '<input type="text" id="apbct_setting_{{name}}" name="cleantalk_settings[{{name}}]"
               class="apbct_setting_{{type}} apbct_setting---{{name}}" value="{{value}}"
               {{placeholder}} {{disabled}} {{required}} {{childrens}} />
               &nbsp;
               <label for="apbct_setting_{{name}}" class="apbct_setting-field_title--{{type}}">
               {{title}} {{popup_description}}
               </label>
               <div class="apbct_settings-field_description">{{description}}</div>';

        $out = $layout;
        foreach ($data as $key => $value) {
            $out = str_replace('{{' . $key . '}}', $value, $out);
        }

        return $out;
    }

    /**
     * @return string
     */
    private function getAffiliateShortCode()
    {
        $data = [
            'name' => isset($this->params['name']) ? $this->params['name'] : '',
            'type' => isset($this->params['type']) ? $this->params['type'] : '',
            'value' => '[cleantalk_affiliate_link]',
            'title' => isset($this->params['title']) ? $this->params['title'] : '',
            'popup_description' => isset($this->description_popup) ? $this->description_popup : '',
            'description' => isset($this->params['description']) ? $this->params['description'] : '',
            'disabled' => $this->disabled_string,
            'required' => isset($this->params['required']) && $this->params['required'] ? 'required="required"' : '',
            'childrens' => isset($this->params['childrens']) ? 'onchange="apbctSettingsDependencies(\'' . $this->children_string . '\')"' : '',
        ];

        $layout = '<input type="text" id="apbct_setting_{{name}}" name="cleantalk_settings[{{name}}]"
            class="apbct_setting_{{type}} apbct_setting---{{name}}"
            value="{{value}}"
            readonly
            {{disabled}} {{required}} {{childrens}} />
            &nbsp;
            <label for="apbct_setting_{{name}}" class="apbct_setting-field_title--{{type}}">
            {{title}} {{popup_description}}
            </label>
            <div class="apbct_settings-field_description">{{description}}</div>';

        $out = $layout;
        foreach ($data as $key => $value) {
            $out = str_replace('{{' . $key . '}}', $value, $out);
        }

        return $out;
    }

    /**
     * @return string
     */
    private function getInputTextarea()
    {
        $title_layout = '<h4 class="apbct_settings-field_title apbct_settings-field_title--{{type}}">{{title}} {{popup_description}}</h4>';

        $data = [
            'title' => isset($this->params['title']) ? $this->params['title'] : '',
            'type' => isset($this->params['type']) ? $this->params['type'] : '',
            'popup_description' => $this->description_popup,
            'description' => isset($this->params['description']) ? $this->params['description'] : '',
            'name' => isset($this->params['name']) ? $this->params['name'] : '',
            'disabled' => $this->disabled_string,
            'required' => isset($this->params['required']) && $this->params['required'] ? 'required="required"' : '',
            'childrens' => isset($this->params['childrens']) ? 'onchange="apbctSettingsDependencies(\'' . $this->children_string . '\')" ' : '',
            'value' => empty($this->value) ? TT::getArrayValueAsString($this->params, 'value') : $this->value,
        ];

        $layout = '';
        if (isset($this->params['title'])) {
            $layout .= $title_layout;
        }

        $layout .= '<div class="apbct_settings-field_description">{{description}}</div>
            <textarea id="apbct_setting_{{name}}" name="cleantalk_settings[{{name}}]"
            class="apbct_setting_{{type}} apbct_setting---{{name}}"
            {{disabled}} {{required}} {{childrens}} >{{value}}</textarea> &nbsp;';

        foreach ($data as $key => $value) {
            $layout = str_replace('{{' . $key . '}}', $value, $layout);
        }

        return $layout;
    }

    /**
     * @return string
     */
    private function getInputColor()
    {
        $data = [
            'name' => isset($this->params['name']) ? $this->params['name'] : '',
            'type' => isset($this->params['type']) ? $this->params['type'] : '',
            'value' => $this->value,
            'disabled' => $this->disabled_string,
            'required' => isset($this->params['required']) && $this->params['required'] ? 'required="required"' : '',
            'childrens' => isset($this->params['childrens']) ? 'onchange="apbctSettingsDependencies(\'' . $this->children_string . '\')" ' : '',
            'title' => isset($this->params['title']) ? $this->params['title'] : '',
            'popup_description' => isset($this->description_popup) ? $this->description_popup : '',
            'description' => isset($this->params['description']) ? $this->params['description'] : '',
        ];

        $layout = '<input type="color" id="apbct_setting_{{name}}" name="cleantalk_settings[{{name}}]"
            class="apbct_setting_{{type}} apbct_setting---{{name}}" value="{{value}}" {{disabled}} {{required}} {{childrens}}
            /> &nbsp;
            <label for="apbct_setting_{{name}}" class="apbct_setting-field_title--{{type}}">
            {{title}} {{popup_description}}
            </label>
            <div class="apbct_settings-field_description">{{description}}</div>';

        $out = $layout;
        foreach ($data as $key => $value) {
            $value = is_array($value) ? implode(', ', $value) : (string) $value;
            $out = str_replace('{{' . $key . '}}', $value, $out);
        }

        return $out;
    }

    /**
     * Returns string of custom HTML for element.
     * @return string
     */
    private function getCustomHTML()
    {
        global $apbct;
        $data = [
            'name' => isset($this->params['name']) ? $this->params['name'] : '',
        ];

        switch ($data['name']) {
            case 'data__email_decoder__status':
                $data['phones_on'] = $apbct->settings['data__email_decoder_encode_phone_numbers'];
                $data['emails_on'] = $apbct->settings['data__email_decoder_encode_email_addresses'];
                $data['obfuscation_mode'] = $apbct->settings['data__email_decoder_obfuscation_mode'];
                $data['encoder_enabled_global'] = $apbct->settings['data__email_decoder'];
                $current_user = wp_get_current_user();
                $current_user_email = $current_user->exists() ? $current_user->user_email : 'example@example.com';
                $emailEncoder = EmailEncoder::getInstance();
                $data['current_user_email'] = $emailEncoder->modifyContent($current_user_email);
                return EmailEncoder::getEncoderStatusForSettingsHat($data);
        }
        return '';
    }
}
