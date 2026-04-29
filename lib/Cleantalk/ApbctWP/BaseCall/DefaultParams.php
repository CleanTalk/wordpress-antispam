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

    /**
     * Get default params
     * @return array
     */
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
            'submit_time'     => $this->getSubmitTime(),
        ];
    }

    /**
     * Get sender IP
     * @return string|null
     */
    public function getSenderIP()
    {
        $test_ip = $this->getTestIp();
        return $test_ip ?? $this->ipGet('remote_addr');
    }

    /**
     * Get X-Forwarded-For
     * @return string|null
     */
    public function getXForwardedForIP()
    {
        return $this->ipGet('x_forwarded_for');
    }

    /**
     * Get X-Real-IP
     * @return string|null
     */
    public function getXRealIP()
    {
        return $this->ipGet('x_real_ip');
    }

    /**
     * Get JS on
     * @return int|null
     */
    public function getJsOn()
    {
        $cookieValue = Sanitize::cleanTextField(
            Cookie::getString('ct_checkjs')
        );

        return apbct_js_test($cookieValue, true)
            ? 1
            : apbct_js_test(Post::getString('ct_checkjs'));
    }

    /**
     * @return int|null
     */
    public function getSubmitTime()
    {
        return SubmitTimeHandler::getFromRequest();
    }

    /**
     * Retrieves the IP address based on the specified type.
     *
     * @param string $type The type or format of the IP address to retrieve.
     * @return string|null Returns the IP address as a string if available; otherwise, returns null.
     */
    public function ipGet($type)
    {
        return \Cleantalk\ApbctWP\Helper::ipGet($type, false);
    }

    /**
     * Retrieves the value of the constant APBCT_AGENT.
     *
     * @return string Returns the value of APBCT_AGENT.
     */
    public function getAgent()
    {
        return APBCT_AGENT;
    }

    /**
     * Retrieves the value of the constant CT_TEST_IP if it is defined and not empty.
     *
     * @return string|null Returns the value of CT_TEST_IP if available; otherwise, returns null.
     */
    public function getTestIp()
    {
        return defined('CT_TEST_IP') && !empty(CT_TEST_IP)
            ? CT_TEST_IP
            : null;
    }
}
