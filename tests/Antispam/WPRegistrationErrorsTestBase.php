<?php

use Cleantalk\Antispam\IntegrationsByClass\WPRegistrationErrors;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;

abstract class WPRegistrationErrorsTestBase extends ApbctTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $apbct;
        $apbct->settings['forms__registrations_test'] = 1;
        $apbct->settings['forms__wc_register_from_order'] = 0;
        $apbct->data['moderate'] = 1;

        Post::getInstance()->variables = [];
        Cookie::getInstance()->variables = [];
        Server::getInstance()->variables = [];
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/register';
        $_COOKIE = array();
        $_POST = array();


        $GLOBALS['ct_signup_done'] = false;
        $GLOBALS['ct_negative_comment'] = '';
        $GLOBALS['ct_registration_error_comment'] = '';
        $GLOBALS['cleantalk_executed'] = false;
        $GLOBALS['apbct_cookie_request_id'] = null;
        $GLOBALS['apbct_cookie_register_ok_label'] = 'apbct_register_ok';
        $GLOBALS['apbct_cookie_request_id_label'] = 'apbct_request_id';
        $GLOBALS['ct_checkjs_register_form'] = 'ct_checkjs_register';
    }

    protected function makeHandler(array $onlyMethods = array())
    {
        if (empty($onlyMethods)) {
            return new WPRegistrationErrors();
        }

        return $this->getMockBuilder(WPRegistrationErrors::class)
            ->onlyMethods($onlyMethods)
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function setHandlerState(WPRegistrationErrors $handler)
    {
        $ref = new ReflectionClass($handler);

        $apbctProp = $ref->getProperty('apbct');
        $apbctProp->setAccessible(true);
        $apbctProp->setValue($handler, $GLOBALS['apbct']);

        $regFlagProp = $ref->getProperty('reg_flag');
        $regFlagProp->setAccessible(true);
        $regFlagProp->setValue($handler, true);
    }

    protected function makeCtResult(array $data = array())
    {
        $default = array(
            'id' => 123,
            'allow' => 1,
            'inactive' => 0,
            'comment' => '',
            'fast_submit' => 0,
            'blacklisted' => 0,
            'js_disabled' => 0,
        );

        return (object) array_merge($default, $data);
    }
}
