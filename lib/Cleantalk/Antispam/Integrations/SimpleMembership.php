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
     */
    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;

        if ( class_exists('SwpmMemberUtils') ) {
            // Doing inactive user status after blocking.
            $member    = \SwpmMemberUtils::get_user_by_email($this->member_info['email']);
            $member_id = $member->member_id;
            \SwpmMemberUtils::update_account_state($member_id, 'inactive');
        }

        ct_die(null, null);
    }
}
