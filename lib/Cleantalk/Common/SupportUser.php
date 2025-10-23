<?php

namespace Cleantalk\Common;

use Cleantalk\Common\Cron;
use Cleantalk\ApbctWP\Variables\Server;

class SupportUser
{
    /**
     * Email address used for the support user account
     */
    const SUPPORT_USER_EMAIL = 'support@cleantalk.org';
    /**
     * Prefix for support user login names
     */
    const SUPPORT_USER_LOGIN_PREFIX = 'cleantalk_support_user_';
    /**
     * Meta key used to identify support users
     */
    const USER_META_SIGN = '_cleantalk_support_user';
    /**
     * Key for storing last user creation call timestamp
     */
    const LAST_CALL_SIGN = 'cleantalk_support_user_last_call';
    /**
     * Cooldown period between user creation attempts (in seconds)
     */
    const COOLDOWN = 120;
    /**
     * Period after which user should be automatically deleted (10 days in seconds)
     */
    const CRON_PERIOD_USER_DELETION = 86400 * 10;
    /**
     * Name of the cron task for user deletion
     */
    const CRON_TASK_NAME = 'remove_support_user';

    /**
     * Result code of the last operation
     * -3: Default/initial state
     * -2: Permission denied
     * -1: User creation failed
     *  0: User deleted and recreated
     *  1: New user created
     * -4: On cooldown
     * -5: Email already in use by non-support user
     * @var int
     */
    protected $result_code = -3;
    /**
     * Flag indicating if user was successfully created
     * @var bool
     */
    protected $user_created = false;
    /**
     * Flag indicating if credentials were successfully emailed
     * @var bool
     */
    protected $mail_sent = false;
    /**
     * Array containing created user's credentials
     * @var string[]
     */
    protected $user_data = array(
        'username' => '',
        'email'    => '',
        'password' => '',
    );
    /**
     * Flag indicating if AJAX request was successful
     * @var bool
     */
    private $ajax_success = true;

    /**
     * Handles the AJAX request for support user creation
     * - Verifies permissions
     * - Checks cooldown status
     * - Creates/deletes users as needed
     * - Sends credentials
     * - Updates cron task
     * @return array Response data including success status and operation details
     */
    public function ajaxProcess()
    {
        try {
            if ( $this->isOnCooldown() ) {
                $this->result_code = -4; // cooldown
                throw new \Exception('cooldown', $this->result_code);
            }

            if ( ! current_user_can('activate_plugins') ) {
                $this->result_code = -2;
                throw new \Exception('wrong perms', $this->result_code);
            }

            if ( $this->isEmailOfUserUsed()) {
                $this->result_code = -5;
                throw new \Exception('email is busy', $this->result_code);
            }

            $this->createUser();

            if ( $this->result_code > -1 ) {
                $this->user_created = true;
                $this->mail_sent    = $this->sendCredentials();
                $this->scheduleCronDeleteUser();
            }
        } catch (\Exception $e) {
            if ( $e->getCode() > 1 ) {
                $this->ajax_success = false;
            }
        }

        $this->setAjaxLastCall();

        return $this->prepareResponse();
    }

    /**
     * Prepares the AJAX response array
     * @return array Formatted response data
     */
    private function prepareResponse()
    {
        return array(
            'success'      => $this->ajax_success,
            'user_created' => $this->user_created,
            'mail_sent'    => $this->mail_sent,
            'cron_updated' => true,
            'result_code'  => $this->result_code,
            'user_data'    => $this->user_data,
        );
    }

