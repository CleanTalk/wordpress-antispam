<?php


namespace Cleantalk\ApbctWP;


class CleantalkSettingsTemplates {

	private $api_key;

	/**
	 * CleantalkDefaultSettings constructor.
	 *
	 * @param $api_key
	 */
	public function __construct( $api_key )
	{
		$this->api_key = $api_key;
		if( $this->api_key ) {
			add_filter( 'apbct_settings_action_buttons', array( $this, 'add_action_button' ), 10, 1 );
		}
	}

	public function add_action_button( $links ) {
		$link = '<a href="#" class="ct_support_link" onclick="cleantalkModal.open(\'apbct_settings_templates\')">' . __('Import/Export settings', 'cleantalk-spam-protect') . '</a>';
		$links[]    = $link;
		return $links;
	}

	public static function get_options_template( $api_key ) {
		// @ToDo here will be API call services_templates_get
		//\Cleantalk\Common\API::method__services_templates_get( $api_key );
		return array(
			1 => array(
				'template_id' => 1,
				'name' => 'Test Settings Template',
				'options_site' => 'json_string',
			),
		);
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
		$templatesSet = '<p><select>';
		foreach( $templates as $template ) {
			$templatesSet .= '<option id="apbcs_settings_template_id_' . $template['template_id'] . '">' . $template['name'] . '</option>';
		}
		$templatesSet .= '</select></p>';
		$button = $this->getImportButton();
		return $templatesSet . '<br>' . $button . '<br><hr>';
	}

	public function getHtmlContentExport( $templates )
	{
		$templatesSet = '<p><select>';
		$templatesSet .= '<option checked="true">New template</option>';
		foreach( $templates as $template ) {
			$templatesSet .= '<option id="apbcs_settings_template_id_' . $template['template_id'] . '">' . $template['name'] . '</option>';
		}
		$templatesSet .= '</select></p>';
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