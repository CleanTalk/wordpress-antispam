<?php

namespace Cleantalk\ApbctWP\PluginSettingsPage;

use Cleantalk\ApbctWP\FormDecorator\FormDecoratorSettings;

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
        $this->params = $params;
        $this->prepareElementData();
        $this->prepareElementHTML();
    }

    private function prepareElementData()
    {
        global $apbct;

        // Prepare values
        $this->value        = $this->params['network']
            ? $apbct->network_settings[$this->params['name']]
            : $apbct->settings[$this->params['name']];
        $value_parent = $this->params['parent']
            ? ($this->params['network'] ? $apbct->network_settings[$this->params['parent']] : $apbct->settings[$this->params['parent']])
            : false;

        // Is element is disabled
        $this->disabled_string   = $this->params['parent'] && ! $value_parent
            ? ' disabled="disabled"'
            : '';        // Strait
        $this->disabled_string   = $this->params['parent'] && $this->params['reverse_trigger'] && ! $value_parent
            ? ''
            : $this->disabled_string; // Reverse logic
        $this->disabled_string   = $this->params['disabled']
            ? ' disabled="disabled"'
            : $this->disabled_string; // Direct disable from params
        $this->disabled_string   =
            ! is_main_site() &&
            $apbct->network_settings &&
            ( ! $apbct->network_settings['multisite__allow_custom_settings'] || $apbct->network_settings['multisite__work_mode'] == 2 )
                ? ' disabled="disabled"'
                : $this->disabled_string; // Disabled by super admin on sub-sites

        // Children string
        $this->children_string         = $this->params['childrens']
            ? 'apbct_setting---' . implode(",apbct_setting---", $this->params['childrens'])
            : '';

        // Hide string
        $this->hide_string              = $this->params['hide']
            ? implode(",", $this->params['hide'])
            : '';
        if ( isset($this->params['long_description']) ) {
            $this->description_popup = '<i setting="' . $this->params['name'] . '" class="apbct_settings-long_description---show apbct-icon-help-circled"></i>';
        }
    }

    /**
     * @return void
     */
    private function prepareElementHTML()
    {
        $this->field_layout .= '<div class="' . $this->params['def_class'] . (isset($this->params['class']) ? ' ' . $this->params['class'] : '') . '">';

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
        $out = '';
        //ESC NEED
        $out .= '<input
					type="checkbox"
					name="cleantalk_settings[' . $this->params['name'] . ']"
					id="apbct_setting_' . $this->params['name'] . '"
					value="1" '
                . " class='apbct_setting_{$this->params['type']} apbct_setting---{$this->params['name']}'"
                . ($this->value == '1' ? ' checked' : '')
                . $this->disabled_string
                . ($this->params['required'] ? ' required="required"' : '')
                . ($this->params['childrens'] ? ' apbct_children="' . $this->children_string . '"' : '')
                . ' onchange="'
                . ($this->params['childrens'] ? ' apbctSettingsDependencies(\'' . $this->children_string . '\');' : '')
                . ($this->params['hide'] ? ' apbctShowHideElem(\'' . $this->hide_string . '\');' : '')
                . '"'
                . ' />'
                . '<label for="apbct_setting_' . $this->params['name'] . '" class="apbct_setting-field_title--' . $this->params['type'] . '">'
                . $this->params['title']
                . '</label>'
                . $this->description_popup;
        //HANDLE LINK
        $href = '<a href="https://cleantalk.org/my/partners" target="_blank">' . __('CleanTalk Affiliate Program are here', 'cleantalk-spam-protect') . '</a>';
        $this->params['description'] = str_replace('{CT_AFFILIATE_TERMS}', $href, $this->params['description']);
        $out .= '<div class="apbct_settings-field_description">'
                . $this->params['description']
                . '</div>';
        return $out;
    }

    /**
     * @return string
     */
    private function getInputRadio()
    {
        $out = isset($this->params['title'])
            ? '<h4 class="apbct_settings-field_title apbct_settings-field_title--' . $this->params['type'] . '">' . $this->params['title'] . $this->description_popup . '</h4>'
            : '';
        //ESC NEED
        $out .= '<div class="apbct_settings-field_content apbct_settings-field_content--' . $this->params['type'] . '">';

        $out .= '<div class="apbct_switchers" style="direction: ltr">';
        foreach ( $this->params['options'] as $option ) {
            //ESC NEED
            $out .= '<input'
                    . ' type="radio"'
                    . " class='apbct_setting_{$this->params['type']} apbct_setting---{$this->params['name']}'"
                    . " id='apbct_setting_{$this->params['name']}__{$option['label']}'"
                    . ' name="cleantalk_settings[' . $this->params['name'] . ']"'
                    . ' value="' . $option['val'] . '"'
                    . $this->disabled_string
                    . ($this->params['childrens']
                    ? ' onchange="apbctSettingsDependencies(\'' . $this->children_string . '\', ' . $option['childrens_enable'] . ')"'
                    : ''
                    )
                    . ($this->value == $option['val'] ? ' checked' : '')
                    . ($this->params['required'] ? ' required="required"' : '')
                    . ' />';
            //ESC NEED
            $out .= '<label for="apbct_setting_' . $this->params['name'] . '__' . $option['label'] . '"> ' . $option['label'] . '</label>';
            $out .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
        }
        $out .= '</div>';
        //ESC NEED
        $out .= isset($this->params['description'])
            ? '<div class="apbct_settings-field_description">' . $this->params['description'] . '</div>'
            : '';
        $out .= '</div>';
        return $out;
    }

    /**
     * @return string
     */
    private function getInputSelect()
    {
        if ($this->params['name'] === 'comments__form_decoration_selector') {
            $this->params = FormDecoratorSettings::filterSelectorParams($this->params, $this->disabled_string, $this->description_popup);
        }

        $title = '';
        if (isset($this->params['custom_select_title'])) {
            $title = $this->params['custom_select_title'];
        } elseif (isset($this->params['title'])) {
            $title = '<h4 class="apbct_settings-field_title apbct_settings-field_title--' . $this->params['type'] . '">' . $this->params['title'] . $this->description_popup . '</h4>';
        }

        $description = '';
        if (isset($this->params['custom_select_description'])) {
            $description = $this->params['custom_select_description'];
        } elseif (isset($this->params['description'])) {
            $description = '<div class="apbct_settings-field_description">' . $this->params['description'] . '</div>';
        }

        if (isset($this->params['custom_select_element'])) {
            $select = $this->params['custom_select_element'];
        } else {
            $select = '<select'
                      . ' id="apbct_setting_' . $this->params['name'] . '"'
                      . " class='apbct_setting_{$this->params['type']} apbct_setting---{$this->params['name']}'"
                      . ' name="cleantalk_settings[' . $this->params['name'] . ']' . ($this->params['multiple'] ? '[]"' : '"')
                      . ($this->params['multiple'] ? ' size="' . count($this->params['options']) . '""' : '')
                      . ($this->params['multiple'] ? ' multiple="multiple"' : '')
                      . ($this->params['childrens']
                    ? ' onchange="apbctSettingsDependencies(\'' . $this->children_string . '\', jQuery(this).find(\'option:selected\').data(\'children_enable\'))"'
                    : ''
                      )
                      . $this->disabled_string
                      . ($this->params['required'] ? ' required="required"' : '')
                      . ' >';
        }

        foreach ( $this->params['options'] as $option ) {
            //ESC NEED
            $select .= '<option'
                       . ' value="' . $option['val'] . '"'
                       . (isset($option['children_enable']) ? ' data-children_enable=' . $option['children_enable'] . ' ' : ' ')
                       . ($this->params['multiple']
                    ? (! empty($this->value) && is_array($this->value) && in_array($option['val'], $this->value) ? ' selected="selected"' : '')
                    : ($this->value == $option['val'] ? 'selected="selected"' : '')
                       )
                       . '>'
                       . $option['label']
                       . '</option>';
        }

        $select .= '</select>';

        if (isset($this->params['nest_label_and_description_after_select'])) {
            $output = $select . $title . $description;
        } else {
            $output = $title . $description . $select;
        }

        return $output;
    }

    /**
     * @return string
     */
    private function getInputText()
    {
        $out = '<input
					type="text"
					id="apbct_setting_' . $this->params['name'] . '"
					name="cleantalk_settings[' . $this->params['name'] . ']"'
               . " class='apbct_setting_{$this->params['type']} apbct_setting---{$this->params['name']}'"
               . ' value="' . $this->value . '" '
               . (isset($this->params['placeholder']) ? ' placeholder="' . $this->params['placeholder'] : '') . '" '
               . $this->disabled_string
               . ($this->params['required'] ? ' required="required"' : '')
               . ($this->params['childrens'] ? ' onchange="apbctSettingsDependencies(\'' . $this->children_string . '\')"' : '')
               . ' />'
               . '&nbsp;'
               . '<label for="apbct_setting_' . $this->params['name'] . '" class="apbct_setting-field_title--' . $this->params['type'] . '">'
               . $this->params['title'] . $this->description_popup
               . '</label>';
        $out .= '<div class="apbct_settings-field_description">'
                . $this->params['description']
                . '</div>';
        return $out;
    }

    /**
     * @return string
     */
    private function getAffiliateShortCode()
    {
        $out = '<input
					type="text"
					id="apbct_setting_' . $this->params['name'] . '"
					name="cleantalk_settings[' . $this->params['name'] . ']"'
               . " class='apbct_setting_{$this->params['type']} apbct_setting---{$this->params['name']}'"
               . ' value="[cleantalk_affiliate_link]" '
               . "readonly" //hardcode for this shortcode
               . $this->disabled_string
               . ($this->params['required'] ? ' required="required"' : '')
               . ($this->params['childrens'] ? ' onchange="apbctSettingsDependencies(\'' . $this->children_string . '\')"' : '')
               . ' />'
               . '&nbsp;'
               . '<label for="apbct_setting_' . $this->params['name'] . '" class="apbct_setting-field_title--' . $this->params['type'] . '">'
               . $this->params['title'] . $this->description_popup
               . '</label>';
        $out .= '<div class="apbct_settings-field_description">'
                . $this->params['description']
                . '</div>';
        return $out;
    }

    /**
     * @return string
     */
    private function getInputTextarea()
    {
        $out = isset($this->params['title'])
            ? '<h4 class="apbct_settings-field_title apbct_settings-field_title--' . $this->params['type'] . '">' . $this->params['title'] . $this->description_popup . '</h4>'
            : '';
        //ESC NEED
        $out .= '<div class="apbct_settings-field_description">'
                . $this->params['description']
                . '</div>';
        //ESC NEED
        $out .= '<textarea
					id="apbct_setting_' . $this->params['name'] . '"
					name="cleantalk_settings[' . $this->params['name'] . ']"'
                . " class='apbct_setting_{$this->params['type']} apbct_setting---{$this->params['name']}'"
                . $this->disabled_string
                . ($this->params['required'] ? ' required="required"' : '')
                . ($this->params['childrens'] ? ' onchange="apbctSettingsDependencies(\'' . $this->children_string . '\')"' : '')
                . '>' . $this->value . '</textarea>'
                . '&nbsp;';
        return $out;
    }

    /**
     * @return string
     */
    private function getInputColor()
    {
        $out = '';
        //ESC NEED
        $out .= '<input
					type="color"
					id="apbct_setting_' . $this->params['name'] . '"
					name="cleantalk_settings[' . $this->params['name'] . ']"'
                . " class='apbct_setting_{$this->params['type']} apbct_setting---{$this->params['name']}'"
                . ' value="' . $this->value . '" '
                . $this->disabled_string
                . ($this->params['required'] ? ' required="required"' : '')
                . ($this->params['childrens'] ? ' onchange="apbctSettingsDependencies(\'' . $this->children_string . '\')"' : '')
                . ' />'
                . '&nbsp;'
                . '<label for="apbct_setting_' . $this->params['name'] . '" class="apbct_setting-field_title--' . $this->params['type'] . '">'
                . $this->params['title'] . $this->description_popup
                . '</label>';
        $out .= '<div class="apbct_settings-field_description">'
                . $this->params['description']
                . '</div>';
        return $out;
    }
}
