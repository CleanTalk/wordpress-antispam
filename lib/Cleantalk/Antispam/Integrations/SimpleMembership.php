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

        if (class_exists('SwpmMembers') ) {
            $member    = \SwpmMemberUtils::get_user_by_email($this->member_info['email']);
            $member_id = $member->member_id;
            \SwpmMembers::delete_user_by_id($member_id);
        }

        ct_die(null, null);
    }
}
