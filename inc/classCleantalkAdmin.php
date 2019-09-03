<?php


class classCleantalkAdmin {

	/**
	 * Flag: hooks was initiated or not
	 */
	private static $launched = false;

	/**
	 * Init method
	 * Launched once by 'init' wp hook
	 */
	public static function init()
	{
		
		if ( ! self::$launched ) {
			self::init_hooks();
		}
		
	}

	/**
	 * Plugging Up WordPress hooks
	 * Contains native WP functionality and Integrations
	 */
	private static function init_hooks()
	{
		
		self::$launched = true;

		// Admin side hooks will be placed here
	}

	/**
	 *  Methods accepted by public hooks in init_hooks()
	 *  The methods have to be staic
	 */

}