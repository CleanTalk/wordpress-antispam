<?php

namespace Cleantalk\Antispam {
    function apbct_base_call($params = array(), $reg_flag = false)
    {
        $GLOBALS['apbct_simple_membership_calls'][] = array($params, $reg_flag);

        return array(
            'ct_result' => (object) array(
                'allow' => $GLOBALS['apbct_simple_membership_allow'],
                'comment' => 'Registration blocked',
            ),
        );
    }
}

namespace {
    class SwpmMemberUtils
    {
        public static $members = array();
        public static $lookups = array();

        public static function get_user_by_email($email)
        {
            self::$lookups[] = $email;
            foreach ( self::$members as $member ) {
                if ( $member->email === $email ) {
                    return $member;
                }
            }

            return false;
        }
    }

    class SwpmMembers
    {
        public static $deleted_ids = array();

        public static function delete_user_by_id($member_id)
        {
            self::$deleted_ids[] = $member_id;
            unset(SwpmMemberUtils::$members[$member_id]);
        }
    }
}
