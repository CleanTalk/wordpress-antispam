<?php

namespace Cleantalk\ApbctWP\FindSpam;

use Cleantalk\Common\Helper;
use Cleantalk\Common\TT;

/**
 * Class LoginIPKeeper
 *
 * This class is responsible for managing user IP data, storing it in WordPress options,
 * and performing operations such as adding, retrieving, and rotating user IP records.
 */
class LoginIPKeeper
{
    /**
     * @var string WordPress option name for storing user IP data.
     */
    private static $wp_meta_name = '_cleantalk_ip_keeper_data';

    /**
     * Save the provided in user's IP and last login from session_tokens.
     *
     * @param \WP_User $wp_user
     *
     * @return void
     */
    public function addUserIP($wp_user)
    {
        // run record adding to user meta
        if ($wp_user instanceof \WP_User && 0 !== $wp_user->ID) {
            $session_tokens = get_user_meta($wp_user->ID, 'session_tokens', true);
            $data = is_array($session_tokens) ? reset($session_tokens) : false;
            if ( is_array($data) ) {
                $ip = TT::getArrayValueAsString($data, 'ip');
                if ( Helper::ipValidate($ip) ) {
                    update_user_meta($wp_user->ID, self::$wp_meta_name, $ip);
                }
            }
        }
    }

    /**
     * Retrieves data from user meta of a user by his user ID.
     *
     * @param int|string $user_id User ID to search for.
     *
     * @return string|null The selected record property value of user meta data.
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getIP($user_id)
    {
        $user_id = TT::toInt($user_id);
        $ip = get_user_meta($user_id, self::$wp_meta_name, true);
        return Helper::ipValidate($ip) ? $ip : null;
    }
}
