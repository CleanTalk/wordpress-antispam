<?php

namespace Cleantalk\Common;

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
	private $compatibility_issues = array(
		array(
			'plugin_name' => 'W3 Total Cache',
			'callback' => 'w3tc_late_init_callback',
			'message' => 'W3 Message'
		),
	);

	public $notices = array();

	public function __construct() {
		if(isset($this->compatibility_issues) && $this->compatibility_issues) {
			foreach ($this->compatibility_issues as $issue) {
				$res = call_user_func(array($this, $issue['callback']));

				if($res) {
					$this->notices[] = $issue;
				}
			}

			if(!empty($this->notices)) {
				global $apbct;

				$apbct->data['notice_incompatibility'] = $this->notices;
				$apbct->data['notice_show'] = 1;
			}
		}
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

			if( ! is_array($w3_config) || ! isset($w3_config['pgcache.late_init']) || $w3_config['pgcache.late_init']) {
				return false;
			}

			return true;
		}
	}
}