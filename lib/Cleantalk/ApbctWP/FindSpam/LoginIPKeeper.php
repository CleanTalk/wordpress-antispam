<?php

namespace Cleantalk\ApbctWP\FindSpam;

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
     * @var int Time in seconds after which inactive users are deleted (30 days by default).
     */
    private static $user_inactive_time_to_being_deleted = 86400 * 30;

    /**
     * Hook action.
     *
     * @param \WP_User $wp_user WordPress user object.
     * @return void
     */
    public function hookSaveLoggedInUserData($wp_user)
    {
        // run rotation on every login event
        $this->rotateData();
        // run record adding to user meta
        $this->addRecord($wp_user);
    }

    /**
     * Save the provided in user's IP and last login from session_tokens.
     *
     * @param \WP_User $wp_user
     *
     * @return void
     */
    public function addRecord($wp_user)
    {
        // run record adding to user meta
        if ($wp_user instanceof \WP_User && 0 !== $wp_user->ID) {
            $session_tokens = get_user_meta($wp_user->ID, 'session_tokens', true);
            $data = reset($session_tokens);
            $record = array();
            if ($data) {
                $record['ip'] = TT::getArrayValueAsString($data, 'ip');
                $record['last_login'] = TT::getArrayValueAsString($data, 'login');
                $record = json_encode($record);
                if (false !== $record) {
                    $this->updateMetaRecord($wp_user->ID, $record);
                }
            }
        }
    }

    /**
     * Retrieves data from user meta of a user by his user ID.
     *
     * @param int|string $user_id User ID to search for.
     * @param string $property ip|last_login, default is ip
     *
     * @return string|null The selected record property value of user meta data.
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getMetaRecordValue($user_id, $property = 'ip')
    {
        $result = null;
        $user_id = TT::toInt($user_id);
        $meta = get_user_meta($user_id, self::$wp_meta_name, true);
        $meta = json_decode($meta, true);
        if (!empty($meta) && !empty($meta[$property])) {
            $result = $meta[$property];
        }
        return $result;
    }

    /**
     * Remove inactive users from the stored data.
     *
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    private function rotateData()
    {
        global $wpdb;
        $meta_values = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, meta_value 
         FROM {$wpdb->usermeta} 
         WHERE meta_key = %s",
                self::$wp_meta_name
            ),
            ARRAY_A
        );
        foreach ($meta_values as $_meta => $data) {
            $user_id = isset($data['user_id'])
                ? $data['user_id']
                : false;
            $user_exists = !empty($user_id)
                ? !empty(get_user_by('ID', $user_id))
                : false;
            if ($user_id && (!$user_exists || !$this->isUserAlive($user_id))) {
                $this->deleteUserMetaRecord($user_id);
            }
        }
    }

    /**
     * @param int|string $user_id
     *
     * @return void
     */
    public function deleteUserMetaRecord($user_id)
    {
        $user_id = TT::toInt($user_id);
        delete_user_meta($user_id, self::$wp_meta_name);
    }

    /**
     * Adds a new record to the keeper data.
     *
     * @param int $user_id User ID.
     * @param string $record JSON of meta record to add.
     * @return void
     */
    private function updateMetaRecord($user_id, $record)
    {
        update_user_meta($user_id, self::$wp_meta_name, $record);
    }

    /**
     * Check if IP record meta last login value is lesser than inactivity time.
     * @param int|string $user_id
     *
     * @return bool False if no record found or last login is more that inactivity time, true otherwise.
     */
    private function isUserAlive($user_id)
    {
        $last_login = $this->getMetaRecordValue($user_id, 'last_login');
        if ($last_login) {
            $last_login = TT::toInt($last_login);
            return time() - $last_login < self::$user_inactive_time_to_being_deleted;
        }
        return false;
    }
}
