<?php


namespace Cleantalk\ApbctWP;


class CleantalkSettingsTemplates {

	private $api_key;

	private static $templates = null;

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
		$link = '<a href="#" class="ct_support_link" onclick="cleantalkModal.open()" data-content-action="get_options_template">' . __('Import/Export settings', 'cleantalk-spam-protect') . '</a>';
		$links[]    = $link;
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
		if( isset( $_POST['data'] ) && is_array( $_POST['data'] ) ) {
			$template_info = $_POST['data'];
			if( isset( $template_info['template_id'] ) ) {
				$template_id = sanitize_text_field( $template_info['template_id'] );
				$res = \Cleantalk\Common\API::method__services_templates_add( $this->api_key, $template_id );
				if( empty( $res['error'] ) ) {
					wp_send_json_success( esc_html__('Success. Reloading...', 'cleantalk-spam-protect' ) );
				} else {
					wp_send_json_error( $res['error'] );
				}
			}
			if( isset( $template_info['template_name'] ) ) {
				$template_name = sanitize_text_field( $template_info['template_name'] );
				$res = \Cleantalk\Common\API::method__services_templates_add( $this->api_key, $template_name );
				if( empty( $res['error'] ) ) {
					wp_send_json_success( esc_html__('Success. Reloading...', 'cleantalk-spam-protect' ) );
				} else {
					wp_send_json_error( $res['error'] );
				}
			}
		}
		wp_send_json_error( 'Export handler error.' );
	}

	public function settings_templates_import_ajax()
	{
		check_ajax_referer('ct_secret_nonce', 'security');
		if( isset( $_POST['data'] ) && is_array( $_POST['data'] ) ) {
			$template_info = $_POST['data'];
			if( isset( $template_info['template_id'], $template_info['template_name'], $template_info['settings'] ) ) {
				$res = apbct_set_plugin_options( $template_info['template_id'], $template_info['template_name'], $template_info['settings'] );
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
		$res = apbct_reset_plugin_options();
		if( empty( $res['error'] ) ) {
			wp_send_json_success( esc_html__('Success. Reloading...', 'cleantalk-spam-protect' ) );
		} else {
			wp_send_json_error( $res['error'] );
		}
	}

	public static function get_options_template( $api_key )
	{
		// @ToDo here will be API call services_templates_get
		//\Cleantalk\Common\API::method__services_templates_get( $api_key );
		if( ! self::$templates ) {
			self::$templates = array(
				1 => array(
					'template_id' => 1,
					'name' => 'Test Settings Template',
					'options_site' => 'json_string',
				),
			);
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
		return $title . '<br>' . $out;
	}

	private function getHtmlContentImport( $templates )
	{
		$templatesSet = '<h3>' . esc_html__( 'Import settings.', 'cleantalk-spam-protect' ) . '</h3>';
		$templatesSet .= '<p><select id="apbct_settings_templates_import" >';
		foreach( $templates as $template ) {
			$templatesSet .= '<option 
									data-id="' . $template['template_id'] . '"
									data-name="' . $template['name'] . '"
									data-settings="' . $template['options_site'] . '">'
			                 . $template['name']
			                 . '</option>';
		}
		$templatesSet .= '</select></p>';
		$button = $this->getImportButton();
		return $templatesSet . '<br>' . $button . '<br><hr>';
	}

	public function getHtmlContentExport( $templates )
	{
		$templatesSet = '<h3>' . esc_html__( 'Export settings.', 'cleantalk-spam-protect' ) . '</h3>';
		$templatesSet .= '<p><select id="apbct_settings_templates_export" >';
		$templatesSet .= '<option data-id="new_template" checked="true">New template</option>';
		foreach( $templates as $template ) {
			$templatesSet .= '<option data-id="' . $template['template_id'] . '">' . $template['name'] . '</option>';
		}
		$templatesSet .= '</select></p>';
		$templatesSet .= '<p><input type="text" id="apbct_settings_templates_export_name" name="apbct_settings_template_name" placeholder="' . esc_html__( 'Enter a template name.', 'cleantalk-spam-protect' ) . '" required /></p>';
		$button = $this->getExportButton();
		return $templatesSet . '<br>' . $button . '<br>';
	}

	public function getHtmlContentReset()
	{
		$content = '<h3>' . esc_html__( 'Reset settings.', 'cleantalk-spam-protect' ) . '</h3>';
		return '<br><br><hr><br>' . $content . '<br>' .  $this->getResetButton() . '<br>';
	}

	private function getTitle()
	{
		return '<h2>' . esc_html__( 'CleanTalk settings templates.', 'cleantalk-spam-protect' ) . '</h2>';
	}

	private function getExportButton()
	{
		return '<button id="apbct_settings_templates_export_button" class="cleantalk_link cleantalk_link-manual">'
		       . esc_html__( 'Export to selected template.', 'cleantalk-spam-protect' )
		       . '<img style="margin-left: 10px;" class="apbct_preloader_button" src="' . APBCT_URL_PATH . '/inc/images/preloader2.gif" />'
		       . '<img style="margin-left: 10px;" class="apbct_success --hide" src="' . APBCT_URL_PATH . '/inc/images/yes.png" />'
		       . '</button>';
	}

	private function getImportButton(){
		return '<button id="apbct_settings_templates_import_button" class="cleantalk_link cleantalk_link-manual">'
		       . esc_html__( 'Import settings from selected template.', 'cleantalk-spam-protect' )
		       . '<img style="margin-left: 10px;" class="apbct_preloader_button" src="' . APBCT_URL_PATH . '/inc/images/preloader2.gif" />'
		       . '<img style="margin-left: 10px;" class="apbct_success --hide" src="' . APBCT_URL_PATH . '/inc/images/yes.png" />'
		       . '</button>';
	}

	private function getResetButton(){
		return '<button id="apbct_settings_templates_reset_button" class="cleantalk_link cleantalk_link-auto">'
		       . esc_html__( 'Reset setting to defaults.', 'cleantalk-spam-protect' )
		       . '<img style="margin-left: 10px;" class="apbct_preloader_button" src="' . APBCT_URL_PATH . '/inc/images/preloader2.gif" />'
		       . '<img style="margin-left: 10px;" class="apbct_success --hide" src="' . APBCT_URL_PATH . '/inc/images/yes.png" />'
		       . '</button>';
	}

}