<?php

namespace Cleantalk\ApbctWP\FindSpam;

class UsersScanParameters
{
    private $new_check;
    private $unchecked;
    private $amount;
    private $offset;
    private $accurate_check;
    private $from;
    private $till;
    private $skip_roles;

    public function __construct($post)
    {
        $this->new_check      = isset($post['new_check']) ? $post['new_check'] : true;
        $this->unchecked      = isset($post['unchecked']) ? $post['unchecked'] : 'unset';
        $this->amount         = isset($post['amount']) ? $post['amount'] : 100;
        $this->offset         = isset($_COOKIE['apbct_check_users_offset']) ? (int)$_COOKIE['apbct_check_users_offset'] : 0;
        $this->accurate_check = isset($post['accurate_check']) ? $post['accurate_check'] : false;
        $this->from           = isset($post['from']) ? $post['from'] : false;
        $this->till           = isset($post['till']) ? $post['till'] : false;
        $this->skip_roles     = array('administrator');
    }

    /**
     * @return array[]
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getSkipRoles()
    {
        return $this->skip_roles;
    }

    /**
     * @return bool|mixed
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getNewCheck()
    {
        return $this->new_check;
    }

    /**
     * @return mixed|string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getUnchecked()
    {
        return $this->unchecked;
    }

    /**
     * @return int|mixed
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @return int|mixed
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @return false|mixed
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getAccurateCheck()
    {
        return $this->accurate_check;
    }

    /**
     * @return false|mixed
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * @return false|mixed
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getTill()
    {
        return $this->till;
    }
}