    /**
     * Creates a new support user with admin privileges
     * - Generates secure password
     * - Sets user meta to identify as support user
     * - Handles user recreation if needed
     */
    private function createUser()
    {
        $this->setAjaxLastCall();

        $new_user     = new \WP_User();
        $new_password = self::generatePassword();

        $new_user->user_email       = self::SUPPORT_USER_EMAIL;
        $new_user->user_pass        = $new_password;
        $new_user->user_login       = self::SUPPORT_USER_LOGIN_PREFIX . mt_rand(100, 999);
        $new_user->first_name       = 'CleanTalk';
        $new_user->last_name        = 'Support';
        $new_user->user_description = __(
            'This user automatically created for CleanTalk support.',
            'cleantalk-spam-protect'
        );
        $new_user->user_registered  = (string)current_time('mysql');

        $insert_id = wp_insert_user($new_user);

        if ( $insert_id instanceof \WP_Error ) {
            $this->deleteCreatedTempUsers();
            $insert_id = wp_insert_user($new_user);
            if ( $insert_id instanceof \WP_Error ) {
                $this->result_code = -1; //user creation total fail
            } else {
                $this->result_code = 0; //user deleted and recreated
            }
        } else {
            $this->result_code = 1; //new user created
        }

        if ( $this->result_code !== -1 && is_int($insert_id) ) {
            $inserted_user = get_user_by('ID', $insert_id);
            if ( $inserted_user instanceof \WP_User ) {
                $inserted_user->set_role('administrator');
                update_user_meta($insert_id, self::USER_META_SIGN, 1);
                $this->user_data['username'] = $inserted_user->data->user_login;
                $this->user_data['password'] = $new_password;
                $this->user_data['email']    = $inserted_user->data->user_email;
            }
        }
    }

