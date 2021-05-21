<?php

namespace Cleantalk\Common;

use Cleantalk\ApbctWP\CleantalkSettingsTemplates;

/**
 * CleanTalk Compatibility class.
 *
 * @package       PHP Antispam by CleanTalk
 * @subpackage    Compatibility
 * @Version       3.5
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see           https://github.com/CleanTalk/php-antispam
 */
class Compatibility
{
	public $notices = array();

	public function __construct() {
		if($compatibility_issues = $this->compatibility_issues()) {
			foreach ($compatibility_issues as $issue) {
				$res = call_user_func(array($this, $issue['callback']));

				if($res) {
					$this->notices[] = $issue;
				}
			}

			global $apbct;
			if(!empty($this->notices)) {
				$apbct->data['notice_incompatibility'] = $this->notices;
				$apbct->data['notice_show'] = 1;
			} else {
				$apbct->data['notice_incompatibility'] = array();
				$apbct->data['notice_show'] = 0;
			}
		}
	}

	/**
	 * Function return array compatibility_issues
	 */
	private function compatibility_issues () {
		return array(
			array(
				'plugin_name' => 'W3 Total Cache',
				'callback' => 'w3tc_late_init_callback',
				'message' => '<h3><b>'.
				             __("Current W3 Total Cache cache mode is not compatible with SpamFireWall. Please read the instructions on how to fix this issue in <a href='https://cleantalk.org/help/cleantalk-and-w3-total-cache'>the article.</a>", 'cleantalk-spam-protect') .
				             '</b></h3>
							 <script>jQuery(document).ready(function(){jQuery("#apbct_setting_sfw__enabled").prop("disabled",true)})</script>'
			),
		);
	}

	/**
	 * W3 Total Cache check late init option
	 *
	 * @return boolean
	 */
	public function w3tc_late_init_callback() {
		if ( ! is_plugin_active('w3-total-cache/w3-total-cache.php')) {
			return false;
		}

		if(file_exists(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'w3tc-config/master.php')) {
			$w3_config = file_get_contents(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'w3tc-config/master.php');
			$w3_config = str_replace('<?php exit; ?>', '', $w3_config);
			$w3_config = json_decode($w3_config, true);

			if(
			    (isset($w3_config['pgcache.cache.ssl']) && ! $w3_config['pgcache.cache.ssl']) ||
			    (isset($w3_config['pgcache.late_init']) && $w3_config['pgcache.late_init'])) {
				return false;
			}

			global $apbct;
			$settings = get_option( 'cleantalk_settings' );
			$settings['sfw__enabled'] = 0;
			update_option('cleantalk_settings', $settings);
			$apbct->settings['sfw__enabled'] = 0;

			return true;
		}
	}
}