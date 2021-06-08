<?php


namespace Cleantalk\ApbctWP;


class CleantalkSettingsTemplates {

	private $api_key;

	private static $templates;

	/**
	 * CleantalkDefaultSettings constructor.
	 *
	 * @param $api_key
	 */
	public function __construct( $api_key )
	{
		$this->api_key = $api_key;
		add_filter( 'apbct_settings_action_buttons', array( $this, 'add_action_button' ), 10, 1 );
		add_action( 'wp_ajax_get_options_template', array( $this, 'get_options_template_ajax' ) );
		add_action( 'wp_ajax_settings_templates_export', array( $this, 'settings_templates_export_ajax' ) );
		add_action( 'wp_ajax_settings_templates_import', array( $this, 'settings_templates_import_ajax' ) );
		add_action( 'wp_ajax_settings_templates_reset', array( $this, 'settings_templates_reset_ajax' ) );
		add_action( 'apbct_settings_template_get', array( $this, 'open_templates_dialog' ) );
	}

	public function add_action_button( $links )
	{
		if( is_array( $links ) ) {
			$last_link = array_slice( $links, -1, 1 );
			unset( $links[ count($links) - 1 ] );
			$link    = '<a href="#" class="ct_support_link" onclick="cleantalkModal.open()">' . __('Import/Export settings', 'cleantalk-spam-protect') . '</a>';
			$links[] = $link;
			$links[] = $last_link[0];
		}
		return $links;
	}

	public function get_options_template_ajax()
	{
		check_ajax_referer('ct_secret_nonce', 'security');
		echo $this->getHtmlContent();
		die();
	}

	public function settings_templates_export_ajax()
	{
		check_ajax_referer('ct_secret_nonce', 'security');
		$error_text = 'Export handler error.';
		if( isset( $_POST['data'] ) && is_array( $_POST['data'] ) ) {
			$template_info = $_POST['data'];
			if( isset( $template_info['template_id'] ) ) {
				$template_id = sanitize_text_field( $template_info['template_id'] );
				$res = \Cleantalk\Common\API::method__services_templates_update( $this->api_key, $template_id, $this->get_plugin_options() );
				if( is_array( $res ) && array_key_exists( 'operation_status', $res ) ) {
					if( $res['operation_status'] === 'SUCCESS' ) {
						wp_send_json_success( esc_html__('Success. Reloading...', 'cleantalk-spam-protect' ) );
					}
					if ( $res['operation_status'] === 'FAILED' ) {
						wp_send_json_error( 'Error: ' . $res['operation_message'] );
					}
				}
				$error_text = 'Template updating response is wrong.';
			}
			if( isset( $template_info['template_name'] ) ) {
				$template_name = sanitize_text_field( $template_info['template_name'] );
				$res = \Cleantalk\Common\API::method__services_templates_add( $this->api_key, $template_name, $this->get_plugin_options() );
				if( is_array( $res ) && array_key_exists( 'operation_status', $res ) ) {
					if( $res['operation_status'] === 'SUCCESS' ) {
						wp_send_json_success( esc_html__('Success. Reloading...', 'cleantalk-spam-protect' ) );
					}
					if ( $res['operation_status'] === 'FAILED' ) {
						wp_send_json_error( 'Error: ' . $res['operation_message'] );
					}
				}
				$error_text = 'Template adding response is wrong.';
			}
		}
		wp_send_json_error( 'Error: ' . $error_text );
	}

	public function settings_templates_import_ajax()
	{
		check_ajax_referer('ct_secret_nonce', 'security');
		if( isset( $_POST['data'] ) && is_array( $_POST['data'] ) ) {
			$template_info = $_POST['data'];
			if( isset( $template_info['template_id'], $template_info['template_name'], $template_info['settings'] ) ) {
				$res = $this->set_plugin_options( $template_info['template_id'], $template_info['template_name'], $template_info['settings'] );
				if( empty( $res['error'] ) ) {
					wp_send_json_success( esc_html__('Success. Reloading...', 'cleantalk-spam-protect' ) );
				} else {
					wp_send_json_error( $res['error'] );
				}
			}
		}
		wp_send_json_error( 'Import handler error.' );
	}

	public function settings_templates_reset_ajax()
	{
		check_ajax_referer('ct_secret_nonce', 'security');
		$res = $this->reset_plugin_options();
		if( empty( $res['error'] ) ) {
			wp_send_json_success( esc_html__('Success. Reloading...', 'cleantalk-spam-protect' ) );
		} else {
			wp_send_json_error( $res['error'] );
		}
	}