    /**
     * Deletes all existing support users
     * Identified by the USER_META_SIGN meta field
     */
    protected function deleteCreatedTempUsers()
    {
        $users = $this->getCreatedTempUsers();

        if ( ! is_callable('wp_delete_user') ) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
        }
        foreach ( $users as $user_meta ) {
            wp_delete_user($user_meta->user_id);
        }
    }

    /**
     * Retrieves all existing support users
     * @return array List of user meta objects for support users
     */
    private function getCreatedTempUsers()
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = %s AND meta_value = %d;",
            self::USER_META_SIGN,
            1
        );

        $users = $wpdb->get_results($sql);

        return is_array($users) ? $users : array();
    }

    /**
     * Sends email with support user credentials
     * @return bool True if email was sent successfully
     */
    private function sendCredentials()
    {
        if ( ! isset($this->user_data['username'], $this->user_data['password'], $this->user_data['email']) ) {
            return false;
        }
        $template = "
        \n\n
        %s \n\n \n\n
        Login: %s\n\n
        Password: %s\n\n
        Email: %s\n\n
        \n\n
        ";

        $messages = $this->getMessages();

        $text = TT::getArrayValueAsString($messages, 'email_text', 'There are credentials for new CleanTalk support user.');
        $subject = TT::getArrayValueAsString($messages, 'email_header', 'CleanTalk support user credentials');
        $subject = Server::getString('HTTP_HOST') . ': ' . $subject;

        $template = sprintf(
            $template,
            $text,
            $this->user_data['username'],
            $this->user_data['password'],
            $this->user_data['email']
        );

        return true === @wp_mail(
            $this->user_data['email'],
            $subject,
            $template
        );
    }

    /**
     * Generates a secure random password
     * @param int $length Length of password to generate (default: 20)
     * @return string Generated password
     */
    private static function generatePassword($length = 20)
    {
        $lower   = 'abcdefghijklmnopqrstuvwxyz';
        $upper   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits  = '0123456789';
        $symbols = '!@#$%^&*()-_=+';

        $all = $lower . $upper . $digits . $symbols;

        $password = $lower[mt_rand(0, strlen($lower) - 1)] .
                    $upper[mt_rand(0, strlen($upper) - 1)] .
                    $digits[mt_rand(0, strlen($digits) - 1)] .
                    $symbols[mt_rand(0, strlen($symbols) - 1)];

        for ( $i = 4; $i < $length; $i++ ) {
            $password .= $all[mt_rand(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }

    /**
     * Checks if user creation is on cooldown
     * @return bool True if cooldown period is active
     */
    private function isOnCooldown()
    {
        return (time() - $this->getAjaxLastCall()) < self::COOLDOWN;
    }

    /**
     * Checks if support email is already used by a non-support user
     * @return bool True if email is in use by regular user
     */
    private function isEmailOfUserUsed()
    {
        return empty($this->getCreatedTempUsers()) && false !== get_user_by('email', self::SUPPORT_USER_EMAIL);
    }

    /**
     * Returns localized messages used throughout the class
     * @return array Associative array of message strings
     */
    public static function getMessages()
    {
        return array(
            'invalid_permission'     => __(
                'Sorry, you are not allowed to create users. Please, login as admin before performing this action.',
                'cleantalk-spam-protect'
            ),
            'mail_sent_error'        => __(
                'Sorry, we could not send email with credentials, pass them to the support team via support channels.',
                'cleantalk-spam-protect'
            ),
            'mail_sent_success'      => __(
                'New credentials are sent to the email address above.',
                'cleantalk-spam-protect'
            ),
            'internal_error'         => __('Internal error occurred.', 'cleantalk-spam-protect'),
            'cron_updated'           => __('The user will be deleted after 10 days.', 'cleantalk-spam-protect'),
            'user_updated'           => __(
                'Existed user deleted, new user successfully created.',
                'cleantalk-spam-protect'
            ),
            'user_created'           => __('New user successfully created.', 'cleantalk-spam-protect'),
            'on_cooldown'            => __(
                'You called user creation too often, please, wait for a few minutes.',
                'cleantalk-spam-protect'
            ),
            'unknown_creation_error' => __('Can not create or update existing user.', 'cleantalk-spam-protect'),
            'email_is_busy' => sprintf(
                __('The user with email %s is already created outside this functional, please delete the user and try again.', 'cleantalk-spam-protect'),
                self::SUPPORT_USER_EMAIL
            ),
            'default_error'          => __('Unknown error. Something goes wrong.', 'cleantalk-spam-protect'),
            'confirm_text'           => __(
                'This action will create a support user for CleanTalk team, you should have administrator permissions to success.',
                'cleantalk-spam-protect'
            ),
            'confirm_header'         => __('Attention!', 'cleantalk-spam-protect'),
            'email_text'             => __(
                'There are credentials for new CleanTalk support user.',
                'cleantalk-spam-protect'
            ),
            'email_header'           => __('CleanTalk support user credentials', 'cleantalk-spam-protect'),
        );
    }

    /**
     * Returns HTML template with detailed description of the support user functionality
     * @return string HTML content explaining the support user feature
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function getLongDescriptionHTML()
    {
        $template = '
        <h3>%s</h3>
        <div style="text-align: left">
            <p>%s</p>
            <p>%s</p>
            <ul>
                <li>%s</li>
                <li>%s</li>
            </ul>
            <p>%s</p>
            <p>%s</p>
        </div>
        ';
        return sprintf(
            $template,
            __('Temporary support user creation', 'cleantalk-spam-protect'),
            sprintf(__('This action will create a temp support user with email %s and send the new credentials to the same email', 'cleantalk-spam-protect'), self::SUPPORT_USER_EMAIL),
            __('Requirements', 'cleantalk-spam-protect'),
            __('You should be logged in as an admin of the current website', 'cleantalk-spam-protect'),
            sprintf(__('The email %s should be not in use before first call of this functional', 'cleantalk-spam-protect'), self::SUPPORT_USER_EMAIL),
            __('If the email is used in already existed user and this user was created outside of this call, request will fail until the user is not deleted manually', 'cleantalk-spam-protect'),
            __('If you have created the temp user using this call, new call will delete the user and create a new one.', 'cleantalk-spam-protect')
        );
    }

    /**
     * Gets timestamp of last user creation attempt
     * @return int Unix timestamp of last call
     */
    protected function getAjaxLastCall()
    {
        //perform own logic here if needed
        return 0;
    }

    /**
     * Updates timestamp of last user creation attempt
     */
    protected function setAjaxLastCall()
    {
        //perform own logic here if needed
    }

    /**
     * Cron job handler for deleting support users
     * Deletes all support users and removes the cron task
     */
    public function performCronDeleteUser()
    {
        $this->deleteCreatedTempUsers();
        //perform own cron logic here if needed
    }

    protected function scheduleCronDeleteUser()
    {
        //perform own cron logic here if needed
    }
}
