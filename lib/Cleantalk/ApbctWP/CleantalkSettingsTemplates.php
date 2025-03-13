<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\TT;

class CleantalkSettingsTemplates
{
    private $api_key;

    private static $templates;

    /**
     * CleantalkDefaultSettings constructor.
     *
     * @param $api_key
     */
    public function __construct($api_key)
    {
        $this->api_key = $api_key;
        add_action('wp_ajax_get_options_template', array($this, 'getOptionsTemplateAjax'));
        add_action('wp_ajax_settings_templates_export', array($this, 'settingsTemplatesExportAjax'));
        add_action('wp_ajax_settings_templates_import', array($this, 'settingsTemplatesImportAjax'));
        add_action('wp_ajax_settings_templates_reset', array($this, 'settingsTemplatesResetAjax'));
    }

    public function getOptionsTemplateAjax()
    {
        AJAXService::checkNonceRestrictingNonAdmins();
        echo $this->getHtmlContent();
        die();
    }

    public function settingsTemplatesExportAjax()
    {
        AJAXService::checkNonceRestrictingNonAdmins();
        $error_text = 'Export handler error.';
        if ( is_array(Post::get('data')) ) {
            $template_info = Post::get('data');
            if ( isset($template_info['template_id']) ) {
                $template_id = sanitize_text_field($template_info['template_id']);
                $res         = \Cleantalk\ApbctWP\API::methodServicesTemplatesUpdate(
                    $this->api_key,
                    (int)$template_id,
                    $this->getPluginOptions()
                );
                if ( is_array($res) && array_key_exists('operation_status', $res) ) {
                    if ( $res['operation_status'] === 'SUCCESS' ) {
                        wp_send_json_success(esc_html__('Success. Reloading...', 'cleantalk-spam-protect'));
                    }
                    if ( $res['operation_status'] === 'FAILED' && isset($res['operation_message']) ) {
                        wp_send_json_error('Error: ' . TT::toString($res['operation_message']));
                    }
                }
                $error_text = 'Template updating response is wrong.';
            }
            if ( isset($template_info['template_name']) ) {
                $template_name = sanitize_text_field($template_info['template_name']);
                $res           = \Cleantalk\ApbctWP\API::methodServicesTemplatesAdd(
                    $this->api_key,
                    $template_name,
                    $this->getPluginOptions()
                );
                if ( is_array($res) && array_key_exists('operation_status', $res) ) {
                    if ( $res['operation_status'] === 'SUCCESS' ) {
                        wp_send_json_success(esc_html__('Success. Reloading...', 'cleantalk-spam-protect'));
                    }
                    if ( $res['operation_status'] === 'FAILED' && isset($res['operation_message']) ) {
                        wp_send_json_error('Error: ' . TT::toString($res['operation_message']));
                    }
                }
                $error_text = 'Template adding response is wrong.';
            }
        }
        wp_send_json_error('Error: ' . $error_text);
    }

    public function settingsTemplatesImportAjax()
    {
        AJAXService::checkNonceRestrictingNonAdmins();
        if ( is_array(Post::get('data')) ) {
            $template_info = Post::get('data');
            if ( isset($template_info['template_id'], $template_info['template_name'], $template_info['settings']) ) {
                $res = $this->setPluginOptions(
                    (int)$template_info['template_id'],
                    htmlspecialchars($template_info['template_name']),
                    $template_info['settings']
                );
                if ( empty($res['error']) ) {
                    wp_send_json_success(esc_html__('Success. Reloading...', 'cleantalk-spam-protect'));
                } else {
                    wp_send_json_error($res['error']);
                }
            }
        }
        wp_send_json_error('Import handler error.');
    }

    public function settingsTemplatesResetAjax()
    {
        AJAXService::checkNonceRestrictingNonAdmins();
        $res = $this->resetPluginOptions();
        if ( empty($res['error']) ) {
            wp_send_json_success(esc_html__('Success. Reloading...', 'cleantalk-spam-protect'));
        } else {
            wp_send_json_error($res['error']);
        }
    }

    public static function getOptionsTemplate($api_key)
    {
        if ( ! self::$templates ) {
            $res = \Cleantalk\ApbctWP\API::methodServicesTemplatesGet($api_key);
            if ( is_array($res) ) {
                if ( array_key_exists('error', $res) ) {
                    $templates = array();
                } else {
                    $templates = $res;
                }
            } else {
                $templates = array();
            }

            self::$templates = $templates;
        }

        return self::$templates;
    }

