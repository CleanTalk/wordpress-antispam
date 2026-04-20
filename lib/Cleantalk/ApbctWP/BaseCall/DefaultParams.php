<?php

namespace Cleantalk\ApbctWP\BaseCall;

use Cleantalk\ApbctWP\RequestParameters\SubmitTimeHandler;
use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;

class DefaultParams
{
    private $auth_key = '';
    private $sender_info = [];

    public function __construct($auth_key, $sender_info = [])
    {
        $this->auth_key = $auth_key;
        $this->sender_info = $sender_info;
    }

    public function get()
    {
        return [
            'auth_key'        => $this->auth_key,
            'sender_info'     => $this->sender_info,
            'sender_ip'       => $this->getSenderIP(),
            'x_forwarded_for' => $this->getXForwardedForIP(),
            'x_real_ip'       => $this->getXRealIP(),
            'js_on'           => $this->getJsOn(),
            'agent'           => $this->getAgent(),
            'submit_time'     => $this->getSubmittime(),
        ];
    }

    public function getSenderIP()
    {
        $test_ip = $this->getTestIp();
        return $test_ip ?? $this->ipGet('remote_addr');
    }

    public function getXForwardedForIP()
    {
        return $this->ipGet('x_forwarded_for');
    }

    public function getXRealIP()
    {
        return $this->ipGet('x_real_ip');
    }

    public function getJsOn()
    {
        $cookieValue = Sanitize::cleanTextField(
            Cookie::getString('ct_checkjs')
        );

        return apbct_js_test($cookieValue, true)
            ? 1
            : apbct_js_test(Post::getString('ct_checkjs'));
    }

    public function getSubmittime()
    {
        return SubmitTimeHandler::getFromRequest();
    }

    /**
     * ---- Wrapper methods for testability ----
     */

    public function ipGet($type)
    {
        return \Cleantalk\ApbctWP\Helper::ipGet($type, false);
    }

    public function getAgent()
    {
        return APBCT_AGENT;
    }

    public function getTestIp()
    {
        return defined('CT_TEST_IP') && !empty(CT_TEST_IP)
            ? CT_TEST_IP
            : null;
    }
}
