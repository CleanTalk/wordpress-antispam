<?php

namespace Cleantalk\Antispam\Integrations;

class SimpleMembership extends IntegrationBase
{
    protected $member_info = array();

    public function getDataForChecking($member_info)
    {
        $this->member_info = $member_info;

        return (
            array(
                'email'    => $member_info['email'],
                'nickname' => $member_info['user_name'],
            )
        );
    }

    /**
     * @param $message
     *
     * @psalm-suppress UnusedVariable
     * @psalm-suppress UndefinedClass
     */
    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;

        $wp_user = ! empty($this->member_info['email'])
            ? get_user_by('email', $this->member_info['email'])
            : false;

        if (class_exists('SwpmMembers') ) {
            $member    = \SwpmMemberUtils::get_user_by_email($this->member_info['email']);
            $member_id = $member->member_id;
            \SwpmMembers::delete_user_by_id($member_id);
        }

        if ( $wp_user instanceof \WP_User ) {
            $this->deleteUserFromNetwork($wp_user);
        }

        ct_die(null, null);
    }

    /**
     * On multisite wp_delete_user() only detaches the user from the current site,
     * so the blocked registration still shows up in Network Admin -> Users.
     *
     * @param \WP_User $wp_user
     *
     * @return void
     */
    private function deleteUserFromNetwork(\WP_User $wp_user)
    {
        if ( ! is_multisite() || is_super_admin($wp_user->ID) ) {
            return;
        }

        // Keep the account if it is still attached to any other site of the network
        $blogs = get_blogs_of_user($wp_user->ID, true);
        unset($blogs[get_current_blog_id()]);
        if ( ! empty($blogs) ) {
            return;
        }

        // Only the account created by this very registration may be removed
        if ( strtotime($wp_user->user_registered) < time() - 300 ) {
            return;
        }

        if ( ! function_exists('wpmu_delete_user') ) {
            require_once ABSPATH . 'wp-admin/includes/ms.php';
        }

        wpmu_delete_user($wp_user->ID);
    }
}
