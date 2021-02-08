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
	}

	public function getHtmlContent( $import_only = false )
	{
		$title = $this->getTitle();
		$out = $this->getHtmlContentImport();
		if( ! $import_only ) {
			$out .= $this->getHtmlContentExport();
		}
		return $title . '<br>' . $out;
	}

	private function getHtmlContentImport()
	{
		$templatesSet = $this->getTemplates();
		$exportButton = $this->getImportButton();
		return $templatesSet . '<br>' . $exportButton . '<br><hr>';
	}

	public function getHtmlContentExport()
	{
		$templatesSet = $this->getTemplates();
		$exportButton = $this->getExportButton();
		return $templatesSet . '<br>' . $exportButton . '<br>';
	}

	private function getTitle()
	{
		return '<h2>' . esc_html__( 'CkeanTalk settings templates.', 'cleantalk-spam-protect' ) . '</h2>';
	}

	private function getTemplates()
	{
		// @ToDo here will be API call services_templates_get
		$out = '<p><select>';
		$out .= '<option checked="true">New template</option>';
		$out .= '<option>Custom template 1</option>';
		$out .= '<option>Custom template 2</option>';
		$out .= '</select></p>';
		return $out;
	}

	private function getExportButton()
	{
		return '<button id="apbct_settings_templates_export" class="cleantalk_link cleantalk_link-manual">' . esc_html__( 'Export to selected template.', 'cleantalk-spam-protect' ) . '</button>';
	}

	private function getImportButton(){
		return '<button id="apbct_settings_templates_export" class="cleantalk_link cleantalk_link-manual">' . esc_html__( 'Import settings from selected template.', 'cleantalk-spam-protect' ) . '</button>';
	}

}