	public static function get_options_template( $api_key )
	{
		if( ! self::$templates ) {

			$res = \Cleantalk\Common\API::method__services_templates_get( $api_key );
			if( is_array( $res ) ) {
				if( array_key_exists( 'error', $res ) ) {
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

	public function getHtmlContent( $import_only = false )
	{
		$templates = self::get_options_template( $this->api_key );
		$title = $this->getTitle();
		$out = $this->getHtmlContentImport( $templates );
		if( ! $import_only ) {
			$out .= $this->getHtmlContentExport( $templates );
			$out .= $this->getHtmlContentReset();
		}
		return $title . $out;
	}

	private function getHtmlContentImport( $templates )
	{
		$templatesSet = '<h3>' . esc_html__( 'Import settings', 'cleantalk-spam-protect' ) . '</h3>';

		//Check available option_site parameter
		if( count( $templates ) > 0 ) {
			foreach( $templates as $key => $template ) {
				if( empty( $template['options_site'] ) ) {
					unset( $templates[$key] );
				}
			}
		}

		if( count( $templates ) === 0 ) {
			$templatesSet .= esc_html__( 'There are no settings templates', 'cleantalk-spam-protect' );
			return $templatesSet . '<br><hr>';
		}

		$templatesSet .= '<p><select id="apbct_settings_templates_import" >';
		foreach( $templates as $template ) {
			$templatesSet .= "<option 
								data-id='" . $template['template_id'] . "'
								data-name='" . htmlspecialchars( $template['name'] ) . "''
								data-settings='" . $template['options_site'] . "'>"
			                 . htmlspecialchars( $template['name'] )
			                 . "</option>";
		}
		$templatesSet .= '</select></p>';
		$button       = $this->getImportButton();

		return $templatesSet . '<br>' . $button . '<br><hr>';
	}

	public function getHtmlContentExport( $templates )
	{
		$templatesSet = '<h3>' . esc_html__( 'Export settings', 'cleantalk-spam-protect' ) . '</h3>';
		$templatesSet .= '<p><select id="apbct_settings_templates_export" >';
		$templatesSet .= '<option data-id="new_template" checked="true">New template</option>';
		foreach( $templates as $template ) {
			$templatesSet .= '<option data-id="' . $template['template_id'] . '">' . htmlspecialchars( $template['name'] ) . '</option>';
		}
		$templatesSet .= '</select></p>';
		$templatesSet .= '<p><input type="text" id="apbct_settings_templates_export_name" name="apbct_settings_template_name" placeholder="' . esc_html__( 'Enter a template name', 'cleantalk-spam-protect' ) . '" required /></p>';
		$button = $this->getExportButton();
		return $templatesSet . '<br>' . $button . '<br>';
	}

	public function getHtmlContentReset()
	{
		return '<hr><br>' . $this->getResetButton() . '<br>';
	}

	private function getTitle()
	{
		global $apbct;
		if( isset( $apbct->data['current_settings_template_name'] ) && $apbct->data['current_settings_template_name'] ) {
			$current_template_name = $apbct->data['current_settings_template_name'];
		} else {
			$current_template_name = 'default';
		}
		$content = '<h2>' . esc_html__( 'CleanTalk settings templates', 'cleantalk-spam-protect' ) . '</h2>';
		$content .= '<p>' . esc_html__( 'You are currently using:', 'cleantalk-spam-protect' ) . ' ' . $current_template_name . '</p>';
		return $content;
	}

	private function getExportButton()
	{
		return '<button id="apbct_settings_templates_export_button" class="cleantalk_link cleantalk_link-manual">'
		       . esc_html__( 'Export settings to selected template', 'cleantalk-spam-protect' )
		       . '<img alt="Preloader ico" style="margin-left: 10px;" class="apbct_preloader_button" src="' . APBCT_URL_PATH . '/inc/images/preloader2.gif" />'
		       . '<img alt="Success ico" style="margin-left: 10px;" class="apbct_success --hide" src="' . APBCT_URL_PATH . '/inc/images/yes.png" />'
		       . '</button>';
	}

	private function getImportButton(){
		return '<button id="apbct_settings_templates_import_button" class="cleantalk_link cleantalk_link-manual">'
		       . esc_html__( 'Import settings from selected template', 'cleantalk-spam-protect' )
		       . '<img alt="Preloader ico" style="margin-left: 10px;" class="apbct_preloader_button" src="' . APBCT_URL_PATH . '/inc/images/preloader2.gif" />'
		       . '<img alt="Success ico" style="margin-left: 10px;" class="apbct_success --hide" src="' . APBCT_URL_PATH . '/inc/images/yes.png" />'
		       . '</button>';
	}

	private function getResetButton(){
		return '<button id="apbct_settings_templates_reset_button" class="ct_support_link">'
		       . esc_html__( 'Reset settings to defaults', 'cleantalk-spam-protect' )
		       . '<img alt="Preloader ico" style="margin-left: 10px;" class="apbct_preloader_button" src="' . APBCT_URL_PATH . '/inc/images/preloader2.gif" />'
		       . '<img alt="Success ico" style="margin-left: 10px;" class="apbct_success --hide" src="' . APBCT_URL_PATH . '/inc/images/yes.png" />'
		       . '</button>';
	}

	private function get_plugin_options() {
		global $apbct;
		$settings = (array) $apbct->settings;
		// Remove apikey from export
		if( isset( $settings['apikey'] ) ) {
			unset( $settings['apikey'] );
		}
		// Remove misc__debug_ajax from export
		if( isset( $settings['misc__debug_ajax'] ) ) {
			unset( $settings['misc__debug_ajax'] );
		}
		// Remove all WPMS from export
		$settings = array_filter( $settings, function( $key ){
			return strpos( $key, 'multisite__' ) === false;
		}, ARRAY_FILTER_USE_KEY );
		return json_encode( $settings, JSON_FORCE_OBJECT );
	}

	private function set_plugin_options( $template_id, $template_name, $settings ) {
		global $apbct;
		$settings = array_replace( (array) $apbct->settings, $settings );
		$settings = apbct_settings__validate($settings);
		$apbct->settings = $settings;
		$apbct->data['current_settings_template_id'] = $template_id;
		$apbct->data['current_settings_template_name'] = $template_name;
		return $apbct->saveSettings() && $apbct->saveData();
	}

	private function reset_plugin_options() {
		global $apbct;
		$def_settings = $apbct->def_settings;
		if( isset( $def_settings['apikey'] ) ) {
			unset( $def_settings['apikey'] );
		}
		$settings = array_replace( (array) $apbct->settings, $def_settings );
		$settings = apbct_settings__validate($settings);
		$apbct->settings = $settings;
		return $apbct->saveSettings();
	}

}