    public function getHtmlContent($import_only = false)
    {
        $templates = self::getOptionsTemplate($this->api_key);
        $title     = $this->getTitle();
        $out       = $this->getHtmlContentImport($templates);
        if ( ! $import_only ) {
            $out .= $this->getHtmlContentExport($templates);
            $out .= $this->getHtmlContentReset();
        }

        return $title . $out;
    }

    private function getHtmlContentImport($templates)
    {
        global $apbct;

        $templatesSet = '<h3>' . esc_html__('Import settings', 'cleantalk-spam-protect') . '</h3>';

        //Check available option_site parameter
        if ( count($templates) > 0 ) {
            foreach ( $templates as $key => $template ) {
                if ( empty($template['options_site']) ) {
                    unset($templates[$key]);
                }
            }
        }

        if ( count($templates) === 0 ) {
            $templatesSet .= esc_html__('There are no settings templates', 'cleantalk-spam-protect');

            return $templatesSet . '<br><hr>';
        }

        $templatesSet .= '<p><select id="apbct_settings_templates_import" >';
        foreach ( $templates as $template ) {
            $templatesSet .= "<option 
								data-id='" . $template['template_id'] . "'
								data-name='" . htmlspecialchars($template['name']) . "''
								data-settings='" . $template['options_site'] . "'>"
                             . htmlspecialchars($template['name'])
                             . "</option>";
        }
        $templatesSet .= '</select></p>';
        $button       = $this->getImportButton();

        $templatesDeleteTip =
            //HANDLE LINK
            sprintf(
                /* translators: CleanTalk dash bord (Settings Templates) URL */
                __('Deleting templates is available in the <a href="%s" target="_blank">CleanTalk Dashboard</a>', 'cleantalk-spam-protect'),
                'https://cleantalk.org/my/services_templates?product=antispam&user_token=' . Escape::escHtml($apbct->user_token)
            );

        return $templatesSet . '<br>' . $button . '<br><small>' . $templatesDeleteTip . '</small><br><hr>';
    }

    public function getHtmlContentExport($templates)
    {
        $templatesSet = '<h3>' . esc_html__('Export settings', 'cleantalk-spam-protect') . '</h3>';
        $templatesSet .= '<p><select id="apbct_settings_templates_export" >';
        $templatesSet .= '<option data-id="new_template" checked="true">New template</option>';
        foreach ( $templates as $template ) {
            $templatesSet .= '<option data-id="' . $template['template_id'] . '">' .
                             htmlspecialchars($template['name']) .
                             '</option>';
        }
        $templatesSet .= '</select></p>';
        $templatesSet .= '<p><input type="text" id="apbct_settings_templates_export_name" name="apbct_settings_template_name" placeholder="' .
                         esc_html__('Enter a template name', 'cleantalk-spam-protect') . '" required /></p>';
        $button       = $this->getExportButton();

        return $templatesSet . '<br>' . $button . '<br>';
    }

    public function getHtmlContentReset()
    {
        return '<hr><br>' . $this->getResetButton() . '<br>';
    }

    private function getTitle()
    {
        global $apbct;
        if ( isset($apbct->data['current_settings_template_name']) && $apbct->data['current_settings_template_name'] ) {
            $current_template_name = $apbct->data['current_settings_template_name'];
        } else {
            $current_template_name = 'default';
        }
        $content = '<h2>' . esc_html__('CleanTalk settings templates', 'cleantalk-spam-protect') . '</h2>';
        $content .= '<p>' . esc_html__('You are currently using:', 'cleantalk-spam-protect') . ' ' . $current_template_name . '</p>';

        return $content;
    }

    private function getExportButton()
    {
        return '<button id="apbct_settings_templates_export_button" class="cleantalk_link cleantalk_link-manual">'
               . esc_html__('Export settings to selected template', 'cleantalk-spam-protect')
               . '<img alt="Preloader ico" style="margin-left: 10px;" class="apbct_preloader_button" src="' . APBCT_URL_PATH . '/inc/images/preloader2.gif" />'
               . '<img alt="Success ico" style="margin-left: 10px;" class="apbct_success --hide" src="' . APBCT_URL_PATH . '/inc/images/yes.png" />'
               . '</button>';
    }

