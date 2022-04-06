<?php

namespace Cleantalk\Common;

/**
 * CleanTalk Compatibility class.
 *
 * @package       PHP Anti-Spam by CleanTalk
 * @subpackage    Compatibility
 * @Version       3.5
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see           https://github.com/CleanTalk/php-antispam
 *
 * @psalm-suppress UnusedProperty
 */
class Compatibility
{
    public $notices = array();

    private $possible_issues = array(
        'w3_total_cache' => array(
            'callback' => 'w3tcCheckLateInitCallback',
        ),
    );

    public function __construct()
    {
        foreach ($this->possible_issues as $plugin_name => $issue) {
            $this->{$issue['callback']}() && $this->setNoticesByPlugin($plugin_name);
        }

        global $apbct;

        if ($this->notices) {
            $apbct->data['notice_incompatibility'] = $this->notices;
        } else {
            $apbct->data['notice_incompatibility'] = array();
        }
        $apbct->saveData();
    }

    /**
     * Function return array compatibility_issues
     *
     * @return true
     */
    private function setNoticesByPlugin($plugin)
    {
        $messages = array(
            'w3_total_cache' => __(
                'Current W3 Total Cache cache mode is not compatible with SpamFireWall. Please read the instructions on how to fix this issue in <a href="https://cleantalk.org/help/cleantalk-and-w3-total-cache" > the article.</a>'
            ),
            'wp_rocket_cache' => esc_html__(
                'SpamFireWall is not compatible with active WP Rocket cache plugin.',
                'cleantalk-spam-protect'
            )
        );

        $this->notices[$plugin] = $messages[$plugin];

        return true;
    }


    /**
     * W3 Total Cache check late init option
     *
     * @return boolean
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function w3tcCheckLateInitCallback()
    {
        if (
            is_plugin_active('w3-total-cache/w3-total-cache.php') &&
            file_exists(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'w3tc-config/master.php')
        ) {
            $w3_config = file_get_contents(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'w3tc-config/master.php');
            $w3_config = str_replace('<?php exit; ?>', '', $w3_config);
            $w3_config = json_decode($w3_config, true);

            if (
                (isset($w3_config['pgcache.cache.ssl']) && ! $w3_config['pgcache.cache.ssl']) ||
                (isset($w3_config['pgcache.late_init']) && $w3_config['pgcache.late_init'])) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * WP Rocket existing checking
     *
     * @return boolean
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function wpRocketCallback()
    {
        global $apbct;
        return
            $apbct->settings['sfw__enabled'] &&
            is_plugin_active('wp-rocket/wp-rocket.php') &&
            defined('WP_ROCKET_VERSION');
    }
}
