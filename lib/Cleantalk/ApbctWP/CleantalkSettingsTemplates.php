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
		}
		return $title . '<br>' . $out;
	}

	private function getHtmlContentImport( $templates )
	{
		$templatesSet = '<p><select id="templates_import" >';
		foreach( $templates as $template ) {
			$templatesSet .= '<option data-id="' . $template['template_id'] . '">' . $template['name'] . '</option>';
		}
		$templatesSet .= '</select></p>';
		$button = $this->getImportButton();
		return $templatesSet . '<br>' . $button . '<br><hr>';
	}

	public function getHtmlContentExport( $templates )
	{
		$templatesSet = '<p><select id="templates_export" >';
		$templatesSet .= '<option id="new_template" checked="true">New template</option>';
		foreach( $templates as $template ) {
			$templatesSet .= '<option data-id="' . $template['template_id'] . '">' . $template['name'] . '</option>';
		}
		$templatesSet .= '</select></p>';
		$templatesSet .= '<p><input type="text" name="template_name" placeholder="' . esc_html__( 'Enter a template name.', 'cleantalk-spam-protect' ) . '" required /></p>';
		$button = $this->getExportButton();
		return $templatesSet . '<br>' . $button . '<br>';
	}

	private function getTitle()
	{
		return '<h2>' . esc_html__( 'CkeanTalk settings templates.', 'cleantalk-spam-protect' ) . '</h2>';
	}

	private function getExportButton()
	{
		return '<button id="apbct_settings_templates_export" class="cleantalk_link cleantalk_link-manual">' . esc_html__( 'Export to selected template.', 'cleantalk-spam-protect' ) . '</button>';
	}

	private function getImportButton(){
		return '<button id="apbct_settings_templates_import" class="cleantalk_link cleantalk_link-manual">' . esc_html__( 'Import settings from selected template.', 'cleantalk-spam-protect' ) . '</button>';
	}

}