    private function getImportButton()
    {
        return '<button id="apbct_settings_templates_import_button" class="cleantalk_link cleantalk_link-manual">'
               . esc_html__('Import settings from selected template', 'cleantalk-spam-protect')
               . '<img alt="Preloader ico" style="margin-left: 10px;" class="apbct_preloader_button" src="' . APBCT_URL_PATH . '/inc/images/preloader2.gif" />'
               . '<img alt="Success ico" style="margin-left: 10px;" class="apbct_success --hide" src="' . APBCT_URL_PATH . '/inc/images/yes.png" />'
               . '</button>';
    }

    private function getResetButton()
    {
        return '<button id="apbct_settings_templates_reset_button" class="ct_support_link">'
               . esc_html__('Reset settings to defaults', 'cleantalk-spam-protect')
               . '<img alt="Preloader ico" style="margin-left: 10px;" class="apbct_preloader_button" src="' . APBCT_URL_PATH . '/inc/images/preloader2.gif" />'
               . '<img alt="Success ico" style="margin-left: 10px;" class="apbct_success --hide" src="' . APBCT_URL_PATH . '/inc/images/yes.png" />'
               . '</button>';
    }

    private function getPluginOptions()
    {
        global $apbct;
        $settings = (array)$apbct->settings;
        // Remove apikey from export
        if ( isset($settings['apikey']) ) {
            unset($settings['apikey']);
        }
        // Remove misc__debug_ajax from export
        if ( isset($settings['misc__debug_ajax']) ) {
            unset($settings['misc__debug_ajax']);
        }
        // Remove all WPMS from export
        $settings = array_filter($settings, function ($key) {
            return strpos($key, 'multisite__') === false;
        }, ARRAY_FILTER_USE_KEY);

        return json_encode($settings, JSON_FORCE_OBJECT);
    }

    public function setPluginOptions($template_id, $template_name, $settings)
    {
        global $apbct, $wpdb;

        /**
         * $blog_ids is an array of the IDs to apply settings for these blogs
         */
        $blogs_ids = apply_filters('apbct_template_save_to', array());

        if ( is_multisite() && is_array($blogs_ids) && count($blogs_ids) ) {
            $initial_blog = get_current_blog_id();
            if ( ! in_array($initial_blog, $blogs_ids) ) {
                $blogs_ids[] = $initial_blog;
            }
            $available_blogs = $wpdb->get_col('SELECT blog_id FROM ' . $wpdb->prefix . 'blogs;');
            foreach ( $blogs_ids as $blog_id ) {
                if ( ! in_array($blog_id, $available_blogs) ) {
                    continue;
                }
                switch_to_blog($blog_id);
                $old_settings = get_option($apbct->option_prefix . '_settings');
                $old_data = get_option($apbct->option_prefix . '_data');
                if ( $old_settings !== false && $old_data !== false ) {
                    $new_settings = array_replace($old_settings, $settings);
                    $new_settings = apbct_settings__validate($new_settings);
                    $data = [];
                    $data['current_settings_template_id'] = $template_id;
                    $data['current_settings_template_name'] = $template_name;
                    $data['key_changed'] = 1;
                    $new_data = array_replace($old_data, $data);
                    update_option($apbct->option_prefix . '_settings', $new_settings);
                    update_option($apbct->option_prefix . '_data', $new_data);
                }
                restore_current_blog();
            }
            switch_to_blog($initial_blog);
        } else {
            $settings = array_replace((array)$apbct->settings, $settings);
            $settings = apbct_settings__validate($settings);

            if ( (array)$apbct->settings == $settings ) {
                return true;
            }

            $apbct->settings = $settings;
            $apbct->data['current_settings_template_id'] = $template_id;
            $apbct->data['current_settings_template_name'] = $template_name;
            $apbct->data['key_changed'] = 1;

            return $apbct->saveSettings() && $apbct->saveData();
        }
        return false;
    }

    public function resetPluginOptions()
    {
        global $apbct;

        $def_settings = $apbct->default_settings;
        if ( isset($def_settings['apikey']) ) {
            unset($def_settings['apikey']);
        }
        $settings        = array_replace((array)$apbct->settings, $def_settings);
        $settings        = apbct_settings__validate($settings);
        $apbct->settings = $settings;

        $apbct->data['current_settings_template_id'] = null;
        $apbct->data['current_settings_template_name'] = null;
        $apbct->saveData();

        return $apbct->saveSettings();
    }
}
